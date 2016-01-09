Jeeves
======

Chat bot for StackOverflow. Playground for [amphp](https://github.com/amphp) libraries and just maybe some actual working bot will come out of it.

## Installation

1. Clone the project
2. Copy init.example.php to init.whatever.php
3. Change the init include in init.deployment.php
4. Run the bot using `php ./cli/run.php`

## Request flow for chat

### Log in to SE's OpenID

- Navigate to the openid login page https://openid.stackexchange.com/account/login
- Find the fkey value (hidden field)
- Log in using the fkey, username and password at https://openid.stackexchange.com/account/login/submit
- Get the websocket URL by making a POST request to http://chat.stackoverflow.com/ws-auth with the room id and the fkey
