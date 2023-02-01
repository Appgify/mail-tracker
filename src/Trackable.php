<?php
namespace ABCreche\MailTracker;

trait Trackable
{
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

    public function withoutTracking()
    {
        $this->withSwiftMessage(function ($message) {
            $message->getHeaders()->addTextHeader('X-No-Track', "false");
        });

        return $this;
    }

    public function witTracking()
    {
        $this->withSwiftMessage(function ($message) {
            $message->getHeaders()->addTextHeader('X-Track', "true");
        });

        return $this;
    }

    public function trackLinks()
    {
        $this->withSwiftMessage(function ($message) {
            $message->getHeaders()->addTextHeader('X-Track-Links', "true");
        });

        return $this;
    }

    public function trackOpens()
    {
        $this->withSwiftMessage(function ($message) {
            $message->getHeaders()->addTextHeader('X-Track-Opens', "true");
        });

        return $this;
    }

    public function logBody()
    {
        $this->withSwiftMessage(function ($message) {
            $message->getHeaders()->addTextHeader('X-LogBody', "true");
        });

        return $this;
    }

    public function model($model, $tag = null)
    {
        $this->withSwiftMessage(function ($message) use ($model, $tag) {
            $message->getHeaders()->addTextHeader('X-Model-ID', $model->getKey());
            $message->getHeaders()->addTextHeader('X-Model-Type', get_class($model));
            if ($tag) {
                $message->getHeaders()->addTextHeader('X-Model-Tag', $tag);
            }
        });

        return $this;
    }

    public function addHeader($key, $value)
    {
        $this->withSwiftMessage(function ($message) use ($key, $value) {
            $message->getHeaders()->addTextHeader($key, $value);
        });

        return $this;
    }
}
