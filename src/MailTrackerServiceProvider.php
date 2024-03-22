<?php

namespace ABCreche\MailTracker;

use Mail;
use Illuminate\Support\Arr;
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
        $this->registerSwiftPlugin();

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
     * Register the Swift plugin
     *
     * @return void
     */
    protected function registerSwiftPlugin()
    {
        $this->app['mailer']->getSymfonyTransport()->registerPlugin(new MailTracker());
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
