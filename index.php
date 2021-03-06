<?php
/**
 * FFirePage is a file based CMS application with just one single `index.php` file!
 *
 * Project Home: https://github.com/zemian/firepage
 * Project Owner: Zemian Deng
 * License: The MIT License (MIT)
 */

//
// ## FirePage
//

// Global Vars
define('FIREPAGE_VERSION', '0.1.0-SNAPSHOT');
define('FIREPAGE_CONFIG_ENV_KEY', 'FIREPAGE_CONFIG');
define('FIREPAGE_CONFIG_NAME', '.firepage.json');
define('FIREPAGE_DEAFULT_ROOT_DIR', __DIR__);
define('FIREPAGE_THEMES_DIR', __DIR__ . "/themes");
define('FIREPAGE_PLUGINS_DIR', __DIR__ . "/plugins");

// FirePage Interfaces
/** A service has init and destroy lifecycle */
interface FPService {
    function init(): void;
    function destroy(): void;
}

/** A View is responsible to render an HTML output. */
interface FPView extends FPService {
    function render(FirePageContext $page): void;
}

/** 
 * A Plugin is a request processor (or controller). A plugin can also listener and handle event that broadcast by
 * the application. A plugin construct will pass a instance of the FirePageApp instance.  
 * 
 * NOTE: All the plugins' process_request() method are invoked as a chain pipeline as long as it return
 * a none NULL value.
 */
interface FPPlugin extends FPService {
    /** Return $view if to continue next plugin chain, else NULL to stop the chain call. */
    function process_request(FirePageContext $page, FPView $view): ?FPView;
    /** Return true if processed, else false. */
    function handle_event($event_name, $params): bool;
}

/** Simple FPPlugin base class with empty implementation. User Plugin can easily extends this. */
class FirePagePlugin implements FPPlugin {
    public FirePageApp $app;
    
    public function __construct(FirePageApp $app) {
        $this->app = $app;
    }

    public function init(): void { 
    }
    public function destroy(): void { 
    }
    public function process_request(FirePageContext $page, FPView $view): ?FPView { 
        return $view;
    }
    /** Return true if processed, else false. */
    function handle_event($event_name, $params): bool {
        return false;
    }
}

/** Static and Utilities functions */
class FirePageUtils {
    static function ends_with($str, $sub_str) {
        $len = strlen($sub_str);
        return substr_compare($str, $sub_str, -$len) === 0;
    }

    static function starts_with($str, $sub_str) {
        $len = strlen($sub_str);
        return substr_compare($str, $sub_str, 0, $len) === 0;
    }

    /** Return array of two: [0] called flag, [1] called result */
    static function call_if_exists($obj, $method, $args) {
        if (method_exists($obj, $method)) {
            $ret = call_user_func_array(array($obj, $method), $args);
            return [true, $ret];
        }
        return [false, null];
    }
}

/** Nav Link object */
class FirePageLink {
    public ?string $url = null;
    public ?string $page = null;
    public int $order = 1;
    public string $label = '';
    
    public function __construct(array $map = []) {
        $this->update($map);
    }
    public function update(array $map) {
        foreach ($map as $k => $v) {
            $this->$k = $v;
        }
    }
}

/** Nav MenuLinks object */
class FirePageMenuLinks {
    public ?string $menu_label = null;
    public ?string $menu_name = null;
    public int $menu_order = 1;
    public string $menu_dir = '';
    public array $links = []; // array of FirePageLink
    public array $child_menu_links = []; // array of FirePageMenuLinks
    
    public function __construct(array $map = []) {
        $this->update($map);
    }

    function update(array $map) {
        foreach ($map as $k => $v) {
            if ($k === 'child_menu_links') {
                foreach ($v as $menu_link_map) {
                    $new_item = new FirePageMenuLinks($menu_link_map);
                    array_push($this->child_menu_links, $new_item);
                }
            } else if ($k === 'links') {
                foreach ($v as $link_map) {
                    $new_item = new FirePageLink($link_map);
                    array_push($this->links, $new_item);
                }
            } else {
                $this->$k = $v;
            }
        }
    }
}

/** A class to represent core config parameters. */
class FirePageConfig {
    public string $root_dir = FIREPAGE_DEAFULT_ROOT_DIR;
    public string $controller_class = 'FirePageController';
    public string $view_class = 'FirePageView';
    public string $title = 'FirePage';
    public ?string $admin_password = null;
    public string $root_menu_label = 'Site';
    public int $max_menu_levels = 2;
    public string $default_dir_name = '';
    public string $default_file_name = 'home.html';
    public string $default_admin_file_name = 'admin-home.html';
    public array $file_extension_list = [".html", ".txt", ".json"];
    public array $exclude_file_list = ["plugins", "themes", "admin-home.html"];
    public ?array $files_to_menu_links = null; // a Map of page_name to FPLink for override
    public bool $pretty_file_to_label = true;
    public ?FirePageMenuLinks $menu_links = null;
    public bool $disable_admin = false;
    public ?string $theme = null;
    public ?array $plugins = null;
    public ?string $logo_url = null;

