# FirePage Developer Guide

The `index.php` is a MVC based application. It process a HTTP request using a Controller. As the request being process
it will generate a page context Model that holds data information. And then finally it uses a View class to render
the page context model data onto a UI output page.

It has few important classes and interfaces that you may use to extend the application.

* `FirePageController` - This is the main Controller that process request for the application. Note that you can
  write you own controller using a plugin. Or you can even replace this main controller by extending it. See
  `controller_class` config parameter for details.
* `FirePageContext` - A transient data holder context object that contains request processing data as it process by
   a Controller, and it is pass to a View class for rendering output for a page.
* `FirePageView` - A View class that will render output for a page request using the page context data object. It
  provide a default UI looks for the application. Note you can override a View class by using `view_class` config 
  parameter.
* `FirePageApp` - This is the main application class and there is only one instance per application.
  It bootstraps the application and holds all the objects mentioned above. It also create and activate any 
  given plugins and theme. All other supporting classes (eg: Controller, View and Plugins) will have access 
  to a instance of this class.
  
A HTTP request will be process by a Controller. User can write custom Plugin Controller that add additional
processing. A plugin is a class that implements `FPPlugin` interface, and it must provide a 
`process_request($page): ?FPView` method. One or more plugins can be chained together for processing a request. 
If one decides to end the chain, then simply returns a `NULL` value, else return a `FPView` instance to continue.

If none of the plugins render their own output, then the default `FirePageController` will run its 
`process_request()` to provide default behavior.

In this document, we will refer to few variable objects that are from certain class types:

* `$app` : `FirePageApp` - the main application instance
* `$view` : `FPView` - usually a `FirePageView` or a sub class of it
* `$page` : `FirePageContext` - a request object that holds processing data
* `$controller` : `FPPlugin` - usually a `FirePageController` or a sub class of it
* `$config`: `FirePageConfig` - a object that holds the `.firepage.json` config parameters.

Class composition:

* The `FirePageApp` holds an instance of `$controller` and `$config`.
* The `FirePageView` holds an instance of `$page`
* The `FirePageController` process request with `$page` and returns a `$view`.

## How to Write Plugin

A FirePage plugin extends the functionality of the application. A plugin is also a Controller that can process
a request. A plugin name is a PHP script under a folder located under `<site>/plugins/<plugin_name>/<plugin_name>.php`. 
The PHP script should define and provide a class named `<plugin_name>FPPlugin`, which must implements the `FPPlugin` 
interface. The plugin class will be automatically instantiated when activated. The constructor of this class will 
receive an instance of the `FirePageApp` object.

Note that one or more plugin may be activated per application.

A plugin can also handle event callback from the application.

The following events are available:

* `after_init` - Arguments: `$app`. 
  Event is sent after all plugins and main controller has been initialized.
* `before_destroy` - Arguments: `$app`. 
  Event is sent before destroying all plugins and main controller.
* `before_process_request` - Arguments: `$page` and `$view`. 
  Event is sent before all plugins and main controller execute their `process_request()` method.
* `after_process_request` - Arguments: `$page` and `$view`. 
  Event is sent after all plugins and main controller execute their `process_request()` method.
* `before_view_render` - Arguments: `$page` and `$view`. 
  Event is sent before the final View object is execute it `render()` method.
* `after_view_render` Arguments: `$page` and `$view`. 
  Event is sent after the final View object is execute it `render()` method.

The main controller `FirePageController` also sends the following events if it gets to process the request:

* `transform_content` - Arguments: `$page`. 
  Event is sent after the file content is fetched. The content string is stored in `$page->file_content`. 
  If plugin decided to transform the content, it needs to set `$page->is_content_transformed` flag to `true`.

## How to Write Theme

A theme is also an plugin, except it has few extra features to help styling your UI looks. A theme name is a PHP 
script under a folder located under `<site>/themes/<theme_name>/<theme_name>.php`. Like a plugin like, a theme 
can also define a plugin class that process the request named `<theme_name>FPPlugin`. In addition, a theme can
have optional PHP scripts that replace a View class.

Note that only one theme can be activated per application.

If the following PHP file exists, it will be used to render the UI output instead of using the default 
`FirePageView` class. It will search in the order it specified here and stop searching when first one is found.
Note that the `<>` means a variable value.

* If `<theme_name>/<sub_path/to/a/<page_name>.php` matches the full path of the content page path name (including the
  sub directory paths.)
* If `<theme_name>/<page_name>.php` matches only the file name part of the content page name.
* If `<theme_name>/page-<extension>.php` matches any content page name extension. (Example: `page-html.php` or 
  `page-txt.php`.)
* If `<theme_name>/page.php` matches any content page.

NOTE: A theme PHP scripts are just a convenient way to provide custom UI render output without having to write a
class that implements `FPView` interface.
