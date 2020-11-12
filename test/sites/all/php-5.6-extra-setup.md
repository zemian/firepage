## Compiling PHP 5.6.40 on MacOS 10.15.16 with extra options

```
download https://ftp.gnu.org/pub/gnu/libiconv/libiconv-1.16.tar.gz

cd libiconv
./configure --prefix=/usr/local
make 
sudo make install

ln -snf /usr/local/Cellar/openssl/1.0.2t /usr/local/opt/openssl
ln -snf /usr/local/Cellar/icu4c/64.2 /usr/local/opt/icu4c

perl -p -i -e 's/buffio.h/tidybuffio.h/'' ext/tidy/*.c

'./configure' '--prefix=/usr/local/php-5.6.40' '--enable-bcmath' '--enable-calendar' '--enable-dba' '--enable-exif' '--enable-ftp' '--enable-fpm' '--enable-mysqlnd' '--enable-pcntl' '--enable-phpdbg' '--enable-shmop' '--enable-soap' '--enable-sockets' '--enable-sysvmsg' '--enable-sysvsem' '--enable-sysvshm' '--enable-wddx' '--enable-zip' '--with-apxs2=/usr/local/opt/httpd/bin/apxs' '--with-bz2=/Library/Developer/CommandLineTools/SDKs/MacOSX10.14.sdk/usr' '--with-curl=/usr/local/opt/curl-openssl' '--with-freetype-dir=/usr/local/opt/freetype' '--with-gd' '--with-gettext=/usr/local/opt/gettext' '--with-gmp=/usr/local/opt/gmp' '--with-iconv-dir=/usr/local/iconv-1.16' '--with-zlib=/Library/Developer/CommandLineTools/SDKs/MacOSX10.14.sdk/usr' '--with-icu-dir=/usr/local/opt/icu4c' '--with-jpeg-dir=/usr/local/opt/jpeg' '--with-kerberos=/Library/Developer/CommandLineTools/SDKs/MacOSX10.14.sdk/usr' '--with-layout=GNU' '--with-ldap=/usr/local/opt/openldap' '--with-ldap-sasl=/Library/Developer/CommandLineTools/SDKs/MacOSX10.14.sdk/usr' '--with-libedit=/Library/Developer/CommandLineTools/SDKs/MacOSX10.14.sdk/usr' '--with-libxml-dir=/Library/Developer/CommandLineTools/SDKs/MacOSX10.14.sdk/usr' '--with-libzip' '--with-mcrypt=/usr/local/opt/mcrypt' '--with-mhash=/Library/Developer/CommandLineTools/SDKs/MacOSX10.14.sdk/usr' '--with-mysql-sock=/tmp/mysql.sock' '--with-mysqli=mysqlnd' '--with-mysql=mysqlnd' '--with-ndbm=/Library/Developer/CommandLineTools/SDKs/MacOSX10.14.sdk/usr' '--with-openssl=/usr/local/opt/openssl' '--with-pdo-dblib=/usr/local/opt/freetds' '--with-pdo-mysql=mysqlnd' '--with-pdo-odbc=unixODBC,/usr/local/opt/unixodbc' '--with-pdo-pgsql=/usr/local/opt/libpq' '--with-pdo-sqlite=/usr/local/opt/sqlite' '--with-pgsql=/usr/local/opt/libpq' '--with-pic' '--with-png-dir=/usr/local/opt/libpng' '--with-pspell=/usr/local/opt/aspell' '--with-sqlite3=/usr/local/opt/sqlite' '--with-tidy=/usr/local/opt/tidy-html5' '--with-unixODBC=/usr/local/opt/unixodbc' '--with-xmlrpc' '--with-xsl=/Library/Developer/CommandLineTools/SDKs/MacOSX10.14.sdk/usr' 'CC=clang' 'CPPFLAGS=-DU_USING_ICU_NAMESPACE=1'

perl -p -i -e 's/#define HAVE_OLD_READDIR_R 1/#define HAVE_POSIX_READDIR_R 1/' main/php_config.h

make clean
make
sudo make install
```

