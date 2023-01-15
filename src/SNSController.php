<?php

namespace ABCreche\MailTracker;

use Event;

use App\Http\Requests;
use Illuminate\Http\Request;
use GuzzleHttp\Client as Guzzle;
use Aws\Sns\Message as SNSMessage;
use Illuminate\Routing\Controller;
use ABCreche\MailTracker\Model\SentEmail;
use ABCreche\MailTracker\RecordBounceJob;
use ABCreche\MailTracker\RecordDeliveryJob;
use ABCreche\MailTracker\RecordComplaintJob;
use Aws\Sns\MessageValidator as SNSMessageValidator;
use ABCreche\MailTracker\Events\EmailDeliveredEvent;
use ABCreche\MailTracker\Events\ComplaintMessageEvent;
use ABCreche\MailTracker\Events\PermanentBouncedMessageEvent;

class SNSController extends Controller
{
    public function callback(Request $request)
    {
        if (config('app.env') != 'production' && $request->message) {
            // phpunit cannot mock static methods so without making a facade
            // for SNSMessage we have to pass the json data in $request->message
            $message = new SNSMessage(json_decode($request->message, true));
        } else {
            $message = SNSMessage::fromRawPostData();
            $validator = app(SNSMessageValidator::class);
            $validator->validate($message);
        }
        // If we have a topic defined, make sure this is that topic
        if (config('mail-tracker.sns-topic') && $message->offsetGet('TopicArn') != config('mail-tracker.sns-topic')) {
            return 'invalid topic ARN';
        }

        switch ($message->offsetGet('Type')) {
            case 'SubscriptionConfirmation':
                return $this->confirm_subscription($message);
            case 'Notification':
                return $this->process_notification($message);
        }
    }

    protected function confirm_subscription($message)
    {
        $client = new Guzzle();
        $client->get($message->offsetGet('SubscribeURL'));
        return 'subscription confirmed';
    }

    protected function process_notification($message)
    {
        $message = json_decode($message->offsetGet('Message'));
        switch ($message->notificationType) {
            case 'Delivery':
                $this->process_delivery($message);
                break;
            case 'Bounce':
                $this->process_bounce($message);
                break;
            case 'Complaint':
                $this->process_complaint($message);
                break;
        }
        return 'notification processed';
    }

    protected function process_delivery($message)
    {
        RecordDeliveryJob::dispatch($message)
            ->onQueue(config('mail-tracker.tracker-queue'));
    }

    public function process_bounce($message)
    {
        RecordBounceJob::dispatch($message)
            ->onQueue(config('mail-tracker.tracker-queue'));
    }

    public function process_complaint($message)
    {
        RecordComplaintJob::dispatch($message)
            ->onQueue(config('mail-tracker.tracker-queue'));
    }
}
