# Laravel Schema Exporter

[![License](https://poser.pugx.org/xethron/migrations-generator/license.png)](https://packagist.org/packages/xethron/migrations-generator)

A fork of [Xethron Migrations Generator](https://github.com/Xethron/migrations-generator) which exports your entire Schema as a single migration file. Currently some custom options and no support for foreign keys yet. This is currently used for a single specific database so options are limited/assumed, but will be improved in future releases. 

The purpose of this fork is to make it easier to port existing non-Laravel codebases to Laravel and to bootstrap the migration process from a clean starting point. 

The recommended way to install this is through composer:

```bash
composer require --dev "kylearch/schema-exporter"
```

In Laravel 5.5 the service providers will automatically get registered. 

In older versions of the framework edit `config/app.php` and add this to providers section:

```php
Way\Generators\GeneratorsServiceProvider::class,
KyleArch\SchemaExporter\SchemaExporterServiceProvider::class,
```
If you want this lib only for dev, you can add the following code to your `app/Providers/AppServiceProvider.php` file, within the `register()` method:

```php
public function register()
{
    if ($this->app->environment() !== 'production') {
        $this->app->register(\Way\Generators\GeneratorsServiceProvider::class);
        $this->app->register(\KyleArch\SchemaExporter\SchemaExporterServiceProvider::class);
    }
    // ...
}
```

## Usage

To generate a single migration representing your entire database schema, you need to have your database setup in Laravel's Config.

Run `php artisan export:schema` to create a migration for all the tables. Currently, tables that start with `tmp` are ignored, as well as a `migrations` table if it exists.

Run `php artisan help export:schema` for a list of options.

## Thank You

Thanks to Bernhard Breytenbach for his initial work on table-table migration generation, which was the basis for this package.

## Contributors

Bernhard Breytenbach ([@BBreyten](https://twitter.com/BBreyten))

Kyle Arch ([@kaarch](https://twitter.com/kaarch))

## License

The Laravel Migrations Generator is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT)