Stuck on this error :(

```
  "_sk_value", referenced from:
      _zif_openssl_x509_parse in openssl.o
      _zif_openssl_csr_new in openssl.o
      _zif_openssl_pkcs7_verify in openssl.o
      _php_openssl_parse_config in openssl.o
      _php_openssl_sockop_set_option in xp_ssl.o
      _capture_peer_certs in xp_ssl.o
ld: symbol(s) not found for architecture x86_64
clang: error: linker command failed with exit code 1 (use -v to see invocation)
make: *** [libs/libphp5.bundle] Error 1
```

## Break down the configuration options

```
./configure \
--prefix=/usr/local/php-5.6.40 \
--enable-bcmath \
--enable-calendar \
--enable-dba \
--enable-exif \
--enable-ftp \
--enable-fpm \
--enable-mysqlnd \
--enable-pcntl \
--enable-phpdbg \
--enable-shmop \
--enable-soap \
--enable-sockets \
--enable-sysvmsg \
--enable-sysvsem \
--enable-sysvshm \
--enable-wddx \
--enable-zip \

--with-gd \
--with-pic \
--with-xmlrpc \
--with-libzip \

--with-layout=GNU \
--with-mysqli=mysqlnd \
--with-mysql=mysqlnd \
--with-pdo-mysql=mysqlnd \
--with-mysql-sock=/tmp/mysql.sock \

--with-apxs2=/usr/local/opt/httpd/bin/apxs \
--with-curl=/usr/local/opt/curl-openssl \
--with-gettext=/usr/local/opt/gettext \
--with-gmp=/usr/local/opt/gmp \
--with-ldap=/usr/local/opt/openldap \
--with-mcrypt=/usr/local/opt/mcrypt \
--with-openssl=/usr/local/opt/openssl \
--with-pdo-dblib=/usr/local/opt/freetds \
--with-pdo-odbc=/usr/local/opt/unixodbc \
--with-pdo-pgsql=/usr/local/opt/libpq \
--with-pdo-sqlite=/usr/local/opt/sqlite \
--with-pgsql=/usr/local/opt/libpq \
--with-pspell=/usr/local/opt/aspell \
--with-sqlite3=/usr/local/opt/sqlite \
--with-tidy=/usr/local/opt/tidy-html5 \
--with-unixODBC=/usr/local/opt/unixodbc \

--with-kerberos=/Library/Developer/CommandLineTools/SDKs/MacOSX10.14.sdk/usr \
--with-zlib=/Library/Developer/CommandLineTools/SDKs/MacOSX10.14.sdk/usr \
--with-bz2=/Library/Developer/CommandLineTools/SDKs/MacOSX10.14.sdk/usr \
--with-ldap-sasl=/Library/Developer/CommandLineTools/SDKs/MacOSX10.14.sdk/usr \
--with-libedit=/Library/Developer/CommandLineTools/SDKs/MacOSX10.14.sdk/usr \
--with-libxml-dir=/Library/Developer/CommandLineTools/SDKs/MacOSX10.14.sdk/usr \
--with-mhash=/Library/Developer/CommandLineTools/SDKs/MacOSX10.14.sdk/usr \
--with-ndbm=/Library/Developer/CommandLineTools/SDKs/MacOSX10.14.sdk/usr \
--with-xsl=/Library/Developer/CommandLineTools/SDKs/MacOSX10.14.sdk/usr \

--with-iconv-dir=/usr/local/iconv-1.16 \
--with-freetype-dir=/usr/local/opt/freetype \
--with-icu-dir=/usr/local/opt/icu4c \
--with-jpeg-dir=/usr/local/opt/jpeg \
--with-png-dir=/usr/local/opt/libpng \
```

Result: Error

### Smaller options

```
./configure \
--prefix=/usr/local/php-5.6.40 \
--enable-bcmath \
--enable-calendar \
--enable-dba \
--enable-exif \
--enable-ftp \
--enable-fpm \
--enable-mysqlnd \
--enable-pcntl \
--enable-phpdbg \
--enable-shmop \
--enable-soap \
--enable-sockets \
--enable-sysvmsg \
--enable-sysvsem \
--enable-sysvshm \
--enable-wddx \
--enable-zip \
--with-gd \
--with-pic \
--with-xmlrpc \
--with-libzip \
--with-mysqli=mysqlnd \
--with-mysql=mysqlnd \
--with-pdo-mysql=mysqlnd \
--with-iconv=/usr/local/opt/libiconv \
--with-zlib=/usr/local/opt/zlib \
--with-icu=/usr/local/opt/icu4c \

perl -p -i -e 's/#define HAVE_OLD_READDIR_R 1/#define HAVE_POSIX_READDIR_R 1/' main/php_config.h
perl -p -i -e 's#EXTRA_LIBS = -lresolv -liconv -liconv#EXTRA_LIBS = -lresolv /usr/local/opt/libiconv/lib/libiconv.dylib#' Makefile
make clean
make
```

Error:
```
  "_libiconv_open", referenced from:
      _do_convert in gdkanji.o
      _zif_iconv_substr in iconv.o
      _zif_iconv_mime_encode in iconv.o
      _php_iconv_string in iconv.o
      __php_iconv_strlen in iconv.o
      __php_iconv_strpos in iconv.o
      __php_iconv_mime_decode in iconv.o
      ...
ld: symbol(s) not found for architecture x86_64
clang: error: linker command failed with exit code 1 (use -v to see invocation)
make: *** [sapi/cli/php] Error 1

```

### Min options that works

```
./configure \
--prefix=/usr/local/php-5.6.40 \
--enable-sockets \
--enable-fpm \
--with-mysqli=mysqlnd \
--with-mysql=mysqlnd \
--with-pdo-mysql \
--with-iconv=/usr/local/opt/libiconv \
--with-zlib=/usr/local/opt/zlib \
--with-apxs2=/usr/local/bin/apxs
```

### Try more options:

THIS WORKS!

Notes:
- --enable-zip will not compile - getpid() error

```
./configure \
--prefix=/usr/local/php-5.6.40 \
--enable-bcmath \
--enable-calendar \
--enable-dba \
--enable-exif \
--enable-ftp \
--enable-fpm \
--enable-mysqlnd \
--enable-pcntl \
--enable-phpdbg \
--enable-shmop \
--enable-soap \
--enable-sockets \
--enable-sysvmsg \
--enable-sysvsem \
--enable-sysvshm \
--enable-wddx \
--with-gd \
--with-pic \
--with-xmlrpc \
--with-libzip \
--with-layout=GNU \
--with-mysqli=mysqlnd \
--with-mysql=mysqlnd \
--with-pdo-mysql=mysqlnd \
--with-iconv=/usr/local/opt/libiconv \
--with-zlib=/usr/local/opt/zlib \
--with-apxs2=/usr/local/bin/apxs \

perl -p -i -e 's/#define HAVE_OLD_READDIR_R 1/#define HAVE_POSIX_READDIR_R 1/' main/php_config.h
perl -p -i -e 's#EXTRA_LIBS = -lresolv -liconv -liconv#EXTRA_LIBS = -lresolv /usr/local/opt/libiconv/lib/libiconv.dylib#' Makefile
make
```