    public function __construct(?array $config = null) {
        // Override config parameters if given
        if ($config !== null) {
            foreach ($config as $key => $value) {
                if ($key === 'menu_links') {
                    $this->menu_links = new FirePageMenuLinks($value); // $value is a map
                } else {
                    $this->$key = $value;
                }
            }
            
            // A root_dir of '' needs to use explicit path
            $this->root_dir = $this->root_dir ?: FIREPAGE_DEAFULT_ROOT_DIR;
        }
    }
}

/** The FirePage application manager - life starts here with run().  */
class FirePageApp {
    public FirePageConfig $config;
    public array $plugins; // array of FPPlugin
    public FirePageController $controller;

    /** Read external .firepage.json config file if it exists. */
    function read_config(string $config_file): ?array {
        if (!file_exists($config_file)) {
            return null; 
        }
        $json = file_get_contents($config_file);
        $config_map = json_decode($json, true); // Return array map
        if ($config_map === null) {
            // Fail to parse JSON config file (eg: invalid JSON format)
            die("Invalid config JSON file: $config_file");
        }
        return $config_map;
    }

    /** 
     * Load user <plugin-name>Plugin class if exists and create an instance of it. 
     * Each Plugin class will receive FirePageApp as constructor parameter.
     */
    function create_plugins(): array {
        $ret = [];
        if ($this->config->plugins !== null) {
            // Invoke plugins/<plugin_name>/<plugin_name>.php if exists
            foreach ($this->config->plugins as $plugin) {
                $plugin_file = FIREPAGE_PLUGINS_DIR . "/{$plugin}/{$plugin}.php";
                if (file_exists($plugin_file)) {
                    require_once $plugin_file;
                    $plugin_class_name = "{$plugin}FPPlugin";
                    if (class_exists($plugin_class_name)) {
                        $plugin_obj = new $plugin_class_name($this);
                        array_push($ret, $plugin_obj);
                    }
                }
            }
        }
        return $ret;
    }

    /** Load theme (which is just like an Plugin. See notes in create_plugins().). */
    function create_theme(): ?FPPlugin {
        $ret = null;
        // Invoke theme/<theme_name>/<theme_name>.php if exists
        if ($this->config->theme !== null) {
            $theme_name = $this->config->theme;
            $theme_file = FIREPAGE_THEMES_DIR . "/{$theme_name}/{$theme_name}.php";
            if (file_exists($theme_file)) {
                require_once $theme_file;
                $theme_class_name = "{$theme_name}FPPlugin";
                if (class_exists($theme_class_name)) {
                    $ret = new $theme_class_name($this);
                }
            }
        }
        return $ret;
    }

    /**
     * Invoke an event listener method defined in any of the FPPlugin object instances. 
     * The method defined in FPPlugin object should be same name as the event listed here. All the method will
     *
     * See docs/developer-guild.md for list of all events available.
     *
     * Returns null if no plugins actions has been invoked. Else it will return an array of call result
     * for each method that got called. Each array item is the result of the call.
     */
    function call_plugins_event_listener($event_name, ...$args) {        
        foreach ($this->plugins as $plugin) {
            /** @var FPPlugin $plugin */
            
            // NOTE: This will not able to spread the $args to the method!
            $plugin->handle_event($event_name, $args);
        }
    }

    /**
     * It looks for script named <page_name>.php, or page-<page_ext>.php in theme to process request.
     * If a script is found and called, it will return null so next request filter will not process View object.
     * Else if no script is found or called, then the original $view is returned back.
     *
     * The theme page .php script will have access to "$app" and "$page" global variables.
     *
     * NOTE: The $page argument is passed by ref and it's modifiable.
     */
    function process_theme_request(FirePageContext $page, FPView $view): ?FPView {
        $theme = $this->config->theme;
        if ($theme === null) {
            return $view;
        }

        $app = $this; // Expose $app variable to theme
        $processed_by_theme = false;
        $page->parent_url_path = dirname($page->url_path);
        $page->theme_url = $page->parent_url_path . "themes/" . $theme;
        $page_admin_file = FIREPAGE_THEMES_DIR . "/{$theme}/admin-page.php";
        if ($page->is_admin && file_exists($page_admin_file)) {
            // Process admin page
            require_once $page_admin_file;
            $processed_by_theme = true;
        } else {
            $page_ext = pathinfo($page->page_name, PATHINFO_EXTENSION);
            $page_name_file = FIREPAGE_THEMES_DIR . "/{$theme}/{$page->page_name}.php";
            $page_name_base = pathinfo($page_name_file, PATHINFO_BASENAME);
            $page_name_base_file = FIREPAGE_THEMES_DIR . "/{$theme}/{$page_name_base}.php";
            $page_ext_file = FIREPAGE_THEMES_DIR . "/{$theme}/page-{$page_ext}.php";
            $page_file = FIREPAGE_THEMES_DIR . "/{$theme}/page.php";

            if (file_exists($page_name_file)) {
                // Process by full page name
                require_once $page_name_file;
                $processed_by_theme = true;
            } else if (file_exists($page_name_base_file)) {
                // Process by base page name
                require_once $page_name_base_file;
                $processed_by_theme = true;
            } else if (file_exists($page_ext_file)) {
                // Process by page extension
                require_once $page_ext_file;
                $processed_by_theme = true;
            } else if (file_exists($page_file)) {
                // Process by explicit 'page.php'
                require_once $page_file;
                $processed_by_theme = true;
            }
        }

        if ($processed_by_theme) {
            return null;
        }

        return $view;
    }
    
