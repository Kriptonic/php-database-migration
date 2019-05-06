# A PHP Database Migration Tool

I liked the simplicity of the `php-database-migration` tool created by `alwex`.
I have forked the repository to add configuration changes that make
integration into existing products easier, as well as providing more
flexibility to developers.

## Planned Features
I am currently focusing on the following features:
* ~~User defined directories~~
* ~~Support for multiple databases with separate migrations~~
* Better handling of remote migrations without local

## Getting Started

This section will be added to once the vision for the package
is more complete.

### Installation

todo

Note: The package has not been added to packagist so you aren't able to install via Composer yet.

### Configuration  

The package will store user environment configuration and migrations in the
`php_db_migration` folder relative to where the application is ran from.
If you wish to change directories used by the package create a new PHP file
where you want to run the application from. Place the following into the file:

```php
#!/usr/bin/php
<?php

require 'vendor/autoload.php';

$config = array(
    'working_path' => __DIR__ . '/custom_dir',
    'migration_path' => __DIR__ . '/custom_dir/migrations',
    'environment_path' => __DIR__ . '/custom_dir/environments'
);

$app = new Migrate\Manager($config);

// Add custom commands here.
// $app->add(new MyCustomCommand());

$app->run();

```

Adjust the `working_path`, `migration_path`, and `environment_path` fields to
suit your requirements. If you leave the `environment_path` and `migration_path`
empty then `environments` and `migrations` will be used respectively as defaults
within the `working_path` directory.

### Simple Usage

Run the program on the commandline from the project root `php bin/migrate`
or the custom runner you created if you changed the paths.
