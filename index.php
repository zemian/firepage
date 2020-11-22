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
define('FIREPAGE_VERSION', '1.0.0-SNAPSHOT');
define('FIREPAGE_CONFIG_ENV_KEY', 'FIREPAGE_CONFIG');
define('FIREPAGE_CONFIG_NAME', '.firepage.json');
define('FIREPAGE_DEAFULT_ROOT_DIR', __DIR__);
define('FIREPAGE_THEMES_DIR', __DIR__ . "/themes");
define('FIREPAGE_PLUGINS_DIR', __DIR__ . "/plugins");

//
// ### The FirePage Application - Controller, View and PageContext
//
/** The application controller. The entry method is process_request(). */
class FirePageController {
    public $app; // FirePageApp
    public $root_dir;
    public $title;
    public $admin_password;
    public $root_menu_label;
    public $max_menu_levels;
    public $default_dir_name;
    public $default_file_name;
    public $default_admin_file_name;
    public $file_extension_list;
    public $exclude_file_list;
    public $files_to_menu_links;
    public $pretty_file_to_label;
    public $menu_links;
    public $disable_admin;
    
    function __construct($app) {
        $this->app = $app;

        // Init config parameters into class properties
        $config = $this->app->config;
        $this->root_dir = ($config['root_dir'] ?? '') ?: FIREPAGE_DEAFULT_ROOT_DIR;
        $this->title = $config['title'] ?? 'FirePage';
        $this->admin_password = $config['admin_password'] ?? '';
        $this->root_menu_label = $config['root_menu_label'] ?? 'Pages';
        $this->max_menu_levels = $config['max_menu_levels'] ?? 2;
        $this->default_dir_name = $config['default_dir_name'] ?? '';
        $this->default_file_name = $config['default_file_name'] ?? 'home.html';
        $this->default_admin_file_name = $config['default_admin_file_name'] ?? 'admin-home.html';
        $this->file_extension_list = $config['file_extension_list'] ?? [".html", ".txt", ".json"];
        $this->exclude_file_list = $config['exclude_file_list'] ?? ["plugins", "themes", "admin-home.html"];
        $this->files_to_menu_links = $config['files_to_menu_links'] ?? [];
        $this->pretty_file_to_label = $config['pretty_file_to_label'] ?? false;
        $this->disable_admin = $config['disable_admin'] ?? false;

        // Optional config params that default to null values if not set
        $this->menu_links = $config['menu_links'] ?? null;
    }

    /**
     * Invoke action methods defined in plugins/theme. Action methods are methods that gets invoke in plugins
     * regardless of the result. All plugins on same method will be called.
     * 
     * Example of methods are "init" and "destroy".
     * 
     * Returns null if no plugins actions has been invoked. Else it will return an array of call result 
     * for each method that got called. Each array item is the result of the call.
     */
    function call_plugins_action($method, ...$args) {
        $ret = array();
        if ($this->app->plugins !== null) {
            foreach ($this->app->plugins as $plugin) {
                $call_ret = FirePageUtils::call_if_exists($plugin, $method, $args);
                if ($call_ret[0]) {
                    array_push($ret, $call_ret[1]);
                }
            }
        }
        if ($this->app->theme !== null) {
            $call_ret = FirePageUtils::call_if_exists($this->app->theme, $method, $args);
            if ($call_ret[0]) {
                array_push($ret, $call_ret[1]);
            }
        }
        return count($ret) > 0 ? $ret : null;
    }
    
    /** Same as call_plugins_action() but will call methods on theme then reverse order of plugins initialized. */
    function call_plugins_action_reverse($method, ...$args) {
        $ret = array();
        if ($this->app->theme !== null) {
            $call_ret = FirePageUtils::call_if_exists($this->app->theme, $method, $args);
            if ($call_ret[0]) {
                array_push($ret, $call_ret[1]);
            }
        }
        if ($this->app->plugins !== null) {
            foreach (array_reverse($this->app->plugins) as $plugin) {
                $call_ret = FirePageUtils::call_if_exists($plugin, $method, $args);
                if ($call_ret[0]) {
                    array_push($ret, $call_ret[1]);
                }
            }
        }
        return count($ret) > 0 ? $ret : null;
    }

