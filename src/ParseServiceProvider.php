<?php

namespace LaraParse;

use Auth;
use Illuminate\Support\ServiceProvider;
use LaraParse\Auth\ParseUserProvider;
use LaraParse\Session\ParseSessionStorage;
use LaraParse\Subclasses\User;
use Parse\ParseClient;

class ParseServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        $this->registerConfig();
        $this->registerAuthProvider();
        $this->registerCustomValidation();
        $this->registerSubclasses();
        $this->registerRepositoryImplementations();
        $this->bootParseClient();
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        // Register our user registrar service
        $this->app->bind(
            'Illuminate\Contracts\Auth\Registrar',
            'LaraParse\Auth\Registrar'
        );

        // Register our custom commands
        $this->app->singleton('command.parse.subclass.make', function ($app) {
            return $app->make('LaraParse\Console\SubclassMakeCommand');
        });

        $this->app->singleton('command.parse.repository.make', function ($app) {
            return $app->make('LaraParse\Console\RepositoryMakeCommand');
        });

        $this->commands('command.parse.subclass.make', 'command.parse.repository.make');
    }

    private function registerConfig()
    {
        $configPath = __DIR__ . '/../config/parse.php';
        $this->publishes([$configPath => config_path('parse.php')], 'config');
        $this->mergeConfigFrom($configPath, 'parse');
    }

    private function registerAuthProvider()
    {
        Auth::provider('parse', function () {
            return new ParseUserProvider();
        });
    }

    private function registerSubclasses()
    {
        $subclasses = $this->app['config']->get('parse.subclasses');
        $foundUserSubclass = false;

        foreach ($subclasses as $subclass) {
            call_user_func("$subclass::registerSubclass");

            if ((new $subclass())->getClassName() == '_User') {
                $foundUserSubclass = true;
            }
        }

        if (!$foundUserSubclass) {
            User::registerSubclass();
        }
    }

    private function registerCustomValidation()
    {
        $this->app['validator']->extend('parse_user_unique', 'LaraParse\Validation\Validator@parseUserUnique');
    }

    public function registerRepositoryImplementations()
    {
        $repositories = $this->app['config']->get('parse.repositories');

        foreach ($repositories as $contract => $implementation) {
            $this->app->bind($implementation, $contract);
        }
    }

    private function bootParseClient()
    {
        $config = $this->app['config']->get('parse');

        // Init the parse client
        ParseClient::initialize($config['app_id'], $config['rest_key'], $config['master_key']);

        if (empty($config['server_url']) != true && empty($config['mount_path'] != true)) {
            ParseClient::setServerURL($config['server_url'], $config['mount_path']);
        }

        ParseClient::setStorage(new ParseSessionStorage($this->app['session']));
    }
}
