<?php

namespace KyleArch\MigrationGenerator;

use Illuminate\Support\ServiceProvider;

class MigrationGeneratorServiceProvider extends ServiceProvider
{

    /**
     * Indicates if loading of the provider is deferred.
     * @var bool
     */
    protected $defer = false;

    /**
     * Register the service provider.
     * @return void
     */
    public function register()
    {
        $this->app->singleton('export.schema', function ($app) {
            return new MigrateGenerateCommand($app->make('Way\Generators\Generator'), $app->make('Way\Generators\Filesystem\Filesystem'), $app->make('Way\Generators\Compilers\TemplateCompiler'), $app->make('migration.repository'), $app->make('config'));
        });

        $this->commands('export.schema');

        // Bind the Repository Interface to $app['migrations.repository']
        $this->app->bind('Illuminate\Database\Migrations\MigrationRepositoryInterface', function ($app) {
            return $app['migration.repository'];
        });
    }

    /**
     * Bootstrap the application events.
     * @return void
     */
    public function boot()
    {
    }

    /**
     * Get the services provided by the provider.
     * @return array
     */
    public function provides()
    {
        return [];
    }

}
