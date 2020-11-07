# MarkNotes

MarkNotes is a single `index.php` page application for managing Markdown notes.

 * Project Home: https://github.com/zemian/marknotes
 * License: The MIT License (MIT)
 * Author: Zemian Deng
 * Date: 2020-11-04

## Getting Started 

To try it, run this:

	php -S localhost:3000
	open http://localhost:3000

By default the server will serve a directory named `notes` for all `*.md` files. 

The application supports the following URL query parameters:

* `?admin` - Go into Admin mode to manage the note files.
* `?notes_dir=mynotes` - Change the directory where to look for Markdown files.
* `?file=mynote.md` - View a note file directly.

NOTE: Because this is a single `index.php` application. You can easily copy it into any folder 
in your web server that contains Markdown files!

## Design Notes

We used [parsedown](https://github.com/erusev/parsedown) to render Markdown file. This library 
is embedded inside the `index.php` in order to keep the goal of single page application.

For styling we use [Bulma CSS](https://unpkg.com/bulma). If you don't want to have to have external internet
access at all, then simply download it and replace the `<link>` element in the `index.php` file.

We also use [CodeMirror](https://unpkg.com/codemirror) to enhance Editor and syntax highlight. Again, 
this will access external internet. But even if it's not able to load, the fall back HTML textarea will work
just fine.
