<?php

namespace ABCreche\MailTracker\Model;

use Illuminate\Database\Eloquent\Model;

// use Model\SentEmail;

class SentEmailUrlClicked extends Model
{
    protected $table = '_sent_emails_url_clicked';

    protected $fillable = [
        'sent_email_id',
        'url',
        'uuid',
        'clicks',
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
        return config('mail-tracker.table_email_clicks', 'sent_emails_url_clicked');
    }

    public function email()
    {
        return $this->belongsTo(SentEmail::class, 'sent_email_id');
    }
}