    /**
     * Invoke filter methods defined in plugins/theme. Filter methods are one that will call as pipe line
     * filter chain on all plugins/theme. If one plugin returns NULL, then rest of plugins will stop processing.
     * 
     * Example of filter method: "process_request".
     *
     * Returns null if no plugins actions has been invoked. Else it will return an array with one element
     * and that's last chained plugin filter method returned value.
     */
    function call_plugins_filter($method, ...$args) {
        $filter_called = false;
        if ($this->app->plugins !== null) {
            foreach ($this->app->plugins as $plugin) {
                $call_ret = FirePageUtils::call_if_exists($plugin, $method, $args);
                if ($call_ret[0]) {
                    $filter_called = true;
                    $args = $call_ret[1];
                    if ($args === null) {
                        return [null];
                    }
                }
            }
        }
        
        if ($this->app->theme !== null) {
            $call_ret = FirePageUtils::call_if_exists($this->app->theme, $method, $args);
            if ($call_ret[0]) {
                $filter_called = true;
                $args = $call_ret[1];
                if ($args === null) {
                    return [null];
                }
            }
        }
        
        if (!$filter_called) {
            return null; // Return early if no filter has been called.
        }

        if ($args !== null) {
            // Returns the final result from the last plugin/theme filter
            return [$args];
        }
        
        // Return NULL here means there is no methods got invoked by plugins
        return null;
    }
    
    function init() {
        $this->call_plugins_action('init', $this);
    }
    
    function destroy() {
        $this->call_plugins_action_reverse('destroy'); // call in reverse order
    }