    /** Main entry of app - read config, load plugins, theme and then process requests. */
    function run(): void {
        // Init config
        $config_file = getenv(FIREPAGE_CONFIG_ENV_KEY) ?: (FIREPAGE_DEAFULT_ROOT_DIR . "/" . FIREPAGE_CONFIG_NAME);
        $this->config = new FirePageConfig($this->read_config($config_file));

        // Load plugins and theme
        $this->plugins = $this->create_plugins();
        $theme = $this->create_theme();
        if ($theme !== null) {
            array_push($this->plugins, $theme);
        }
        
        // Create default FP controller
        $controllerClass = $this->config->controller_class;
        $this->controller = new $controllerClass($this);
                
        // Init all plugins
        foreach ($this->plugins as $plugin) {
            $plugin->init();
        }
        $this->controller->init();
        $this->call_plugins_event_listener('after_init', $this);

        // Create page context and view class instance
        $page = new FirePageContext($this);
        $viewClass = $this->config->view_class;
        $view = new $viewClass($this);
        
        // Process request using chained plugins - until first NULL view is returned
        $this->call_plugins_event_listener('before_process_request', $page, $view);
        foreach ($this->plugins as $plugin) {
            $view = $plugin->process_request($page, $view);
            if ($view === null) {
                break;
            }
        }

        // Process request by main controller - if view is not NULL
        if ($view !== null) {
            $view = $this->controller->process_request($page, $view);
        }
        $this->call_plugins_event_listener('after_process_request', $page, $view);

        // Process request by theme - custom page look
        if ($view !== null) {
            $view = $this->process_theme_request($page, $view);
        }
        
        // If View instance is still not NULL, call the render()        
        if ($view !== null) {
            $this->call_plugins_event_listener('before_view_render', $page, $view);
            $view->render($page);
            $this->call_plugins_event_listener('after_view_render', $page, $view);
        }
                
        // Done process request - destroy all plugins in reverse order
        $this->call_plugins_event_listener('before_destroy', $this);
        foreach (array_reverse($this->plugins) as $plugin) {
            $plugin->destroy();
        }
        $this->controller->destroy();
    }
}

/** The default FP controller. It's also a FPPlugin implementation. */
class FirePageController extends FirePagePlugin {
    public FirePageApp $app;
    public string $root_dir;
    
    function __construct(FirePageApp $app) {
        $this->app = $app;
        $this->root_dir = $app->config->root_dir;
    }

    function get_files($sub_path = '') {
        $ret = [];
        $dir = $this->root_dir . ($sub_path ? "/$sub_path" : '');
        $files = array_slice(scandir($dir), 2); // Get rid off . and ..
        $file_extension_list = $this->app->config->file_extension_list;
        foreach ($files as $file) {
            $dir_file = "$dir/$file";
            if (is_file($dir_file) && $file !== FIREPAGE_CONFIG_NAME) {
                if ($this->is_file_excluded($dir_file)) {
                    continue;
                }
                foreach ($file_extension_list as $ext) {
                    if (!FirePageUtils::starts_with($file, '.') && FirePageUtils::ends_with($file, $ext)) {
                        array_push($ret, $file);
                        break;
                    }
                }
            }
        }
        return $ret;
    }

    function get_dirs($sub_path = '') {
        $ret = [];
        $dir = $this->root_dir . ($sub_path ? "/$sub_path" : '');
        $files = array_slice(scandir($dir), 2); // Get rid off . and ..
        foreach ($files as $file) {
            $dir_file = "$dir/$file";
            if (is_dir($dir_file)) {
                // Do not include hidden dot folders or from exclusion list
                if (!FirePageUtils::starts_with($file, '.') && !$this->is_file_excluded($dir_file)) {
                    array_push($ret, $file);
                }
            }
        }
        return $ret;
    }

    function exists($file) {
        return file_exists($this->root_dir . "/" . $file);
    }

    function read($file) {
        return file_get_contents($this->root_dir . "/" . $file);
    }

    function write($file, $contents) {
        $file_path = $this->root_dir . "/" . $file;
        $dir_path = pathinfo($file_path, PATHINFO_DIRNAME);
        if (!is_dir($dir_path)) {
            mkdir($dir_path, 0777, true);
        }
        return file_put_contents($file_path, $contents);
    }

    function delete($file) {
        $file_path = $this->root_dir . "/" . $file;
        return file_exists($file_path) && unlink($file_path);
    }

    /** This method will EXIT! */
    function redirect(string $path) {
        header("Location: $path");
        exit();
    }

    function get_admin_session() {
        return $_SESSION['login'] ?? null;
    }

    function is_password_enabled() {
        return boolval($this->app->config->admin_password);
    }

