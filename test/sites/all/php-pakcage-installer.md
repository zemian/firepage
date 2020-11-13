## Installing PHP, database and a web server

There are many pre-package installer that will install all three of these software together.

NOTE: Starting PHP 5.4, it has a built-in web server that's good for local dev! See https://www.php.net/manual/en/features.commandline.webserver.php

## XAMPP Setup

* Download from https://www.apachefriends.org/

* Error: "TempDir is not accessible" on Mac

> The `$cfg['TempDir'] (./tmp/)` is not accessible. phpMyAdmin is not able to cache templates and will be slow because of this

The default installation comes with `phpmyadmin` that show this error. To fix it:

	cd /Applications/XAMPP/xamppfiles/phpmyadmin
	mkdir tmp
	chmod 777 tmp

