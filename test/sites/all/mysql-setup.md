See [MySQL Setup](https://github.com/zemian/learn-mysql) for details on general setup of the database.

## MySQL PHP Drivers

There are two main drivers starting PHP 5.1: mysqli or pdo_mysql. There is a third API called "ext/mysql" which is deprecated in PHP 5.5.

See https://dev.mysql.com/doc/apis-php/en/apis-php-mysqlinfo.api.choosing.html

Use pod_mysql if you want database portibility in code, and it only support OO API. The pdo_mysql supports both procedural and OO API, but it is only used for mysql specific.

### About mysqlnd (MySQL Native Driver)

Starting PHP 5.3.0, there is new native C library called "mysqlnd" can be used instead of the old MySQL Client (`libmysqlclient`). It's easier to compile and it provide more MySQL specific features.

See https://dev.mysql.com/doc/apis-php/en/apis-php-mysqlnd.overview.html

NOTE: Starting PHP 5.4 the `mysqlnd` is used by default for pdo_mysql now.

PHP configure option: `--with-mysqli=mysqlnd` and `--with-pdo-mysql`

## PHP 5.6 and MySQL 8 Error - character-set

```
Warning: mysql_connect(): Server sent charset (255) unknown to the client. Please, report to the developers in /Users/zedeng/src/zemian/learn-php/www/php-app/dbtest-old.php on line 18
```

To fix this, change your database encoding from `utf8mb4` to `utf8` ON the server `my.cnf` config file!

```	
# For PHP 5.6 support, we will default character-set to utf8 insetad of utf8mb4

[client]
default-character-set=utf8
 
[mysql]
default-character-set=utf8
 
[mysqld]
collation-server = utf8_unicode_ci
character-set-server = utf8
```

NOTE: Run `mysql --help` to see where `my.cnf` is loaded.

Ref: https://thisinterestsme.com/charset-255-unknown-mysql/

## PHP 5.6 and MySQL 8 Error - password

> When running a PHP version before 7.1.16, or PHP 7.2 before 7.2.4, set MySQL 8 Server's default password plugin to mysql_native_password or else you will see errors similar to The server requested authentication method unknown to the client [caching_sha2_password] even when caching_sha2_password is not used. 

```
Warning: mysql_connect(): The server requested authentication method unknown to the client [caching_sha2_password] in /Users/zedeng/src/zemian/learn-php/php/dbtest-old.php on line 18`
```

In the DB server `my.cnf` config file, add the following:

```
# For PHP 5.6 support, we will default older user password auth method
default-authentication-plugin=mysql_native_password
```

## Backup and Restore `learnphpdb`

```
# Backup
mysqldump --single-transaction --quick --no-autocommit --extended-insert=false -u root learnphpdb > learnphpdb-`date +%s`-dump.sql

# Restore
mysql -f -u root learnphpdb < learnphpdb-<date>-dump.sql
```
