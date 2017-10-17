Jeeves
======

Chat bot for StackOverflow. Uses [amphp](https://github.com/amphp) libraries for async magic sauce.

[![Build Status](https://travis-ci.org/Room-11/Jeeves.svg?branch=master)](https://travis-ci.org/Room-11/Jeeves)
![Badge Of Shame](https://blame.daverandom.com/Room-11/Jeeves)

## Requirements

* PHP 7.1 or greater.
* [`php_intl`](https://secure.php.net/manual/en/book.intl.php) PHP extension.
* [`php_mbstring`](https://secure.php.net/manual/en/book.mbstring.php) PHP extension.
* [`libxml`](https://secure.php.net/manual/en/book.libxml.php), version 2.7.8 or greater due to use of `LIBXML_HTML_NOIMPLIED` and `LIBXML_HTML_NODEFDTD `.

## Installation

1. Clone the project.
1. Copy `config/config.sample.yml` to `config/config.yml`.
1. Replace all configuration variables with your values.
1. Run the bot using `php ./bin/jeeves`.

### JAAS (Jeeves as a Service)

If you want to run the bot as a systemd service:

1. Copy `config/jeeves.sample.service` to `/etc/systemd/system/jeeves.service`.
1. Replace the path with your installation location.
1. Make sure the `bin/jeeves` file is executable
1. If you want to service to automatically start run `systemctl enable jeeves`
1. Start the service using `systemctl start jeeves`

## Optional Dependencies

* For true non-blocking execution, install one of the following:
    * [`libevent`](https://pecl.php.net/package/libevent) PECL extension.
    * [`ev`](https://pecl.php.net/package/ev) PECL extension
    * [`php-uv`](https://github.com/bwoebi/php-uv) PHP extension.

## Documentation

Documentation is something other people do. Despite this, there is some information in the [wiki](https://github.com/Room-11/Jeeves/wiki)

## License

 The source code of this project is licensed under the [MIT license](https://opensource.org/licenses/mit-license.php).
