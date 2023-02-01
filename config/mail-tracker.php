<?php

return [
    /**
     * Determines whether or not the body of the email is logged in the sent_emails table
     */
    'log-content' => false,

    /**
     * To disable by default the open tracks, set this to false.
     */
    'track-opens' => true,

    /**
     * To disable by default injecting tracking links, set this to false.
     */
    'track-links' => false,

    /**
     * Optionally expire old emails, set to 0 to keep forever.
     */
    'expire-days' => 60,

    /**
     * Where should the pingback URL route be?
     */
    'route' => [
        'prefix' => 'email',
        'middleware' => ['api'],
    ],

    /**
     * Default database connection name (optional - use null for default)
     */
    'connection' => null,

    'table_emails' => 'sent_emails',

    'table_email_clicks' => 'sent_emails_url_clicked',

    /**
     * The SNS notification topic - if set, discard all notifications not in this topic.
     */
    'sns-topic' => null,

    /**
     * What queue should we dispatch our tracking jobs to?  Null will use the default queue.
     */
    'tracker-queue' => null,

];