    function is_logged_in() {
        return $this->get_admin_session() !== null;
    }

    /** Sort menu links according to the "order" property. */
    function sort_menu_links(FirePageMenuLinks $menu_links) {
        usort($menu_links->links, function ($a, $b) {
            $ret = $a->order <=> $b->order;
            if ($ret === 0) {
                $ret = $a->label <=> $b->label;
            }
            return $ret;
        });
        usort($menu_links->child_menu_links, function ($a, $b) {
            $ret = $a->menu_order <=> $b->menu_order;
            if ($ret === 0) {
                $ret = $a->menu_label <=> $b->menu_label;
            }
            return $ret;
        });
        foreach ($menu_links->child_menu_links as $menu_link) {
            $this->sort_menu_links($menu_link);
        }
    }

    /** Remap a FirePageMenuLinks to one given in config parameters. Allow user override. */
    function remap_menu_links(FirePageMenuLinks $menu_link) {
        $map = $this->app->config->files_to_menu_links;
        if ($map === null) {
            return;
        }
        
        foreach ($menu_link->links as $idx => $link) {
            $page_name = $link->page;
            if (array_key_exists($page_name, $map)) {
                $new_link = $map[$page_name];
                if (isset($new_link['hide'])) {
                    unset($menu_link->links[$idx]);
                } else {
                    $link->update($new_link);
                }
            }
        }

        foreach ($menu_link->child_menu_links as $idx => $child_menu_links_item) {
            $menu_dir = $child_menu_links_item->menu_dir;
            if (array_key_exists($menu_dir, $map) && isset($map[$menu_dir]['hide'])) {
                unset($menu_link->child_menu_links[$idx]);
            } else {
                $this->remap_menu_links($child_menu_links_item);
            }
        }
    }

    /** Build a FirePageMenuLinks object based on dir tree and files. */
    function get_menu_links_tree($dir, $max_level, $menu_order = 1): FirePageMenuLinks {
        $dir_name = ($dir === '') ? $this->app->config->root_menu_label : pathinfo($dir, PATHINFO_FILENAME);
        $menu_links = new FirePageMenuLinks();
        $menu_links->menu_order = $menu_order;
        $menu_links->menu_dir = $dir;
        $menu_links->menu_name = $dir;
        $menu_links->menu_label = $this->pretty_file_to_label($dir_name);

        $files = $this->get_files($dir);
        $i = 1;
        foreach ($files as $file) {
            $page_name = ($dir === '') ? $file : "$dir/$file";
            $label = $this->pretty_file_to_label($file);
            
            $link = new FirePageLink();
            $link->page = $page_name;
            $link->order = $i++;
            $link->label = $label;
            array_push($menu_links->links, $link);
        }

        if ($max_level > 0) {
            $sub_dirs = $this->get_dirs($dir);
            foreach ($sub_dirs as $sub_dir) {
                $dir_path = ($dir === '') ? $sub_dir : "$dir/$sub_dir";
                $sub_tree = $this->get_menu_links_tree($dir_path, $max_level - 1, $menu_order + 1);
                array_push($menu_links->child_menu_links, $sub_tree);
            }
        }
        return $menu_links;
    }
    
    function get_menu_links($is_admin): FirePageMenuLinks {
        $menu_links = null;
        $controller = $this->app->controller;
        $default_dir_name = $this->app->config->default_dir_name;
        $max_menu_levels = $this->app->config->max_menu_levels;
        if ($is_admin) {
            // For admin UI, we always get menu links from dir/files tree
            $menu_links = $controller->get_menu_links_tree($default_dir_name, $max_menu_levels);
        } else {
            // For non-admin UI, the menu_links might be user given or dir tree with remap.
            if ($this->app->config->menu_links !== null) {
                // If config has manually given menu_links, just build from that map
                $menu_links = $this->app->config->menu_links;
            } else {
                // Else, auto generate menu_links based on dirs/files listing and remap if there is any
                $menu_links = $controller->get_menu_links_tree($default_dir_name, $max_menu_levels);
                $controller->remap_menu_links($menu_links);
            }
        }
        
        // We will sort the menu links before return.
        $controller->sort_menu_links($menu_links);
        return $menu_links;
    }
    
    function process_request_site(FirePageContext $page, FPView $view): ?FPView {
        // Process GET request
        $action = $page->action;
        if ($action === 'page'){
            $page->file_content = $this->get_file_content($page->page_name);

            // Handle .JSON file specially
            if (FirePageUtils::ends_with($page->page_name, '.json') && $page->file_content !== null) {
                header('Content-Type: application/json');
                echo $page->file_content;
                return null;
            }

            // Now handle normal transform if needed.
            $this->transform_content($page);
        } else {
            die("Unknown action=$action");
        }
        
        return $view;
    }

