# Release Notes

## 1.3.0 2020-11-12

* [x] Improve index.php with MarkNotesApp class
* [x] Fix session failed to start
* [x] Fix exclusion of got files
* [x] Rename config parameter 'default_note' => 'default_file_name'
* [x] Rename config parameter 'default_ext_list' => 'file_extension_list'
* [x] Rename config parameter 'default_notes_dir' => 'default_dir_name'
* [x] Add config parameter 'root_dir'
* [x] Add config parameter 'exclude_file_list'
* [x] Add syntax highlight for `.json` and `.html` in editor
* [x] Add config parameter 'menu_links' for custom links rendering
* [x] Add config parameter 'files_to_menu_links' for remapping menu links
* [x] Add config parameter 'pretty_file_to_label' to auto petty file to menu link label

## 1.2.0 2020-11-10

* [x] Fix `.firepage.json` config override
* [x] Add `MARKNOTES_CONFIG` env config file loading
* [x] Make `default_ext_list` config into list (support multiple extensions)
* [x] Add footer to site
* [x] Validate hidden (dot) files/folders creation and edit
* [x] Validate reserved `.firepage.json` file name
* [x] Add Parsedown-Extra lib
* [x] Refactor code to use MarkNotesApp class
* [x] Style `.txt` & `.json` files with `<pre>` wrapper

## 1.1.0 2020-11-07 

* [x] Support sub folders browsing up to 3 levels. Ignore all dot hidden folders
* [x] Add external config file `.firepage.json` override
* [x] Secure - Support Admin password
* [x] Add config `root_menu_label` parameter
* [x] Add stronger file name and content validation

## 1.0.0 2020-10-01 

* [x] Single file `index.php` application
* [x] Clean and simple UI style with Markdown editing syntax highlight
* [x] Display all Markdown files with `readme.md` as default page
* [x] Web based Admin interface to manage Markdown files
* [x] Secure - Serve files from single directory only
