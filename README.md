# Cron
**Job scheduling for the PHP Fat-Free Framework**

This plugin for [Fat-Free Framework](http://github.com/bcosca/fatfree) helps you control job scheduling directly from your web app.

* [Installation](#installation)
* [Operation and basic usage](#operation-and-basic-usage)
* [Schedule format](#schedule-format)
    * [Crontab](#crontab)
    * [Examples](#examples)
    * [Presets](#presets)
* [Options](#options)
    * [Logging](#logging)
    * [Web interface](#web-interface)
    * [Script path](#script-path)
    * [PHP binary path](#php-binary-path)
* [Ini configuration](#ini-configuration)
* [Asynchronicity](#asynchronicity)
* [API](#api)
* [Potential improvements](#potential-improvements)

## Installation

To install this plugin, just copy the `lib/cron.php` file into your `lib/` or your `AUTOLOAD` folder.

## Operation and basic usage

This plugin provides your app with an external interface to run scheduled jobs.
The interface consists in 2 routes automatically added to your app:

* `GET /cron` checks for due jobs and executes them
* `GET /cron/@job` triggers a specific job

By default, this interface is accessible in CLI mode only and is meant to be called by the server job scheduler:

1. Unix cron or Windows Task Scheduler calls `index.php /cron` every minute (or at a slower rate).
2. `index.php /cron` checks for due jobs at that time and executes them, asynchronously if possible.

### Step 1:

Configure your server job scheduler so that it calls `php index.php /cron` every minute.

Here's how to do it on a \*nix server, assuming that your application resides in `/path/to/app/index.php`:

* create a file named, for example, *mycrontab*, containing the following line:

```cron
* * * * * cd /path/to/app; php index.php /cron
```

* configure cron with it, using the following command:

```bash
crontab mycrontab
```

**NB:** depending on your hosting, you may need to ask your provider to perform that step.

### Step 2:

Instantiate the `Cron` class and define the list and frequency of jobs with the following commands:

```php
//index.php
$f3=require('lib/base.php');
...
$cron=Cron::instance();
$cron->set('Job1','App->job1','@daily');
$cron->set('Job2','App->job2','@weekly');
...
$f3->run();
```

### That's it!

*Job1* will run every day and *Job2* every week.


## Schedule format

### Crontab

Each job is scheduled using the (nearly) standard crontab format,
which consists of 5 fields separated by spaces:

```
 * * * * *
 │ │ │ │ │
 │ │ │ │ │
 │ │ │ │ └───── day of week (0 - 6) (0 to 6 are Sunday to Saturday)
 │ │ │ └────────── month (1 - 12)
 │ │ └─────────────── day of month (1 - 31)
 │ └──────────────────── hour (0 - 23)
 └───────────────────────── min (0 - 59)
```

Each field may be:

* a `*`, meaning *any* value
* a number: `3`
* a range: `1-4` (equals `1,2,3,4`)
* a list of numbers or ranges: `1-4,6,8-10`

Ranges have a default step value of 1, which can be adjusted using a `/`:

* `1-6/2` is the same as `1,3,5`
* `*/3` is the same as `1,4,7,10` (month column)

### Examples

```php
$cron->set('Job1','App->job1','* * * * *'); // runs every minute
$cron->set('Job2','App->job2','*/5 * * * *'); // runs every 5 minutes
$cron->set('Job3','App->job3','0 8 * * 1'); // runs every Monday at 08:00
$cron->set('Job4','App->job4','0 4 10 * *'); // runs the 10th of each month at 04:00
$cron->set('Job5','App->job5','0 0 * */3 *'); // runs on a quarterly basis
```

### Presets

For easier reading, it is possible to define presets:

```php
$cron->preset('weekend','0 8 * * 6'); // runs every Saturday at 08:00
$cron->preset('lunch','0 12 * * *'); // runs every day at 12:00
$cron->set('Job6','App->job6','@weekend');
$cron->set('Job7','App->job7','@lunch');
```

The following presets are defined by default:

* `@yearly` or `@annually` <=> `0 0 1 1 *`
* `@monthly` <=> `0 0 1 * *`
* `@weekly` <=> `0 0 * * 0`
* `@daily` <=> `0 0 * * *`
* `@hourly` <=> `0 * * * *`

## Options

### Logging

If you set `$cron->log=TRUE`, every successfully executed job will be logged in a `cron.log` file located in the `TEMP` folder.

### Web interface

By default, the routes `GET /cron` and `GET cron/@job` are available in CLI mode only, which means that an HTTP request to them will throw a 404 error.

You can enable web routes by setting `$cron->web=TRUE`.

In that case, `/cron` can be triggered via HTTP on a periodic basis, for example by your web app, or by a web cron service, or even by your own crontab:

```cron
* * * * * curl http://mydomain.tld/cron
```

### Script path

By default, the script called asynchronously is `index.php` located in the current working directory.

You may need to tweak this value if:

* your web root differs from your app root (e.g: `index.php` resides in `www/` and starts with `chdir('..')`)
* all your scheduling is handled in a separate file (e.g: `cron.php` instead of `index.php`)

Examples:

```php
$cron->script='htdocs/index.php';//relative to app root
$cron->script=__DIR__.'/cron.php';//absolute path
```

### PHP binary path

By default, the PHP binary used to trigger asynchronous job executions is either `php` or `php-cli` (smart guess).

You may need to tweak this value if none of these values correspond to an executable PHP CLI binary
or if they are not in the path.

Example:

```php
$cron->binary('/home/myphp-cli');
```

## Ini configuration

Configuration is possible from within an .ini file, using the `CRON` variable. E.g:

```ini
[CRON]
log = TRUE
web = FALSE
script = cron.php

[CRON.presets]
lunch = 0 12 * * *

[CRON.jobs]
Job1 = App->job1, * * * * *
Job2 = App->job2, @lunch
Job3 = App->job3, @hourly
```

**IMPORTANT:** Don't forget to instantiate the class before running your app:

```php
//index.php
$f3=require('lib/base.php')
$f3->config('cron.ini');
Cron::instance(); // <--- MANDATORY
$f3->run();
```

## Asynchronicity

If you want tasks to be run asynchronously, you'll need:
* `exec()` to be enabled on your hosting
* the [script path](#script-path) to be configured correctly
* the [PHP CLI binary](#php-binary-path) to be executable and in the path of your hosting user

**NB1:** The plugin will detect automatically if jobs can be run asynchronously.
If not, jobs will be executed synchronously, which may take longer and add a risk of queue loss in case of a job failure.

**NB2:** Asynchronicity is not available on Windows at the moment. File an issue if you need it.

## API

```php
$cron = Cron::instance();
```

### log

**Logging of successfully executed jobs (default=FALSE)**

```php
$cron->log=TRUE;// enable logging
```

### web

**Web interface (default=FALSE)**

```php
$cron->web=TRUE;// enable web interface
```

### script

**Path of the script to call asynchronously (default='index.php')**

Defaults to `index.php` in the current working directory.

```php
$cron->script='htdocs/index.php';//relative to app root
$cron->script=__DIR__.'/cron.php';//absolute path
```

### clipath

**Alias for script [deprecated]**

### binary

**Path of the PHP CLI binary (default='php' or 'php-cli')**

```php
echo $cron->binary;// php
```

### binary( $path )

**Set PHP CLI binary path**

```php
$cron->binary('/home/myphp-cli');
```

### silent

**Silent mode (default=TRUE)**

Disable silent mode if you want the script to output the list of executed jobs.

```php
$cron->silent=FALSE;
```

### set( $job, $handler, $expr )

**Schedule a job**

```php
$cron->set('Job1','App->job1','@daily'); // runs daily
$cron->set('Job2','App->job2','*/5 * * * *'); // runs every 5 minutes
$cron->set('Job3','App->job3','0 8 * * 1'); // runs every Monday at 08:00
```

**NB:** Valid characters for job names are alphanumeric characters and hyphens.

### preset( $name, $expr )

**Define a schedule preset**

```php
$cron->preset('weekend','0 8 * * 6'); // runs every Saturday at 08:00
$cron->preset('lunch','0 12 * * *'); // runs every day at 12:00
```

**NB:** Valid characters for job names are alphanumeric characters.

### isDue( $job, $time )

**Returns TRUE if the requested job is due at the given time**

```php
$cron->isDue('Job3',time()); // returns TRUE if Job3 is due now
```

### execute( $job, $async=TRUE )

**Execute a job**

```php
$cron->execute('Job2',FALSE); // executes Job2 synchronously
```

### run( $time=NULL, $async=TRUE )

**Run scheduler, i.e executes all due jobs at a given time**

```php
$cron->run(strtotime('yesterday midnight'));
// run asynchronously all jobs due yesterday at midnight
```

## Potential improvements

* Enable asynchronous execution on Windows.