    function process_request_admin(FirePageContext $page, FPView $view): ?FPView {
        // if password is enabled start session
        if ($this->is_password_enabled()) {
            if (!session_start()) {
                die("Unable to create PHP session!");
            }

            // If admin session is not set, then force action to be login first.
            if (!$this->is_logged_in()) {
                $page->action = "login";
            }
        }

        // Process request by HTTP method
        if (isset($_POST['action'])) {
            // Process POST request
            $page->action = $_POST['action'];
            $action = $page->action;
            if ($action === 'login_submit') {
                $password = $_POST['password'];
                $admin_password = $this->app->config->admin_password;

                $page->form_error = $this->login($password, $admin_password);
                if ($page->form_error === null) {
                    $this->redirect($page->controller_url);
                }
            } else if ($action === 'new_submit' || $action === 'edit_submit') {
                $page->page_name = $_POST['page'];
                $page->file_content = $_POST['file_content'];

                $file = $page->page_name;
                $file_content = $page->file_content;
                if ($page->form_error === null) {
                    $is_exists_check = $action === 'new_submit';
                    $page->form_error = $this->validate_note_name($file, $is_exists_check);
                }
                if ($page->form_error === null) {
                    $page->form_error = $this->validate_note_content($file_content);
                }
                if ($page->form_error === null) {
                    $this->write($file, $file_content);
                    $this->redirect($page->controller_url . "page=$file");
                }
            } else {
                die("Unknown post action=$action");
            }
        } else {
            $action = $page->action;
            if ($action === 'new') {
                $page->page_name = '';
                $page->file_content = '';
            } else if ($action === 'edit') {
                // Process GET Edit Form
                $page->file_content = $this->get_file_content($page->page_name);
            } else if ($action === 'delete-confirmed') {
                // Process GET - DELETE file
                $file = $page->page_name;
                if ($this->delete($file)) {
                    $page->delete_status = "File $file deleted";
                } else {
                    $page->delete_status = "File not found: $file";
                }
            } else if ($action === 'logout') {
                $this->logout();
                $this->redirect($page->controller_url);
            } else if ($action === 'page') {
                $page->file_content = $this->get_file_content($page->page_name);
                $this->transform_content($page);
            } else if ($action === 'login') {
                // Do nothing here.
            } else {
                die("Unknown action=$action");
            }
        }
        
        return $view;
    }

    function process_request(FirePageContext $page, FPView $view): ?FPView {
        if ($page->is_admin) {
            $view = $this->process_request_admin($page, $view);
        } else {
            $view = $this->process_request_site($page, $view);
        }
        
        // Get additional page data for rendering
        if ($view !== null) {
            $page->menu_links = $this->get_menu_links($page->is_admin);
        }
        
        return $view;
    }

    function transform_content(FirePageContext $page) {
        // Plugin should set $page->is_content_transformed flag if it has handled the content transformation
        $this->app->call_plugins_event_listener('transform_content', $page);

        // If plugin didn't do any transformation, let's use default action here.
        if (!$page->is_content_transformed) {
            // If it's an empty file, we will show a default empty message
            if ($page->file_content === null) {
                $page->file_content = '<i class="has-text-danger">File not found!</i>';
                $page->is_content_transformed = true;
            } else if ($page->file_content === '') {
                $page->file_content = '<i class="has-text-warning">This page is empty!</i>';
                $page->is_content_transformed = true;
            } else {
                // If content is not .html or .json, wraps it in preformat tag
                $file = $page->page_name;
                if (!(FirePageUtils::ends_with($file, '.html') || FirePageUtils::ends_with($file, '.json'))) {
                    $page->file_content = '<pre>' . htmlentities($page->file_content) . '</pre>';
                    $page->is_content_transformed = true;
                }
            }
        }
    }
    
    function get_file_content($file) {
        if($this->exists($file) &&
            $this->validate_note_name($file, false) === null) {
            return $this->read($file);
        }
        return null;
    }

    function is_file_excluded($dir_file) {
        $exclude_file_list = $this->app->config->exclude_file_list;
        if (count($exclude_file_list) > 0) {
            foreach($exclude_file_list as $exclude) {
                // Exclude in relative to the root_dir
                if (FirePageUtils::starts_with("$dir_file", "$this->root_dir/$exclude")) {
                    return true; // break
                }
            }
        }
        return false;
    }

    function login($password, $admin_password) {
        $n = strlen($password);
        if (!($n > 0 && $n < 20) || $password !== $admin_password) {
            return "Invalid Password";
        } else {
            $_SESSION['login'] = array('login_ts' => time());
            return null;
        }
    }

    function logout() {
        unset($_SESSION['login']);
    }

    function validate_note_name($name, $is_exists_check) {
        $ext_list = $this->app->config->file_extension_list;
        $max_depth = $this->app->config->max_menu_levels;
        $error = 'Invalid name: ';
        $n = strlen($name);
        $ext_words = implode('|', $ext_list);
        if (!($n > 0 && $n < 30 * $max_depth)) {
            $error .= 'Must not be empty and less than 100 chars.';
        } else if (!preg_match('/^[\w_\-\.\/]+$/', $name)) {
            $error .= "Must use alphabetic, numbers, '_', '-' characters only.";
        } else if (!preg_match('/(' . $ext_words . ')$/', $name)) {
            $error .= "Must have $ext_words extension.";
        } else if (preg_match('/' . FIREPAGE_CONFIG_NAME . '$/', $name)) {
            $error .= "Must not be reversed file: " . FIREPAGE_CONFIG_NAME;
        } else if (preg_match('#(^\.)|(/\.)#', $name)) {
            $error .= "Must not be a dot file or folder.";
        } else if ($is_exists_check && $this->exists($name)) {
            $error .= "File already exists.";
        } else if (substr_count($name, '/') > $max_depth) {
            $error .= "File nested path be less than $max_depth levels.";
        } else {
            $error = null;
        }
        return $error;
    }

