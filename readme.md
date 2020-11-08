# Welcome to MarkNotes

MarkNotes is a single `index.php` page application for managing Markdown notes.

 * Project Home: https://github.com/zemian/marknotes
 * License: The MIT License (MIT)
 * Author: Zemian Deng
 * Date: 2020-11-04

If this is a live site, go to [index.php?admin](index.php?admin) to manage the files!
(NOTE: This link will not work in GitHub project hosting for obviously reason: they don't support PHP!)

If you are looking for a live demo, try this <https://zemiancodeplayground.000webhostapp.com/marknotes/index.php>

## Features

* Single file `index.php` application.
* Easy copy and deploy `index.php` to any web folder.
* Clean and simple UI style with Markdown editing syntax highlight.
* Display all Markdown files with `readme.md` as default page.
* Support sub folders browsing up to 3 levels. Ignore all dot hidden folders.
* Web based Admin interface to manage Markdown files.
* Secure - never serve files outside where `index.php` is.
* Secure - support Admin password.

## Getting Started

Copy the `index.php` file to a live web server's public folder. And that's it!

Or to try it locally in your system, install PHP and then run the following:

	php -S localhost:3000
	open http://localhost:3000

By default the web server will serve the project directory for all `*.md` files, and you 
can click on any file link listed to view them. Change the browser URL with `index.php?admin`
to go into Admin page. In there you can manage all the Markdown files.

## Config Parameters 

There are few config parameters that you can easily change on top of the `index.php` file. 

```
$config = array(
    'title' => 'MarkNotes',        // Use to display the HTML title and Admin logo text.
    'admin_password' => '',        // Password to enter into admin area.
    'max_menu_levels' => 3,        // Max number of depth level to list for menu links (sub-folders).
    'default_ext' => '.md',        // File extension to manage. All else are ignore.
    'default_notes_dir' => '',     // Specify the root dir for note files. Blank means current dir.
    'default_note' => 'readme.md', // Default page to load in a notes dir.
    'root_menu_label' => ''        // Set a value to be displayed as root menu label
);
```

Or you may override any of these config parameters with a `.marknotes.json` file located where 
the `index.php` is. The Json file should contain a Json object with attributes matching to config
parameter names above. For example:

```
{
    "title": "My Project",
    "admin_password": "mysecret",
    "max_menu_levels": 2,
    "default_ext": ".markdown",
    "default_notes_dir": "docs",
    "default_note": "home.md",
    "root_menu_label": "DOCS"
}
``` 

## Admin Password

The default Admin password is not set and the Admin page is NOT secured. You may set the password 
in `admin_password` config parameter to require a login prompt.

## Design Notes

We used PHP [parsedown](https://github.com/erusev/parsedown) to render Markdown file. This library 
is embedded inside the `index.php` in order to keep the goal of a single page application.

For styling we use [Bulma CSS](https://unpkg.com/bulma). It's access through `unpkg.com` CDN directly.
If you don't want to have to have external internet access dependency, then simply download it and 
replace the `<link>` tag element in the `index.php` file.

We also use [CodeMirror](https://unpkg.com/codemirror) to enhance Editor and syntax highlight.
