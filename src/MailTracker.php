<?php

namespace ABCreche\MailTracker;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use ABCreche\MailTracker\Model\SentEmail;
use ABCreche\MailTracker\Events\EmailSentEvent;
use ABCreche\MailTracker\Model\SentEmailUrlClicked;
use Ramsey\Uuid\Uuid;

class MailTracker implements \Swift_Events_SendListener
{
    protected $uuid;

    /**
     * Inject the tracking code into the message
     */
    public function beforeSendPerformed(\Swift_Events_SendEvent $event)
    {
        $message = $event->getMessage();

        // Create the trackers
        $this->createTrackers($message);

        // Purge old records
        $this->purgeOldRecords();
    }

    public function sendPerformed(\Swift_Events_SendEvent $event)
    {
        // If this was sent through SES, retrieve the data
        if ((config('mail.default') ?? config('mail.driver')) == 'ses') {
            $message = $event->getMessage();
            $this->updateSesMessageId($message);
        }
    }

    protected function updateSesMessageId($message)
    {
        // Get the SentEmail object
        $headers = $message->getHeaders();
        $uuid = optional($headers->get('X-Mailer-Uuid'))->getFieldBody();
        $sent_email = SentEmail::where('uuid', $uuid)->first();

        // Get info about the message-id from SES
        if ($sent_email) {
            $sent_email->message_id = $headers->get('X-SES-Message-ID')->getFieldBody();
            $sent_email->save();
        }
    }

    protected function addTrackers($message, $uuid)
    {
        $headers = $message->getHeaders();

        $html = $message->getBody();

        if ($headers->get('X-Track-Opens')) {
            $html = $this->injectTrackingPixel($html, $uuid);
        }
        if ($headers->get('X-Track-Links')) {
            $html = $this->injectLinkTracker($html, $uuid);
        }

        return $html;
    }

    protected function injectTrackingPixel($html, $uuid)
    {
        // Append the tracking url
        $tracking_pixel = '<img border=0 width=1 alt="" height=1 src="'.route('mailTracker_t', [$uuid]).'" />';

        $linebreak = app(Str::class)->random(32);
        $html = str_replace("\n", $linebreak, $html);

        if (preg_match("/^(.*<body[^>]*>)(.*)$/", $html, $matches)) {
            $html = $matches[1].$matches[2].$tracking_pixel;
        } else {
            $html = $html . $tracking_pixel;
        }
        $html = str_replace($linebreak, "\n", $html);

        return $html;
    }

    protected function injectLinkTracker($html, $uuid)
    {
        $this->uuid = $uuid;

        $html = preg_replace_callback(
            "/(<a[^>]*href=[\"])([^\"]*)/",
            [$this, 'inject_link_callback'],
            $html
        );

        return $html;
    }

    protected function inject_link_callback($matches)
    {
        if (empty($matches[2])) {
            $url = app()->make('url')->to('/');
        } else {
            $url = str_replace('&amp;', '&', $matches[2]);
        }

        $url = $matches[1].route(
            'mailTracker_n',
            [
                'l' => $url,
                'h' => $this->uuid
            ]
        );

        return $url;
    }

    /**
     * Legacy function
     *
     * @param [type] $url
     * @return boolean
     */
    public static function uuid_url($url)
    {
        // Replace "/" with "$"
        return str_replace("/", "$", base64_encode($url));
    }

    /**
     * Create the trackers
     *
     * @param  Swift_Mime_Message $message
     * @return void
     */
    protected function createTrackers($message)
    {
        if (!$message->getTo() || !is_array($message->getTo())) {
            return;
        }

        foreach ($message->getTo() as $to_email => $to_name) {
            foreach ($message->getFrom() as $from_email => $from_name) {
                $headers = $message->getHeaders();
                if ($headers->get('X-No-Track') || !$headers->get('X-Track')) {
                    // Don't send with this header
                    $headers->remove('X-No-Track');
                    // Don't track this email
                    continue;
                }
                do {
                    $uuid = (string) Uuid::uuid4();
                    $used = SentEmail::where('uuid', $uuid)->count();
                } while ($used > 0);
                $shouldLogBody = !empty($headers->get('X-LogBody'));

                $headers->addTextHeader('X-Mailer-Uuid', $uuid);
                $subject = $message->getSubject();

                $original_content = $message->getBody();

                if ($message->getContentType() === 'text/html' ||
                    ($message->getContentType() === 'multipart/alternative' && $message->getBody()) ||
                    ($message->getContentType() === 'multipart/mixed' && $message->getBody())
                ) {
                    $message->setBody($this->addTrackers($message, $uuid));
                }

                foreach ($message->getChildren() as $part) {
                    if (strpos($part->getContentType(), 'text/html') === 0) {
                        $part->setBody($this->addTrackers($message, $uuid));
                    }
                }

                $tracker = SentEmail::create([
                    'uuid' => $uuid,
                    'headers' => $headers->toString(),
                    'sender' => $from_name." <".$from_email.">",
                    'recipient' => $to_name.' <'.$to_email.'>',
                    'subject' => $subject,
                    'content' => $shouldLogBody ? (strlen($original_content) > 65535 ? substr($original_content, 0, 65532) . "..." : $original_content) : null,
                    'opens' => 0,
                    'clicks' => 0,
                    'message_id' => $message->getId(),
                    'meta' => [],
                ]);

                Event::dispatch(new EmailSentEvent($tracker));

                $headers->remove('X-Model-ID');
                $headers->remove('X-Model-Type');
                $headers->remove('X-Model-Tag');
                $headers->remove('X-LogBody');
            }
        }
    }

    /**
     * Purge old records in the database
     *
     * @return void
     */
    protected function purgeOldRecords()
    {
        if (config('mail-tracker.expire-days') > 0) {
            $emails = SentEmail::where('created_at', '<', \Carbon\Carbon::now()
                ->subDays(config('mail-tracker.expire-days')))
                ->select('id')
                ->get();
            SentEmailUrlClicked::whereIn('sent_email_id', $emails->pluck('id'))->delete();
            SentEmail::whereIn('id', $emails->pluck('id'))->delete();
        }
    }
}