    function validate_note_content($content) {
        $error = 'Invalid note content: ';
        $n = strlen($content);
        $size = 1024 * 1024 * 10;
        if (!($n < $size)) {
            $error .= 'Must be less than $size bytes.';
        } else {
            $error = null;
        }
        return $error;
    }

    function pretty_file_to_label($file) {
        if (!$this->app->config->pretty_file_to_label) {
            return $file;
        }
        $label = pathinfo($file, PATHINFO_FILENAME);
        $label = preg_replace('/((?:^|[A-Z])[a-z]+)/', " $1", $label);
        $label = preg_replace('/([\-_])/', " ", $label);
        $label = preg_replace_callback('/( [a-z])/', function ($matches) {
            return strtoupper($matches[0]);
        }, $label);
        $label = trim($label);
        $label = ucfirst($label);
        return $label;
    }
}

/** A Page context/map to store any data for View to use. */
class FirePageContext {
    public FirePageApp $app;
    public string $action = 'page';
    public string $page_name = 'home.html';
    public bool $is_admin = false;
    public string $url_path = '';
    public string $controller_url = 'index.php';
    public ?string $file_content = null;
    public ?string $form_error = null;
    public ?string $delete_status = null;
    public bool $is_content_transformed = false;
    public ?FirePageMenuLinks $menu_links = null;
    
    function __construct(FirePageApp $app) {   
        $this->app = $app;
        
        // Init properties from query params
        $this->action = $_GET['action'] ?? $this->action; // Default action is to GET page
        $this->is_admin = (!$app->config->disable_admin) && isset($_GET['admin']);
        $this->page_name = $_GET['page'] ?? $app->config->default_file_name;
        $this->url_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $this->controller_url = $this->url_path . '?' . ($this->is_admin ? 'admin=true&' : '');

        // Set default admin page if exists
        $admin_file_exists = $app->controller->exists($app->config->default_admin_file_name);
        if ($this->is_admin && !isset($_GET['page']) && $admin_file_exists) {
            $this->page_name = $app->config->default_admin_file_name;
        }
    }
}

/** 
 * A View class that will render default theme UI. It must contains these methods: echo_header, echo_body_content
 * and echo_footer. 
 */
class FirePageView implements FPView {
    public FirePageApp $app;
    public FirePageContext $page; // Populate during render()

    function __construct(FirePageApp $app) {
        $this->app = $app;
    }
    
    public function init(): void {}
    public function destroy(): void {}

    public function render(FirePageContext $page): void {
        $this->page = $page;
        $this->echo_start_html();
        $this->echo_head();
        $this->echo_body();
        $this->echo_end_html();
    }
    
    function echo_start_html() {
        echo '<!doctype html>';
        echo '<html lang="en">';
    }

    function echo_end_html() {
        echo '</html>';
    }

    function echo_head() {
        ?>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
            <?php $this->echo_header_scripts(); ?>
            <title><?php echo $this->app->config->title ?></title>
        </head>
        <?php
    }

    function echo_body() {
        echo '<body>';
        $this->echo_body_content();
        $this->echo_footer();
        echo '</body>';
    }

    function echo_header_scripts() {
        $page = $this->page;
        // Start of view template
        ?>
        <link rel="stylesheet" href="https://unpkg.com/bulma@0.9.1/css/bulma.min.css">
        <?php if ($page->is_admin && ($page->action === 'new' || $page->action === 'edit')) { ?>
        <link rel="stylesheet" href="https://unpkg.com/codemirror@5.58.2/lib/codemirror.css">
            <script src="https://unpkg.com/codemirror@5.58.2/lib/codemirror.js"></script>
            <script src="https://unpkg.com/codemirror@5.58.2/addon/mode/overlay.js"></script>
            <script src="https://unpkg.com/codemirror@5.58.2/mode/javascript/javascript.js"></script>
            <script src="https://unpkg.com/codemirror@5.58.2/mode/css/css.js"></script>
            <script src="https://unpkg.com/codemirror@5.58.2/mode/xml/xml.js"></script>
            <script src="https://unpkg.com/codemirror@5.58.2/mode/htmlmixed/htmlmixed.js"></script>
            <script src="https://unpkg.com/codemirror@5.58.2/mode/markdown/markdown.js"></script>
            <script src="https://unpkg.com/codemirror@5.58.2/mode/gfm/gfm.js"></script>
        <?php } ?>
        <?php // End of view template
    }

