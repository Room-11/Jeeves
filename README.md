Jeeves
======

Chat bot for StackOverflow. Uses [amphp](https://github.com/amphp) libraries for async magic sauce.

[![Build Status](https://travis-ci.org/Room-11/Jeeves.svg?branch=master)](https://travis-ci.org/Room-11/Jeeves)

## Installation

1. Clone the project.
1. Copy `config/config.sample.yml` to `config/config.yml`.
1. Replace all configuration variables with your values.
1. Run the bot using `php ./cli/run.php`.

### JAAS (Jeeves as a Service)

If you want to run the bot as a systemd service:

1. Copy `config/jeeves.sample.service` to `config/jeeves.service`.
1. Replace the path with your installation location.
1. Symlink the service to your systemd directory
1. Make the `cli/run.php` file executable
1. Start the service using `systemctl start jeeves`

## Documentation

Documentation is something other people do. Despite this, there is some information in the [wiki](https://github.com/Room-11/Jeeves/wiki) 
