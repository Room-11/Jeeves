Jeeves
======

Chat bot for StackOverflow. Uses [amphp](https://github.com/amphp) libraries for async magic sauce.

[![Build Status](https://travis-ci.org/Room-11/Jeeves.svg?branch=master)](https://travis-ci.org/Room-11/Jeeves)

## Requirements

1. PHP 7.0 or greater.
1. [`php_intl`](https://secure.php.net/manual/en/book.intl.php) PHP extension.
1. [`php_mbstring`](https://secure.php.net/manual/en/book.mbstring.php) PHP extension.
1. [`libxml`](https://secure.php.net/manual/en/book.libxml.php), version 2.7.8 or greater due to use of `LIBXML_HTML_NOIMPLIED` and `LIBXML_HTML_NODEFDTD `.

## Installation

1. Clone the project.
1. Copy `config/config.sample.yml` to `config/config.yml`.
1. Replace all configuration variables with your values.
1. Run the bot using `php ./cli/run.php`.

### JAAS (Jeeves as a Service)

If you want to run the bot as a systemd service:

1. Copy `config/jeeves.sample.service` to `/etc/systemd/system/jeeves.service`.
1. Replace the path with your installation location.
1. Make the `cli/run.php` file executable
1. If you want to service to automatically start run `systemctl enable jeeves`
1. Start the service using `systemctl start jeeves`

## Optional Dependencies

1. For true non-blocking execution:
    * Install the [`libevent`](https://pecl.php.net/package/libevent) PECL extension.
    * Install the [`php-uv`](https://github.com/bwoebi/php-uv) PHP extension OR the  [`ev`](https://pecl.php.net/package/ev) PECL extension.

## Documentation

Documentation is something other people do. Despite this, there is some information in the [wiki](https://github.com/Room-11/Jeeves/wiki)
 
## License
 
 The source code of this project is licensed under the [MIT license](https://opensource.org/licenses/mit-license.php).
