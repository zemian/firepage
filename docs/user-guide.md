# User Guide

FirePage is a file based CMS application with just one single `index.php` file!

## Features

* Single file `index.php` application.
* Easy copy and deploy `index.php` to any web folder.
* Clean and simple UI style with Markdown editing syntax highlight.
* Display all Markdown files with `readme.md` as default page.
* Easy menu links to files with sub folders support.
* Admin - Web based interface to create, edit and delete Markdown files.
* Secure - Support Admin password.
* Secure - Serve files from single directory only.
* Secure - Auto ignore all dot hidden folders and files.
* Configurable - Using optional `.firepage.json` config file.

## Getting Started

Copy the `index.php` file to a live web server's public folder. And that's it!

Or to try it locally in your system. Install PHP and then run the following:

    git clone https://github.com/zemian/firepage
    cd firepage
    
    php -S localhost:3000
    open http://localhost:3000

By default the web server will serve the project directory for all `*.md` files, and you 
can click on any file links listed to on menu to view them. Change the browser URL with `index.php?admin` to go into Admin page. In there you can manage all the Markdown files.

## Core Config Parameters

The application supports the following config parameters that you may override using a `.firepage.json` file 
located where the `index.php` is. Or you may also specified config file using a server ENV variable
named `FIREPAGE_CONFIG`.

The Json file should contain a Json object. Below are the default values if you omit them.

```
{
    "root_dir": "",
    "title": "FirePage",
    "disable_admin": false,
    "admin_password": "",
    "root_menu_label": "Site",
    "max_menu_levels": 2,
    "default_dir_name": "",
    "default_file_name": "home.html",
    "default_admin_file_name": "admin-home.html",
    "file_extension_list": [".html", ".txt", ".json"],
    "exclude_file_list": ["plugins", "themes", "admin-home.html"],
    "pretty_file_to_label": true
}
```

### Core Config Parameter Descriptions

* "root_dir": Directory where to read Markdown files. Empty means relative to where `index.php` is.
* "title": Use to display the HTML title and Admin logo text.
* "disable_admin": Set to true if you do not admin interface at all.
* "admin_password": Set to non empty to required password to enter into admin area.
* "root_menu_label": Set a value to be displayed as root menu label.
* "max_menu_levels": Max number of depth level to list for menu links (sub-folders).
* "default_dir_name": Specify the root dir for note files. Empty means relative to where `index.php` is.
* "default_file_name": Default page to load in a notes dir.
* "file_extension_list": Content file extensions allowed to be manage.
* "exclude_file_list": List of file or directory to exclude relative from `root_dir`. If set, these won't even display
  in the admin interface.
* "pretty_file_to_label": If set to true, it convert file/dir name to a pretty link label.

### Advance Config Parameters

Below config parameters will have default values of `NULL` if omitted.

* "menu_links": Manually set a menu links. Omit this entry and the menu links will be auto generated based on 
  dirs/files listing. Note that the `child_menu_links` can contain a list of `menu_links` with the same structure 
  (recursive for nested menus).
    ```  
      "menu_links": {
        "menu_label": "Notes",
        "menu_name": "",
        "menu_order": 1,
        "links": [
          { "order": 1, "label" : "Home", "page": "readme.md" },
          { "order": 2, "label" : "Markdown Sample", "page": "sample.md" },
          { "order": 3, "label" : "Release Notes", "page": "release.md" },
          { "order": 4, "label" : "License", "page": "license.md" }
          { "order": 5, "label" : "Search", "url": "https://www.google.com" }
        ],
        "child_menu_links": []
      }
    ```
* "files_to_menu_links": Remap file or dir name in generated menu_links with better label or hide it completely.
  If `hide` attribute is set, then it only hide the file from menu link, but it will still shows in Admin interface. 
  If the entry match to a folder (menu dir path), then an the entire folder will be hidden.
    ```
      "files_to_menu_links": {
        "readme.md" : { "order": 0, "label" : "Home" },
        "sample.md" : { "order": 1, "label" : "Markdown Sample" },
        "release.md" : { "order":2, "label" : "Release Notes" },
        "license.md" : { "order": 3, "label" : "License" },
        "todo.md" : { "hide": true },
        "temp" : { "hide": true }
      }
    ```
  
* "theme": Use a custom theme located under `themes/<theme-name>` folder.

## Admin Password

The default Admin password is **not** set. You may set the password in `admin_password` config parameter 
to non-empty value and it will prompt for login.

## Third Party Dependencies

The `index.php` file itself does not include any dependencies. The UI it uses online CDN library for the styling.
If you need any extra functionalities, you may extend FirePage with `plugins` and `themes`.

### Default External Dependencies

For styling we use [Bulma CSS](https://unpkg.com/bulma). It's access through `unpkg.com` CDN directly.

We also use [CodeMirror](https://unpkg.com/codemirror) to enhance Editor and syntax highlight.
