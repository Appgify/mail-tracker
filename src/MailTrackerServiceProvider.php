<?php

namespace ABCreche\MailTracker;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class MailTrackerServiceProvider extends ServiceProvider
{

    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        // Publish pieces
        $this->publishConfig();
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../database/migrations/'
            => database_path('migrations/')
        ], 'abcreche-mail-tracker-migrations');


        // Hook into the mailer
        Event::listen(MessageSending::class, function (MessageSending $event) {
            $tracker = new MailTracker;
            $tracker->messageSending($event);
        });
        Event::listen(MessageSent::class, function (MessageSent $mail) {
            $tracker = new MailTracker;
            $tracker->messageSent($mail);
        });

        // Install the routes
        $this->installRoutes();
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Publish the configuration files
     *
     * @return void
     */
    protected function publishConfig()
    {
        $this->publishes([
            __DIR__.'/../config/mail-tracker.php' => config_path('mail-tracker.php')
        ], 'config');
    }

    /**
     * Install the needed routes
     *
     * @return void
     */
    protected function installRoutes()
    {
        $config = $this->app['config']->get('mail-tracker.route', []);
        $config['namespace'] = 'ABCreche\MailTracker';

        Route::group($config, function () {
            Route::get('t/{uuid}', 'MailTrackerController@getT')->name('mailTracker_t');
            Route::get('l/{url}/{uuid}', 'MailTrackerController@getL')->name('mailTracker_l');
            Route::get('n', 'MailTrackerController@getN')->name('mailTracker_n');
            Route::post('sns', 'SNSController@callback')->name('mailTracker_SNS');
        });
    }
}
