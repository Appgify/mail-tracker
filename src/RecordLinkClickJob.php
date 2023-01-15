<?php

namespace ABCreche\MailTracker;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Event;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use ABCreche\MailTracker\Model\SentEmail;
use ABCreche\MailTracker\Events\LinkClickedEvent;
use ABCreche\MailTracker\Model\SentEmailUrlClicked;
use ABCreche\MailTracker\Events\EmailDeliveredEvent;

class RecordLinkClickJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $sentEmail;
    public $url;

    public function retryUntil()
    {
        return now()->addDays(5);
    }

    public function __construct($sentEmail, $url)
    {
        $this->sentEmail = $sentEmail;
        $this->url = $url;
    }

    public function handle()
    {
        $this->sentEmail->clicks++;
        $this->sentEmail->save();
        $url_clicked = SentEmailUrlClicked::where('url', $this->url)->where('uuid', $this->sentEmail->uuid)->first();
        if ($url_clicked) {
            $url_clicked->clicks++;
            $url_clicked->save();
        } else {
            $url_clicked = SentEmailUrlClicked::create([
                'sent_email_id' => $this->sentEmail->id,
                'url' => $this->url,
                'uuid' => $this->sentEmail->uuid,
            ]);
        }
        Event::dispatch(new LinkClickedEvent($this->sentEmail));
    }
}
