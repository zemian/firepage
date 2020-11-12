# Welcome to MarkNotes

MarkNotes is a single `index.php` page application for managing Markdown files.

* Project Home: https://github.com/zemian/marknotes
* Project Owner: Zemian Deng
* License: [The MIT License (MIT)](index.php?file=license.md)
* Release: [Notes](index.php?file=release.md)

If this is a live site, go to [Admin](index.php?admin) to manage the files!
(NOTE: This link will not work in GitHub project hosting for obviously reason: they don't support PHP!)

A live demo is available [here](https://zemiancodeplayground.000webhostapp.com/marknotes/index.php).

And some screenshots are available [here](https://zemian.github.io/2020/11/07/marknotes/).

## Features

* Single file `index.php` application.
* Easy copy and deploy `index.php` to any web folder.
* Clean and simple UI style with Markdown editing syntax highlight.
* Display all Markdown files with `readme.md` as default page.
* Support sub folders browsing up to 3 levels. Ignore all dot hidden folders.
* Web based Admin interface to manage Markdown files.
* Secure - Serve files from single directory only.
* Secure - Support Admin password.
* Configurable - Using optional `.marknotes.json` file.

## Getting Started

Copy the `index.php` file to a live web server's public folder. And that's it!

Or to try it locally in your system. Install PHP and then run the following:

    git clone https://github.com/zemian/marknotes
    cd marknotes
    
    php -S localhost:3000
    open http://localhost:3000

By default the web server will serve the project directory for all `*.md` files, and you 
can click on any file links listed to on menu to view them. Change the browser URL with `index.php?admin` to go into Admin page. In there you can manage all the Markdown files.

## Config Parameters

The application supports the following config parameters that you may override using a `.marknotes.json` file 
located where the `index.php` is. Or you may also specified config file using a server ENV variable
named `MARKNOTES_CONFIG`.

The Json file should contain a Json object. Below are the defualt values and you only need to specify the one
you want to override.

```
{
    "root_dir": "",
    "title": "Mark Notes",
    "admin_password": "",
    "root_menu_label": "Notes",
    "max_menu_levels": 2,
    "default_dir_name": "",
    "default_file_name": "readme.md",
    "file_extension_list": [".md"],
    "exclude_file_list": []
}
```

### Config Descriptions

* "root_dir": Directory where to read Markdown files. Empty means relative to where `index.php` is.
* "title": Use to display the HTML title and Admin logo text.
* "admin_password": Set to non empty to required password to enter into admin area.
* "root_menu_label": Set a value to be displayed as root menu label.
* "max_menu_levels": Max number of depth level to list for menu links (sub-folders).
* "default_dir_name": Specify the root dir for note files. Empty means relative to where `index.php` is.
* "default_file_name": Default page to load in a notes dir.
* "file_extension_list": Content file extensions allowed to be manage.
* "exclude_file_list": List of file or directory to exclude relative from `root_dir`.

## Admin Password

The default Admin password is **not** set. You may set the password in `admin_password` config parameter 
to non-empty value and it will prompt for login.

## Third Party Dependencies

### Embedded in `index.php`

* [parsedown](https://github.com/erusev/parsedown) A PHP parser to render Markdown file.
* [parsedown-extra](https://github.com/erusev/parsedown-extra) Support Markdown extra features.

### External Dependencies

For styling we use [Bulma CSS](https://unpkg.com/bulma). It's access through `unpkg.com` CDN directly.

We also use [CodeMirror](https://unpkg.com/codemirror) to enhance Editor and syntax highlight.
