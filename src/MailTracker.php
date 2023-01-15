<?php

namespace ABCreche\MailTracker;

use Exception;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Str;
use Illuminate\Mail\SentMessage;
use Symfony\Component\Mime\Email;
use Illuminate\Support\Facades\Event;
use Illuminate\Mail\Events\MessageSent;
use Symfony\Component\Mime\Part\TextPart;
use Illuminate\Mail\Events\MessageSending;
use ABCreche\MailTracker\Model\SentEmail;
use ABCreche\MailTracker\Events\EmailSentEvent;
use Symfony\Component\Mime\Part\Multipart\MixedPart;
use ABCreche\MailTracker\Model\SentEmailUrlClicked;
use Closure;
use Symfony\Component\Mime\Part\Multipart\RelatedPart;
use Symfony\Component\Mime\Part\Multipart\AlternativePart;

class MailTracker
{
    // Set this to "false" to skip this library migrations
    public static $runsMigrations = true;

    protected $uuid;

    /**
     * Configure this library to not register its migrations.
     *
     * @return static
     */
    public static function ignoreMigrations()
    {
        static::$runsMigrations = false;

        return new static;
    }

    /**
     * Inject the tracking code into the message
     */
    public function messageSending(MessageSending $event)
    {
        $message = $event->message;

        // Create the trackers
        $this->createTrackers($message);

        // Purge old records
        $this->purgeOldRecords();
    }

    public function messageSent(MessageSent $event)
    {
        $sentMessage = $event->sent;
        $headers = $sentMessage->getOriginalMessage()->getHeaders();
        $uuid = optional($headers->get('X-Mailer-Uuid'))->getBody();
        $sentEmail = SentEmail::where('uuid', $uuid)->first();

        if ($sentEmail) {
            $sentEmail->message_id = $this->callMessageIdResolverUsing($sentMessage);
            $sentEmail->save();
        }
    }

    protected function callMessageIdResolverUsing(SentMessage $message): string
    {
        return $this->getMessageIdResolver()(...func_get_args());
    }

    public function getMessageIdResolver(): Closure
    {
        if (! isset($this->messageIdResolver)) {
            $this->resolveMessageIdUsing($this->getDefaultMessageIdResolver());
        }

        return $this->messageIdResolver;
    }

    public function resolveMessageIdUsing(Closure $resolver): self
    {
        $this->messageIdResolver = $resolver;
        return $this;
    }

    protected function getDefaultMessageIdResolver(): Closure
    {
        return function (SentMessage $message) {
            /** @var \Symfony\Component\Mime\Header\Headers $headers */
            $headers = $message->getOriginalMessage()->getHeaders();

            // Laravel supports multiple mail drivers.
            // We try to guess if this email was sent using SES
            if ($messageHeader = $headers->get('X-SES-Message-ID')) {
                return $messageHeader->getBody();
            }

            // Second attempt, get the default message ID from symfony mailer
            return $message->getMessageId();
        };
    }

    protected function updateMessageId(SentMessage $message)
    {
        // Get the SentEmail object
        $headers = $message->getOriginalMessage()->getHeaders();
        $uuid = optional($headers->get('X-Mailer-Uuid'))->getBody();
        $sent_email = SentEmail::where('uuid', $uuid)->first();

        // Get info about the message-id from SES
        if ($sent_email) {
            $sent_email->message_id = $message->getMessageId();
            $sent_email->save();
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

    protected function addTrackers($message, $html, $uuid)
    {
        $headers = $message->getHeaders();

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

        foreach ($message->getTo() as $toAddress) {
            $to_email = $toAddress->getAddress();
            $to_name = $toAddress->getName();
            foreach ($message->getFrom() as $fromAddress) {
                $from_email = $fromAddress->getAddress();
                $from_name = $fromAddress->getName();
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
                $original_html = '';
                if (
                    ($original_content instanceof(AlternativePart::class)) ||
                    ($original_content instanceof(MixedPart::class)) ||
                    ($original_content instanceof(RelatedPart::class))
                ) {
                    $messageBody = $message->getBody() ?: [];
                    $newParts = [];
                    foreach ($messageBody->getParts() as $part) {
                        if (method_exists($part, 'getParts')) {
                            foreach ($part->getParts() as $p) {
                                if ($p->getMediaSubtype() == 'html') {
                                    $original_html = $p->getBody();
                                    $newParts[] = new TextPart(
                                        $this->addTrackers($message, $original_html, $uuid),
                                        $message->getHtmlCharset(),
                                        $p->getMediaSubtype(),
                                        null
                                    );

                                    break;
                                }
                            }
                        }

                        if ($part->getMediaSubtype() == 'html') {
                            $original_html = $part->getBody();
                            $newParts[] = new TextPart(
                                $this->addTrackers($message, $original_html, $uuid),
                                $message->getHtmlCharset(),
                                $part->getMediaSubtype(),
                                null
                            );
                        } else {
                            $newParts[] = $part;
                        }
                    }
                    $message->setBody(new AlternativePart(...$newParts));
                } else {
                    $original_html = $original_content->getBody();
                    if ($original_content->getMediaSubtype() == 'html') {
                        $message->setBody(
                            new TextPart(
                                $this->addTrackers($message, $original_html, $uuid),
                                $message->getHtmlCharset(),
                                $original_content->getMediaSubtype(),
                                null
                            )
                        );
                    }
                }

                $tracker = SentEmail::create([
                    'uuid' => $uuid,
                    'headers' => $headers->toString(),
                    'sender_name' => $from_name,
                    'sender_email' => $from_email,
                    'recipient_name' => $to_name,
                    'recipient_email' => $to_email,
                    'subject' => $subject,
                    'content' => $shouldLogBody ? (strlen($original_html) > 65535 ? substr($original_html, 0, 65532) . "..." : $original_html) : null,
                    'opens' => 0,
                    'clicks' => 0,
                    'message_id' => (string) Uuid::uuid4(),
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
