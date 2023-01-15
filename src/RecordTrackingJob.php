<?php

namespace ABCreche\MailTracker;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Event;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use ABCreche\MailTracker\Model\SentEmail;
use ABCreche\MailTracker\Events\ViewEmailEvent;
use ABCreche\MailTracker\Events\LinkClickedEvent;
use ABCreche\MailTracker\Model\SentEmailUrlClicked;
use ABCreche\MailTracker\Events\EmailDeliveredEvent;

class RecordTrackingJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $sentEmail;

    public function __construct($sentEmail)
    {
        $this->sentEmail = $sentEmail;
    }

    public function retryUntil()
    {
        return now()->addDays(5);
    }

    public function handle()
    {
        $this->sentEmail->opens++;
        $this->sentEmail->save();
        Event::dispatch(new ViewEmailEvent($this->sentEmail));
    }
}
