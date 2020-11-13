## Ensure `display_errors` flag is On. You can do this in three ways:

1. Try to add this directly in `.php` file for debugging:

	```
	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);
	error_reporting(E_ALL | E_STRICT);
	```

2. Or add the same in `php.ini` globally. It would require web server (Apache) restart!

3. Or your application might have a configuration file that overrides these. (eg: Joomla's `configuration.php` file.)

## Monitor the web server error log

For example with Apahce, run `tail -f /usr/local/var/log/httpd/error_log`

## Ensure your `php.ini` is clean and consistent

If you setting up multiple PHP env, ensure you used the same setting files. Check `phpinfo` page and verify if needed. Setting such as `short_open_tag` would have different values based on env, and if application is hardcoded to depend on this feature, you likely will get blank screen!

## Where is php.ini?

Run `phpinfo.php` and you will see where the `.ini` file is located.

For example, PHP 5.6 is at `/usr/local/etc/php/5.6/php.ini`

## Ensure `date.timezone` is set.

This value can be set in `php.ini` file globally. For example:

```
date.timezone = 'UTC'
```

## Debugging Live PHP Code

* Learn the PHP error display flag.
* Learn your own application logging system.
* Use the PHP `debug_backtrace()`. For example:	

    ```
    file_put_contents('logs/debug.log', date("Y-m-d H:i:s ") . var_export(debug_backtrace(), true) . PHP_EOL);
    ```
