<?php

namespace ABCreche\MailTracker\Model;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $uuid
 * @property string $headers
 * @property string $sender
 * @property string $recipient
 * @property string $subject
 * @property string $content
 * @property int $opens
 * @property int $clicks
 * @property int|null $message_id
 * @property string $meta
 */
class SentEmail extends Model
{
    protected $fillable = [
        'uuid',
        'headers',
        'sender_name',
        'sender_email',
        'recipient_name',
        'recipient_email',
        'subject',
        'content',
        'opens',
        'clicks',
        'message_id',
        'meta',
        'opened_at',
        'clicked_at',
    ];

    protected $casts = [
        'meta' => 'collection',
    ];

    public function getConnectionName()
    {
        return config('mail-tracker.connection') ?: config('database.default');
    }

    /**
     * {@inheritdoc}
     */
    public function getTable(): string
    {
        return config('mail-tracker.table_emails', parent::getTable());
    }

    /**
     * Returns the smtp detail for this message ()
     * @return [type] [description]
     */
    public function getSmtpInfoAttribute()
    {
        if (empty($this->meta)) {
            return '';
        }
        $meta = $this->meta;
        $responses = [];
        if ($meta->has('smtpResponse')) {
            $response = $meta->get('smtpResponse');
            $delivered_at = $meta->get('delivered_at');
            $responses[] = $response.' - Delivered '.$delivered_at;
        }
        if ($meta->has('failures')) {
            foreach ($meta->get('failures') as $failure) {
                if (!empty($failure['status'])) {
                    $responses[] = $failure['status'].' ('.$failure['action'].'): '.$failure['diagnosticCode'].' ('.$failure['emailAddress'].')';
                } else {
                    $responses[] = 'Generic Failure ('.$failure['emailAddress'].')';
                }
            }
        } elseif ($meta->has('complaint')) {
            $complaint_time = $meta->get('complaint_time');
            if ($meta->get('complaint_type')) {
                $responses[] = 'Complaint: '.$meta->get('complaint_type').' at '.$complaint_time;
            } else {
                $responses[] = 'Complaint at '.$complaint_time->format("n/d/y g:i a");
            }
        }
        return implode(" | ", $responses);
    }

    /**
     * Returns the header requested from our stored header info
     */
    public function getHeader($key)
    {
        $headers = collect(preg_split("/\r\n|\n|\r/", $this->headers))
            ->filter(function ($header) {
                return preg_match("/:/", $header);
            })
            ->transform(function ($header) {
                list($key, $value) = explode(":", $header, 2);
                return collect([
                    'key' => trim($key),
                    'value' => trim($value)
                ]);
            })->filter(function ($header) {
                return $header->get('key');
            })->keyBy('key')
            ->transform(function ($header) {
                return $header->get('value');
            });
        return $headers->get($key);
    }

    public function urlClicks()
    {
        return $this->hasMany(SentEmailUrlClicked::class);
    }
}
