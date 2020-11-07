# Welcome to MarkNotes

MarkNotes is a single `index.php` page application for managing Markdown notes.

 * Project Home: https://github.com/zemian/marknotes
 * License: The MIT License (MIT)
 * Author: Zemian Deng
 * Date: 2020-11-04

Go to [Admin](index.php?admin) mode to manage them!

## Features

* Single file `index.php` application.
* Easy copy and deploy `index.php` to any web folder.
* Clean and simple UI style with Markdown editing syntax highlight.
* Display all Markdown files with `readme.md` as default page.
* Support sub folders browsing up to 3 levels.
* Web based Admin interface to manage Markdown files.
* Secure - never serve files outside where `index.php` is.

## Getting Started

To try it, run this:

	php -S localhost:3000
	open http://localhost:3000

By default the server will serve a directory named `notes` for all `*.md` files. 

The application supports the following URL query parameters:

* `?admin` - Go into Admin mode to manage the note files.
* `?notes_dir=mynotes` - Change the directory where to look for Markdown files.
* `?file=mynote.md` - View a note file directly.

## Design Notes

We used [parsedown](https://github.com/erusev/parsedown) to render Markdown file. This library 
is embedded inside the `index.php` in order to keep the goal of single page application.

For styling we use [Bulma CSS](https://unpkg.com/bulma). If you don't want to have to have external internet
access at all, then simply download it and replace the `<link>` element in the `index.php` file.

We also use [CodeMirror](https://unpkg.com/codemirror) to enhance Editor and syntax highlight. Again, 
this will access external internet. But even if it's not able to load, the fall back HTML textarea will work
just fine.
