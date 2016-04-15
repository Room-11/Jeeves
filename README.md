Jeeves
======

Chat bot for StackOverflow. Playground for [amphp](https://github.com/amphp) libraries and just maybe some actual working bot will come out of it.

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

## Request flow for chat

### Log in to SE's OpenID

- Navigate to the openid login page https://openid.stackexchange.com/account/login
- Find the fkey value (hidden field)
- Log in using the fkey, username and password at https://openid.stackexchange.com/account/login/submit
- Navigate to http://stackoverflow.com/users/login?returnurl=stackoverflow.com%2f
- Log in using the fkey, username and password at http://stackoverflow.com/users/login?returnurl=stackoverflow.com%2f
- Go to a room e.g. http://chat.stackoverflow.com/rooms/11/php because it's the best...
- Get the fkey (again, I'm fairly certain by now it stands for fuckingkey)
- Get the websocket URL by making a POST request to http://chat.stackoverflow.com/ws-auth with the room id and the fkey

### Setup the websocket connection

- /ws-auth should return the websocket URI to connect to