    function get_files($sub_path = '') {
        $ret = [];
        $dir = $this->root_dir . ($sub_path ? "/$sub_path" : '');
        $files = array_slice(scandir($dir), 2);
        foreach ($files as $file) {
            $dir_file = "$dir/$file";
            if (is_file($dir_file) && $file !== FIREPAGE_CONFIG_NAME) {
                if ($this->is_file_excluded($dir_file)) {
                    continue;
                }
                foreach ($this->file_extension_list as $ext) {
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
        $files = array_slice(scandir($dir), 2);
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
        return $this->exists($file) && unlink($this->root_dir . "/" . $file);
    }

    /** This method will EXIT! */
    function redirect($path) {
        header("Location: $path");
        exit();
    }

    function get_admin_session() {
        return $_SESSION['login'] ?? null;
    }

    function is_password_enabled() {
        return $this->admin_password !== '';
    }

    function is_logged_in() {
        return $this->get_admin_session() !== null;
    }


    // We need to accept array reference so we can modify array in-place!
    function sort_menu_links(&$menu_links) {
        usort($menu_links['links'], function ($a, $b) {
            $ret = $a['order'] <=> $b['order'];
            if ($ret === 0) {
                $ret = $a['label'] <=> $b['label'];
            }
            return $ret;
        });
        usort($menu_links['child_menu_links'], function ($a, $b) {
            $ret = $a['menu_order'] <=> $b['menu_order'];
            if ($ret === 0) {
                $ret = $a['menu_label'] <=> $b['menu_label'];
            }
            return $ret;
        });
        foreach ($menu_links['child_menu_links'] as $menu_link) {
            $this->sort_menu_links($menu_link);
        }
    }

    function remap_menu_links(&$menu_link) {
        if ($this->call_plugins_action('remap_menu_links', $menu_link)) {
            return;
        }
        if (count($this->files_to_menu_links) === 0) {
            return;
        }
        $map = $this->files_to_menu_links;
        foreach ($menu_link['links'] as $idx => &$link) {
            $file = $link['page'];
            if (array_key_exists($file, $map)) {
                $new_link = $map[$file];
                if (isset($new_link['hide'])) {
                    unset($menu_link['links'][$idx]);
                } else {
                    $link = array_merge($link, $map[$file]);
                }
            }
        }

        foreach ($menu_link['child_menu_links'] as $idx => &$child_menu_links_item) {
            $menu_dir = $child_menu_links_item['menu_dir'];
            if (array_key_exists($menu_dir, $map) && isset($map[$menu_dir]['hide'])) {
                unset($menu_link['child_menu_links'][$idx]);
            } else {
                $this->remap_menu_links($child_menu_links_item);
            }
        }
    }

    function get_menu_links_tree($dir, $max_level, $menu_order = 1) {
        $menu_links = array(
            "menu_label" => null,
            "menu_name" => null,
            "menu_order" => $menu_order,
            "menu_dir" => $dir,
            "links" => [],
            "child_menu_links" => []
        );
        $dir_name = ($dir === '') ? $this->root_menu_label : pathinfo($dir, PATHINFO_FILENAME);
        $menu_links['menu_label'] = $this->pretty_file_to_label($dir_name);
        $menu_links['menu_name'] = $dir;

        $files = $this->get_files($dir);
        $i = 1;
        foreach ($files as $file) {
            $file_path = ($dir === '') ? $file : "$dir/$file";
            $url_path = $this->default_dir_name ? "{$this->default_dir_name}/$file_path" : $file_path;
            $label = $this->pretty_file_to_label($file);

            array_push($menu_links['links'], array(
                'page' => $file_path,
                'order' => $i++,
                'label' => $label,
                'url' => $url_path
            ));
        }

        if ($max_level > 0) {
            $sub_dirs = $this->get_dirs($dir);
            foreach ($sub_dirs as $sub_dir) {
                $dir_path = ($dir === '') ? $sub_dir : "$dir/$sub_dir";
                $sub_tree = $this->get_menu_links_tree($dir_path, $max_level - 1, $menu_order + 1);
                array_push($menu_links['child_menu_links'], $sub_tree);
            }
        }
        return $menu_links;
    }

    /** 
     * Return a View object that can render UI output. It may return NULL if no view is needed (like an plugin
     * has taken care of their own output.)
     */
    function process_request() {
        // Page properties
        $page = new FirePageContext($this);

        // Let plugin process request first, and if there is any result from it, return immediately
        $plugins_result = $this->call_plugins_filter('process_request', $page);
        if ($plugins_result !== null) {
            $page = $plugins_result[0];
        }
        
        if ($page === null) {
            return null; // This mean the plugin has taken care of view and output.
        }
        
        // Here means let's continue process request by our controller.
        return $this->process_default_request($page);
    }
    
    function process_default_request($page) {
        // If this is admin request, and if password is enabled start session
        if ($page->is_admin && $this->is_password_enabled()) {
            if (!session_start()) {
                die("Unable to create PHP session!");
            }

            // If admin session is not set, then force action to be login first.
            if (!$this->is_logged_in()) {
                $page->action = "login";
            }
        }

        // Process Request and return $page var
        $action = $page->action;

        // Process POST requests
        if (isset($_POST['action'])) {
            $page->action = $_POST['action'];

            $action = $page->action;
            if ($action === 'login_submit') {
                $password = $_POST['password'];
                $admin_password = $this->admin_password;

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
                die("Unknown action=$action");
            }
        } else {
            // Process GET request
            $is_admin = $page->is_admin;
            if ($is_admin) {
                if ($action === 'new') {
                    $page->page_name = '';
                    $page->file_content = '';
                } else if ($action === 'edit') {
                    // Process GET Edit Form
                    $file = $page->page_name;
                    if ($this->exists($file)) {
                        $page->file_content = $this->read($file);
                    } else {
                        $page->file_content = "File not found: $file";
                    }
                } else if ($action === 'delete-confirmed') {
                    // Process GET - DELETE file
                    $file = $page->page_name;
                    $delete_status = &$page->delete_status; // Use Ref
                    if ($this->delete($file)) {
                        $delete_status = "File $file deleted";
                    } else {
                        $delete_status = "File not found: $file";
                    }
                } else if ($action === 'logout') {
                    $this->logout();
                    $this->redirect($page->controller_url);
                } else if ($action === 'page') {
                    $content = $this->get_file_content($page->page_name);
                    $page->file_content = $this->transform_content($page->page_name, $content);
                }
            } else {
                // GET - Not Admin Actions
                if ($action === 'page'){
                    $content = $this->get_file_content($page->page_name);
                    $page->file_content = $this->transform_content($page->page_name, $content);
                } else {
                    die("Unknown action=$action");
                }
            }
        }

        // Let Theme process request that can modify $page object if needed.
        if(!$this->process_theme_request($page)) {
            return $this->process_view($page);
        }

        // No view object is returned.
        return null;
    }

    /**
     * It looks for script named <page_name>.php, or page-<page_ext>.php in theme to process request. If the script
     * exist, it should return page object for view to render, else returns null response has already been
     * processed. (no view needed). The theme page .php script will have access to "$controller" and
     * "$page" global variables.
     *
     * NOTE: The $page argument is passed by ref and it's modifiable.
     */
    function process_theme_request(&$page) {
        $theme = $this->app->theme;
        if ($theme === null) {
            return false;
        }

        $processed_by_theme = false;
        $page->parent_url_path = dirname($page->url_path);
        $page->theme_url = $page->parent_url_path . "themes/" . $theme;
        $controller = $this; // This $controller variables will be expose to theme.
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
        return $processed_by_theme;
    }
    
    function process_view($page) {        
        // Do not provide any view object for .json file
        if (FirePageUtils::ends_with($page->page_name, '.json')) {
            header('Content-Type: application/json');
            echo $page->file_content;
            return null;
        }
        return $this->create_view($page);
    }
    
    function create_view($page) {
        return new FirePageView($this, $page);
    }

    function transform_content($file, $content) {
        $plugins_result = $this->call_plugins_filter('transform_content', $file, $content);
        if ($plugins_result !== null) {
            $plugin_content = $plugins_result[0];
            // If plugin returns null, then run transform_default_content, else use what plugin returned.
            if ($plugin_content === null) {
                $content = $this->transform_default_content($file, $content);
            } else {
                $content = $plugin_content;
            }
        }
        
        return $content;
    }
    
    function transform_default_content($file, $content) {
        // Both HTML and .json files, we will will not transform.
        if (FirePageUtils::ends_with($file, '.html') || FirePageUtils::ends_with($file, '.json')) {
            // Do nothing
        } else {
            // If it's an empty file, we will show a default empty message
            if ($content === '') {
                $content = '<i>This page is empty!</i>';
            }

            // All other file types will escape HTML and serve as pre-formatted text
            $content = '<pre>' . htmlentities($content) . '</pre>';
        }

        return $content;
    }

    function get_file_content($file) {
        if($this->exists($file) &&
            $this->validate_note_name($file, false) === null) {
            $content = $this->read($file);
            return $content;
        } else {
            return "File not found: $file";
        }
    }

    function is_file_excluded($dir_file) {
        if (count($this->exclude_file_list) > 0) {
            foreach($this->exclude_file_list as $exclude) {
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
        $ext_list = $this->file_extension_list;
        $max_depth = $this->max_menu_levels;
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
        if (!$this->pretty_file_to_label) {
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
    public $action;
    public $page_name;
    public $is_admin;
    public $url_path;
    public $file_content;
    public $controller_url;
    public $form_error = null;
    public $delete_status = null;
    
    function __construct($controller) {        
        // Init properties from query params
        $this->action = $_GET['action'] ?? 'page'; // Default action is to GET page
        $this->is_admin = (!$controller->disable_admin) && isset($_GET['admin']);
        $this->page_name = $_GET['page'] ?? $controller->default_file_name;
        $this->url_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $this->controller_url = $this->url_path . '?' . ($this->is_admin ? 'admin=true&' : '');

        // Set default admin page if exists
        if ($this->is_admin && !isset($_GET['page']) && $controller->exists($controller->default_admin_file_name)) {
            $this->page_name = $controller->default_admin_file_name;
        }
    }
}

/** 
 * A View class that will render default theme UI. It must contains these methods: echo_header, echo_body_content
 * and echo_footer. 
 */
class FirePageView {
    public $controller;
    public $page;

    function __construct($controller, $page) {
        $this->controller = $controller;
        $this->page = $page;
    }

    function get_menu_links() {
        $controller = $this->controller;        
        $page = $this->page;
        $menu_links = null;
        if ($page->is_admin) {
            $menu_links = $controller->get_menu_links_tree($this->controller->default_dir_name, $this->controller->max_menu_levels);
        } else {
            if ($controller->menu_links !== null) {
                // If config has manually given menu_links, return just that.
                $menu_links = $controller->menu_links;
            } else {
                // Else, auto generate menu_links based on dirs/files listing
                $menu_links = $controller->get_menu_links_tree($this->controller->default_dir_name, $this->controller->max_menu_levels);
                $controller->remap_menu_links($menu_links);
            }
            $controller->sort_menu_links($menu_links);
        }
        return $menu_links;
    }

    function echo_menu_links($menu_links = null) {
        $page = $this->page;
        
        if ($menu_links === null) {
            $menu_links = $this->get_menu_links();
        }
        
        echo "<p class='menu-label'>{$menu_links['menu_label']}</p>";
        echo "<ul class='menu-list'>";

        $active_file = $page->page_name;
        $controller_url = $page->controller_url;
        $i = 0; // Use to track last item in loop
        $files_len = count($menu_links['links']);
        foreach ($menu_links['links'] as $link) {
            $label = $link['label'];
            $url = $link['url'];
            $is_active = ($url === $active_file) ? "is-active": "";
            echo "<li><a class='$is_active' href='{$controller_url}page=$url'>$label</a>";
            if ($i++ < ($files_len - 1)) {
                echo "</li>"; // We close all <li> except last one so Bulma memu list can be nested
            }
        }

        foreach ($menu_links['child_menu_links'] as $child_menu_links_item) {
            $this->echo_menu_links($child_menu_links_item);
        }
        echo "</li>"; // Last menu item
        echo "</ul>";
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

    function echo_header() {
        $title = $this->controller->title;
        ?>
        <!doctype html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
            <?php $this->echo_header_scripts(); ?>
            <title><?php echo $title ?></title>
        </head>
        <body>
        <?php
    }

    function echo_navbar_admin() {
        $controller = $this->controller;
        $page = $this->page;
        ?>
        <div class="navbar">
            <div class="navbar-brand">
                <div class="navbar-item">
                    <a class="title" href='<?php echo $page->controller_url; ?>'><?php echo $controller->title; ?></a>
                </div>
            </div>
            <div class="navbar-end">
                <?php if ($page->is_admin && $page->action === 'page' && (!$controller->is_password_enabled() || $controller->is_logged_in())) { ?>
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
        $controller = $this->controller;
        $page = $this->page;
        ?>
        <div class="navbar">
            <div class="navbar-brand">
                <div class="navbar-item">
                    <a class="title" href='<?php echo $page->controller_url; ?>'><?php echo $controller->title; ?></a>
                </div>
            </div>
        </div>
        <?php
    }
    
    function echo_navbar() {
        $page = $this->page;        
        if ($page->is_admin) {
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
        $controller = $this->controller;
        $page = $this->page;
        ?>
        <section class="section">
            <div class="columns">
                <div class="column is-3 menu">
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
                    <?php } else if ($page->action === 'page') { ?>
                        <div class="content">
                            <?php echo $page->file_content; ?>
                        </div>
                    <?php } else { ?>
                        <div class="message is-warning">
                            <div class="message-header">Oops!</div>
                            <div class="message-body">
                                <p class="block">We can not process this action: <?php echo $page->action ; ?></p>
                            </div>
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
    
    function echo_page_content() {
        $page = $this->page;
        ?>
        <section class="section">
            <div class="columns">
                <div class="column is-3 menu">
                    <?php $this->echo_menu_links(); ?>
                </div>
                <div class="column is-9">
                    <div class="content">
                        <?php echo $page->file_content; ?>
                    </div>
                </div>
            </div>
        </section>
        <?php
    }
    
    function echo_body_content() {
        $controller = $this->controller;
        $page = $this->page;
        
        // Start of view template
        ?>
        
        <?php $this->echo_navbar(); ?>
        <?php if ($page->is_admin && ($controller->is_password_enabled() && !$controller->is_logged_in())) {?>
            <?php $this->echo_admin_login(); ?>
        <?php } else { /* Not login form. */ ?>
            <?php if ($page->is_admin) { ?>
                <?php $this->echo_admin_content(); ?>
            <?php } else { /* Not admin page */?>
                <?php $this->echo_page_content(); ?>
            <?php } /* End of Not admin page */ ?>
        <?php } /* End of Not login form. */ ?>

        <?php // End of view template
    }
    
    function echo_footer() {
        $version = FIREPAGE_VERSION;
        $theme_label = '';
        if ($this->controller->app->theme !== null) {
            $theme_label = " with <b>" . $this->controller->app->theme . "</b> theme";
        }
        
        ?>
        <div class="footer">
            <p>Powered by <a href="https://github.com/zemian/firepage">FirePage <?php echo $version ?></a>
                <?php echo $theme_label ?></p>
        </div>
        
        </body>
        </html>
        <?php
    }
}

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

//
// ### Main App Entry
//
class FirePageApp {
    public $config = [];
    public $theme_name = null;
    public $theme = null; // theme instance
    public $plugin_names = null;
    public $plugins = null; // plugin instances
    
    function read_config($config_file) {
        $json = file_get_contents($config_file);
        $config = json_decode($json, true);
        if ($config === null) {
            die("Invalid config JSON file: $config_file");
        }

        return $config;
    }

    function create_plugins() {
        $this->plugin_names = $this->config['plugins'] ?? null; // plugins list
        if ($this->plugin_names !== null) {
            $this->plugins = array();
            // Invoke plugins/<plugin_name>/<plugin_name>.php if exists
            foreach ($this->plugin_names as $plugin) {
                $plugin_file = FIREPAGE_PLUGINS_DIR . "/{$plugin}/{$plugin}.php";
                if (file_exists($plugin_file)) {
                    require_once $plugin_file;
                    $plugin_class_name = "{$plugin}Plugin";
                    if (class_exists($plugin_class_name)) {
                        $plugin_obj = new $plugin_class_name();
                        array_push($this->plugins, $plugin_obj);
                    }
                }
            }
        }
    }

    function create_theme() {
        $this->theme_name = $this->config['theme'] ?? null; // theme name
        // Invoke theme/<theme_name>/<theme_name>.php if exists
        if ($this->theme_name !== null) {
            $theme_name = $this->theme_name;
            $theme_file = FIREPAGE_THEMES_DIR . "/{$theme_name}/{$theme_name}.php";
            if (file_exists($theme_file)) {
                require_once $theme_file;
                $theme_class_name = "{$theme_name}Theme";
                if (class_exists($theme_class_name)) {
                    $this->theme = new $theme_class_name();
                }
            }
        }
    }

    function run () {
        // Read in external config file for override
        $config_file = getenv(FIREPAGE_CONFIG_ENV_KEY) ?: (FIREPAGE_DEAFULT_ROOT_DIR . "/" . FIREPAGE_CONFIG_NAME);
        if (file_exists($config_file)) {
            $this->config = $this->read_config($config_file);
        }

        $this->create_plugins();
        $this->create_theme();
        
        // Instantiate app controller and process the request
        $controller_class = $this->config['controller_class'] ?? 'FirePageController';
        $controller = new $controller_class($this);
        $controller->init();
        $view = $controller->process_request();
        if ($view !== null) {
            $view->echo_header();
            $view->echo_body_content();
            $view->echo_footer();
        }
        $controller->destroy();
    }
}

// Run it
$app = new FirePageApp();
$app->run();
