WP Evernote
===========

WP Evernote is a Wordpress plugin which will generate posts
from notes in a public Evernote notebook.

***This plugin is currently in a testing state. We are working
on support for API keys and automatic scheduled updates.***

Debugging
=========

> Fatal error: Class 'OAuth' not found in .../wpevernote/evernote-sdk-php/lib/Evernote/Client.php on line 46`

You need to install the oauth module for php.

* pecl install oauth
* You should add "extension=oauth.so" to php.ini
