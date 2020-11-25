# FirePage User Guide

FirePage is a file based CMS application with just one single `index.php` file!

The design and implementation of FirePage is very simple: it serve a directory full of content files as a website. 
It provide a quick website with auto generated navigation menu links to all the files. The site can also be
expertly crafted by developers to customize the look and feel with a theme. Or developers can add or change 
functionality of the application with plugins. See [Developer Guide](developer-guide.md) for more.

After the site is deployed online, FirePage also has a basic Admin interface where you can manage your content pages
online.

## Getting Started

The FirePage requires PHP version `7.4` or higher.

### Using a Web Server Application

To make a website, start a web server application and locate your `DocumentRoot` or `<site>` directory where 
it serves the the content files. (For example: for [Apache HTTPD web server](http://httpd.apache.org/), the `<site>` 
directory is usually located in `/usr/var/www`.) Also setup your web server to support PHP scripts.

Then the next step is simply copy the `index.php` file from [FirePage](https://github.com/zemian/firepage/releases) 
project to the `<site>` folder. And that's it!

Now open a browser url to the web server host. (For example: <http://localhost/index.php>). You should now see 
your site alive with FirePage default theme!

### Using the PHP built-in Web Server

You may also get FirePage up and running with a few commands in your Terminal.
 
For example:

```
cd <site>
php -S localhost:3000
open http://localhost:3000

# Or you open the Admin interface
open http://localhost:3000?admin
```

## Using FirePage

FirePage will able to manage `.html`, `.txt` and `.json` files by default. But it can be extends with `plugins` 
to support more file types. The UI look can be also be customizable with a `theme`. For example, the FirePage
project comes with `markdown` plugin that let you quickly generate web pages using simple 
[Markdown](https://www.markdownguide.org/) format. It also comes with a `docs` theme that looks great 
for building a documentation or company informational site.

## Configuration File

The FirePage application has reasonable default values, but you can override them using an external JSON 
file called `.firepage.json`. You can add this config file next to the `index.php` and it 
will be automatically detected. Or you may also specified a web server ENV variable named `FIREPAGE_CONFIG` to 
point to a `.firepage.json` file located anywhere in your file system.

### Core Config Parameters

The `.firepage.json` file should contain a JSON object, and you can add property to override any value. 
All of the config parameters are optional, and it has a default value set if you do not specify it in the file.

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

* "root_dir": A directory path to where all the content pages are located. Empty means 
  is set to where the `index.php` is located. You can choose a directory outside of `<site>` path!
* "title": Use to display the website title and logo text.
* "disable_admin": Set to true if you do not want the online Admin interface at all.
* "admin_password": Set to non empty to required password to enter into Admin interface.
* "root_menu_label": Set a value to be displayed as root menu label. Can be set to empty to display nothing.
* "max_menu_levels": Max number of sub directories depth to list for menu links.
* "default_dir_name": Specify a directory within `root_dir` to be the default root directory. Empty means 
  it will be directory where `index.php` is.
* "default_file_name": Default page to load in a directory. A Home page.
* "file_extension_list": Content file extensions list allowed to be manage. All other unlisted will be ignored.
  Note that a `plugin` might add more new file extensions to this list.
* "exclude_file_list": List of file or directory to be excluded from `root_dir`. If set, these will not be
  displayed on website, but it will still appear in the Admin interface for editing.
* "pretty_file_to_label": If set to true, it convert file/dir name to a pretty link label.

### Advance Config Parameters

Below listed config parameters will have a default values of `NULL` value in the code if they are omitted in 
the configuration file.

* "logo_url": Set a image url for your site logo. It will be displayed on the top navbar.
  
* "files_to_menu_links": Remap file or dir name in a auto generated menu_links with a better label
  sort menu differently, or even hide it completely. If `hide` attribute is set to true, then it will 
  hide from website, but it will still shows in Admin interface. If the entry match to a folder 
  (menu dir path), then an the entire folder will be hidden.
  
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
  
* "menu_links": Manually set a menu links structure object. It can be recursive under `child_menu_links` list. When 
this property is set, it disable the auto discovery of files and sub directory in content directory for auto
menu links. Below is an example of this object with all the properties you may set.

    ```  
      "menu_links": {
        "menu_label": "Site",
        "menu_name": "",
        "menu_order": 1,
        "links": [
          { "order": 1, "label" : "Home", "page": "readme.md" },
          { "order": 2, "label" : "Markdown Sample", "page": "sample.md" },
          { "order": 3, "label" : "Release Notes", "page": "release.md" },
          { "order": 4, "label" : "License", "page": "license.md" }
          { "order": 5, "label" : "Search", "url": "https://www.google.com" }
        ],
        "child_menu_links": [
          "menu_links": {
              "menu_label": "Project Docs",
              "menu_name": "docs",
              "menu_order": 1,
              "links": [
                { "order": 1, "label" : "User", "page": "user.md" },
                { "order": 2, "label" : "Developer", "page": "developer.md" }
              ],
              "child_menu_links": []
          },
          "menu_links": {
              "menu_label": "Project Releases",
              "menu_name": "releases",
              "menu_order": 2,
              "links": [
                { "order": 1, "label" : "User", "page": "release-notes.md" },
                { "order": 2, "label" : "Download", "page": "download.md" }
              ],
              "child_menu_links": []
          }
        ]
      }
    ```

* "plugins": Provide a list of plugin names. A plugin name is a folder located under `<site>/themes/<theme-name>`. 
  
* "theme": Set and use a custom theme name. A theme name is a folder located under `<site>/themes/<theme-name>`. 

### Special Config Parameters
  
* "view_class": Set a class name that implements a `FPView` interface. This class is responsible for rendering
  the UI output. Default value is `FirePageView`.

* "controller_class": Set a class name that extends the `FirePageController` class. This is the main application 
  controller. The default value is `FirePageController`.

### About Admin Password and Security

The default Admin password is **not** set. You may set the password in `admin_password` config parameter 
to non-empty value and it will prompt for login.

## Third Party Dependencies

The FirePage application (`index.php`) itself does not use any dependencies, other than PHP. However the default UI 
does use a CSS and JS editor highlighting libraries. But these are referenced using a online CDN path. 

NOTE: If you need to work with offline theme styling files, see our `bulma` theme.

For styling the default UI uses [Bulma CSS](https://unpkg.com/bulma). In Admin interface, it uses the a editor
syntax highlight with [CodeMirror](https://unpkg.com/codemirror).