    function echo_body_content() {
        $controller = $this->app->controller;
        $page = $this->page;

        $this->echo_navbar();
        
        if ($page->is_admin && ($controller->is_password_enabled() && !$controller->is_logged_in())) {
            $this->echo_admin_login();
        } else {
            if ($page->is_admin) {
                $this->echo_admin_content();
            } else {
                $this->echo_site_content();
            }
        }
    }

    function echo_footer() {
        $version = FIREPAGE_VERSION;
        $theme_label = '';
        if ($this->app->config->theme !== null) {
            $theme_label = " with <b>" . $this->app->config->theme . "</b> theme";
        }

        ?>
        <div class="footer">
            <p>Powered by <a href="https://github.com/zemian/firepage">FirePage <?php echo $version ?></a>
                <?php echo $theme_label ?></p>
        </div>
        <?php
    }

    function echo_content() {
        $content = $this->page->file_content ?? '';
        echo $content;
    }

    function echo_site_content() {
        ?>
        <section class="section">
            <div class="columns">
                <div class="column is-3">
                    <div class="menu">
                        <?php $this->echo_menu_links(); ?>
                    </div>
                </div>
                <div class="column is-9">
                    <div class="content" style="min-height: 60vh;">
                        <?php $this->echo_content(); ?>
                    </div>
                </div>
            </div>
        </section>
        <?php
    }

    function echo_menu_links(FirePageMenuLinks $menu_links = null) {        
        if ($menu_links === null) {
            // Default menu links is from page data, else it's a recursive from sub menu
            $menu_links = $this->page->menu_links;
        }

        if ($menu_links->menu_label !== '') {
            echo "<p class='menu-label'>{$menu_links->menu_label}</p>";
        }

        echo "<ul class='menu-list'>";
        $this->echo_menu_links_items($menu_links->links, $this->page->controller_url, $this->page->page_name);
        foreach ($menu_links->child_menu_links as $child_item) {
            $this->echo_menu_links_as_sublist($child_item);
        }
        echo "</ul>";
    }
    
    function echo_menu_links_items($links, $controller_url, $active_file) {
        foreach ($links as $link) {
            $url = $link->url;
            $page_name = $link->page;
            if ($url === null && $page_name !== null) {
                $url = $controller_url . "page=" . $page_name;
            }
            $is_active = ($page_name === $active_file) ? "is-active": "";
            echo "<li><a class='$is_active' href='$url'>$link->label</a></li>";
        }
    }

    function echo_menu_links_as_sublist(FirePageMenuLinks $menu_links) {        
        echo "<li>";
        echo "<a>{$menu_links->menu_label}</a>";
        echo "<ul>";
        $this->echo_menu_links_items($menu_links->links, $this->page->controller_url, $this->page->page_name);

        // Recurse into sub $menu_links
        foreach ($menu_links->child_menu_links as $child_item) {
            $this->echo_menu_links_as_sublist($child_item);
        }
        echo "</ul>";
        echo "</li>";
    }
    
    function echo_navbar_logo() {
        ?>
        <a class="navbar-item" href='<?php echo $this->page->url_path; ?>'>
            <?php if ($this->app->config->logo_url !== null) { ?>
                <img src="<?php echo $this->app->config->logo_url; ?>"
                     width="100" height="28"
                     alt="<?php echo $this->app->config->title; ?>">
            <?php } else { ?>
                <span class="title"><?php echo $this->app->config->title; ?></span>
            <?php } ?>
        </a>
        <?php        
    }

    function echo_navbar_admin() {
        $page = $this->page;
        ?>
        <div class="navbar">
            <div class="navbar-brand">
                <?php $this->echo_navbar_logo() ?>
            </div>
            <div class="navbar-end">
                <?php if ($page->action === 'page') { ?>
                    <div class="navbar-item">
                        <a href="<?php echo $page->controller_url; ?>action=edit&page=<?= $page->page_name ?>">EDIT</a>
                    </div>
                    <div class="navbar-item">
                        <a href="<?php echo $page->controller_url; ?>action=delete&page=<?= $page->page_name ?>">DELETE</a>
                    </div>
                <?php } ?>
            </div>
        </div>
        <?php
    }
    
    function echo_navbar_site() {
        ?>
        <div class="navbar">
            <div class="navbar-brand">
                <?php $this->echo_navbar_logo() ?>
            </div>
        </div>
        <?php
    }
    
    function echo_navbar() {
        if ($this->page->is_admin) {
            $this->echo_navbar_admin();
        } else {
            $this->echo_navbar_site();
        }
    }
    
    function echo_admin_login() {
        $page = $this->page;
        ?>
        <section class="section">
            <?php if ($page->form_error !== null) { ?>
                <div class="notification is-danger"><?php echo $page->form_error; ?></div>
            <?php } ?>
            <div class="level">
                <div class="level-item has-text-centered">
                    <form method="POST" action="<?php echo $page->controller_url; ?>">
                        <input type="hidden" name="action" value="login_submit">
                        <div class="field">
                            <div class="label">Admin Password</div>
                            <div class="control"><input class="input" type="password" name="password"></div>
                        </div>
                        <div class="field">
                            <div class="control">
                                <input class="button is-info" type="submit" name="submit" value="Login">
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </section>
        <?php
    }
    
