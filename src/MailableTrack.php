<?php
namespace ABCreche\MailTracker;

use ABCreche\MailTracker\Trackable;
use Illuminate\Mail\Mailable;

class MailableTrack extends Mailable
{
    use Trackable;

    public function __construct()
    {
        $this->witTracking();

        if (config('mail-tracker.log-content')) {
            $this->logBody();
        }
        if (config('mail-tracker.track-opens')) {
            $this->trackOpens();
        }
        if (config('mail-tracker.track-links')) {
            $this->trackLinks();
        }
    }
}
