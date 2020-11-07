# MarkNotes

MarkNotes is a single `index.php` page application for viewing Markdown notes.

To try it, run this:

	php -S localhost:3000
	open http://localhost:3000

Project Owner: Zemian Deng

## Design Notes

We used [parsedown](https://github.com/erusev/parsedown) to render Markdown file. This library 
is embedded inside the `index.php` in order to keep the goal of single page application.

For styling we use [Bulma CSS](https://unpkg.com/bulma). If you don't want to have to have external internet
access at all, then simply download it and replace the `<link>` element in the `index.php` file.