    function echo_admin_content() {
        $controller = $this->app->controller;
        $page = $this->page;
        ?>
        <section class="section">
            <div class="columns">
                <div class="column is-3">
                    <div class="menu">
                        <p class="menu-label">Admin</p>
                        <ul class="menu-list">
                            <li><a href='<?php echo $page->controller_url; ?>action=new'>New</a></li>
    
                            <?php if ($controller->is_logged_in()) { ?>
                                <li><a href='<?php echo $page->controller_url . "action=logout"; ?>'>Logout</a></li>
                            <?php } else { ?>
                                <li><a href='<?php echo $page->url_path; ?>'>Exit</a></li>
                            <?php } ?>
                        </ul>
    
                        <?php $this->echo_menu_links(); ?>
                    </div>
                </div>
                <div class="column is-9">
                    <?php if ($page->action === 'new' || $page->action === 'new_submit') { ?>
                        <?php if ($page->form_error !== null) { ?>
                            <div class="notification is-danger"><?php echo $page->form_error; ?></div>
                        <?php } ?>
                        <form method="POST" action="<?php echo $page->controller_url; ?>">
                            <input type="hidden" name="action" value="new_submit">
                            <div class="field">
                                <div class="label">File Name</div>
                                <div class="control"><input id='file_name' class="input" type="text" name="page" value="<?php echo $page->page_name ?>"></div>
                            </div>
                            <div class="field">
                                <div class="label">Markdown</div>
                                <div class="control"><textarea id='file_content' class="textarea" rows="20" name="file_content"><?php echo $page->file_content ?></textarea></div>
                            </div>
                            <div class="field">
                                <div class="control">
                                    <input class="button is-info" type="submit" name="submit" value="Create">
                                    <a class="button" href="<?php echo $page->controller_url; ?>">Cancel</a>
                                </div>
                            </div>
                        </form>
                    <?php } else if ($page->action === 'edit' || $page->action === 'edit_submit') { ?>
                        <form method="POST" action="<?php echo $page->controller_url; ?>">
                            <input type="hidden" name="action" value="edit_submit">
                            <div class="field">
                                <div class="label">File Name</div>
                                <div class="control"><input id='file_name' class="input" type="text" name="page" value="<?= $page->page_name ?>"></div>
                            </div>
                            <div class="field">
                                <div class="label">Markdown</div>
                                <div class="control"><textarea id='file_content' class="textarea" rows="20" name="file_content"><?= $page->file_content ?></textarea></div>
                            </div>
                            <div class="field">
                                <div class="control">
                                    <input class="button is-info" type="submit" name="submit" value="Update">
                                    <a class="button" href="<?php echo $page->controller_url; ?>page=<?= $page->page_name ?>">Cancel</a>
                                </div>
                            </div>
                        </form>
                    <?php } else if ($page->action === 'delete') { ?>
                        <div class="message is-danger">
                            <div class="message-header">Delete Confirmation</div>
                            <div class="message-body">
                                <p class="block">Are you sure you want to delete <b><?= $page->page_name ?></b>?</p>

                                <a class="button is-info" href="<?php echo $page->controller_url; ?>action=delete-confirmed&page=<?= $page->page_name ?>">Delete</a>
                                <a class="button" href="<?php echo $page->controller_url; ?>page=<?= $page->page_name ?>">Cancel</a>
                            </div>
                        </div>
                    <?php } else if ($page->action === 'delete-confirmed') { ?>
                        <div class="message is-success">
                            <div class="message-header">Deleted!</div>
                            <div class="message-body">
                                <p class="block"><?php echo $page->delete_status; ?></p>
                            </div>
                        </div>
                    <?php } else { ?>
                        <div class="content" style="min-height: 60vh;">
                            <?php $this->echo_content(); ?>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </section>

        <?php if ($page->action === 'new' || $page->action === 'edit') { ?>
            <script>
                function getCodeMirrorMode(fileName) {
                    var mode = fileName.split('.').pop();
                    if (mode === 'md') {
                        // ext = 'markdown';
                        mode = 'gfm'; // Use GitHub Flavor Markdown
                    } else if (mode === 'json') {
                        mode = {name: 'javascript', json: true};
                    } else if (mode === 'html') {
                        mode = 'htmlmixed';
                    }
                    return mode;
                }

                // Load CodeMirror with Markdown (GitHub Flavor Markdown) syntax highlight
                var fileNameEl = document.getElementById('file_name');
                var editor = CodeMirror.fromTextArea(document.getElementById('file_content'), {
                    lineNumbers: true,
                    mode: getCodeMirrorMode(fileNameEl.value),
                    theme: 'default',
                });
                editor.setSize(null, '500');

                // Listen to FileName focus change then trigger editor mode change
                fileNameEl.addEventListener('focusout', (event) => {
                    var name = event.target.value;
                    var mode = getCodeMirrorMode(name);
                    editor.setOption('mode', mode);
                });
            </script>
        <?php } /* End of new/edit form for <script> tag */?>
        
        <?php
    }
}

//
// ### Main App Entry
//
$app = new FirePageApp();
$app->run();
