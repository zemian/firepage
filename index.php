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
    protected $config;
    protected $root_dir;
    protected $title;
    protected $admin_password;
    protected $root_menu_label;
    protected $max_menu_levels;
    protected $default_dir_name;
    protected $default_file_name;
    protected $default_admin_file_name;
    protected $file_extension_list;
    protected $exclude_file_list;
    protected $files_to_menu_links;
    protected $pretty_file_to_label;
    protected $menu_links;
    protected $theme;
    
    function __construct($config) {
        $this->config = $config;

        // Init config parameters into class properties
        $this->root_dir = ($config['root_dir'] ?? '') ?: FIREPAGE_DEAFULT_ROOT_DIR;
        $this->title = $config['title'] ?? 'FirePage';
        $this->admin_password = $config['admin_password'] ?? '';
        $this->root_menu_label = $config['root_menu_label'] ?? 'Pages';
        $this->max_menu_levels = $config['max_menu_levels'] ?? 2;
        $this->default_dir_name = $config['default_dir_name'] ?? '';
        $this->default_file_name = $config['default_file_name'] ?? 'home.html';
        $this->default_admin_file_name = $config['default_admin_file_name'] ?? 'admin-home.html';
        $this->file_extension_list = $config['file_extension_list'] ?? ['.html', '.txt'];
        $this->exclude_file_list = $config['exclude_file_list'] ?? ['plugins', 'themes', 'admin-home.html'];
        $this->files_to_menu_links = $config['files_to_menu_links'] ?? [];
        $this->pretty_file_to_label = $config['pretty_file_to_label'] ?? false;

        // Optional config params that defaut to null values if not set
        $this->menu_links = $config['menu_links'] ?? null;
        $this->theme = $config['theme'] ?? null;
    }
    
    function init() {
        // Do nothing for now.
    }
    
    function destroy() {
        // Do nothing for now.
    }

    /**
     * It looks for script named <page_name>.php, or page-<page_ext>.php in theme to process request. If the script
     * exist, it should return page object for view to render, else returns null response has already been
     * processed. (no view needed).
     * 
     * NOTE: The $page argument is passed by ref and it's modifiable.
     */
    function process_theme_request(&$page) {
        $ret = false;
        // This $app variables will be expose to theme.
        $app = $this;
        $page->parent_url_path = dirname($page->url_path);
        $page->theme_url = $page->parent_url_path . "themes/" . $app->theme;
        if ($app->theme !== null) {
            $page_admin_file = FIREPAGE_THEMES_DIR . "/{$app->theme}/admin-page.php";
            if ($page->is_admin && file_exists($page_admin_file)) {
                // Process admin page
                $ret = require_once $page_admin_file;
            } else {
                $page_ext = pathinfo($page->page_name, PATHINFO_EXTENSION);
                $page_name_file = FIREPAGE_THEMES_DIR . "/{$app->theme}/{$page->page_name}.php";
                $page_ext_file = FIREPAGE_THEMES_DIR . "/{$app->theme}/page-{$page_ext}.php";
                $page_file = FIREPAGE_THEMES_DIR . "/{$app->theme}/page.php";

                if (file_exists($page_name_file)) {
                    // Process by page name
                    $ret = require_once $page_name_file;
                } else if (file_exists($page_ext_file)) {
                    // Process by page extension
                    $ret = require_once $page_ext_file;
                } else if (file_exists($page_file)) {
                    // Process by explicit 'page.php'
                    $ret = require_once $page_file;
                }
            }
        }
        return $ret;
    }

    /** 
     * Return a View object that can render UI output. It may return NULL if no view is needed (or Theme handle their
     * own output. 
     */
    function process_request() {
        
        // Page properties

        $page = new FirePageContext($this);
        
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
                    $page->file_content = $this->get_file_content($page->page_name);
                }
            } else {
                // GET - Not Admin Actions
                if ($action === 'page'){
                    $page->file_content = $this->get_file_content($page->page_name);
                } else {
                    die("Unknown action=$action");
                }
            }
        }
        
        // Let Theme process request that can modify $page object if needed.
        $this->process_theme_request($page);
        if ($page->no_view) {
            return null;
        }
        
        return $this->create_view($page);
    }
    
    function create_view($page) {
        return new FirePageView($this, $page);
    }

    function transform_content($file, $content) {
        if ($content === '') {
            $content = '<i>This page is empty!</i>';
        }

        if (!FirePageUtils::ends_with($file, '.html')) {
            $content = "<pre>" . htmlentities($content) . "</pre>";
        }
        
        return $content;
    }

    function get_file_content($file) {
        if($this->exists($file) &&
            $this->validate_note_name($file, false) === null) {
            $content = $this->read($file);
            return $this->transform_content($file, $content);
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

    function is_password_enabled() {
        return $this->admin_password !== '';
    }

    function is_logged_in() {
        return $this->get_admin_session() !== null;
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

}

/** A Page context/map to store any data for View to use. */
class FirePageContext {
    protected $app;
    protected $action;
    protected $page_name;
    protected $is_admin;
    protected $url_path;
    protected $file_content;
    protected $controller_url;
    protected $no_view = false;
    protected $form_error = null;
    protected $delete_status = null;
    
    function __construct($app) {
        $this->app = $app;
        
        // Init properties from query params
        $this->action = $_GET['action'] ?? 'page'; // Default action is to GET page
        $this->is_admin = isset($_GET['admin']);
        $this->page_name = $_GET['page'] ?? $app->default_file_name;
        $this->url_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $this->controller_url = $this->url_path . '?' . ($this->is_admin ? 'admin=true&' : '');

        // Set default admin page if exists
        if ($this->is_admin && !isset($_GET['page']) && $app->exists($app->default_admin_file_name)) {
            $this->page_name = $app->default_admin_file_name;
        }
    }
}

/** A View class that will render default theme UI. */
class FirePageView {
    protected $app;
    protected $page;

    function __construct($app, $page) {
        $this->app = $app;
        $this->page = $page;
    }

    function get_menu_links() {
        $app = $this->app;        
        $page = $this->page;
        $menu_links = null;
        if ($page->is_admin) {
            $menu_links = $app->get_menu_links_tree($this->app->default_dir_name, $this->app->max_menu_levels);
        } else {
            if ($app->menu_links !== null) {
                // If config has manually given menu_links, return just that.
                $menu_links = $app->menu_links;
            } else {
                // Else, auto generate menu_links based on dirs/files listing
                $menu_links = $app->get_menu_links_tree($this->app->default_dir_name, $this->app->max_menu_levels);
                $app->remap_menu_links($menu_links);
            }
            $app->sort_menu_links($menu_links);
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
        $controller = $page->controller_url;
        $i = 0; // Use to track last item in loop
        $files_len = count($menu_links['links']);
        foreach ($menu_links['links'] as $link) {
            $label = $link['label'];
            $url = $link['url'];
            $is_active = ($url === $active_file) ? "is-active": "";
            echo "<li><a class='$is_active' href='{$controller}page=$url'>$label</a>";
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
        $title = $this->app->title;
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
        $app = $this->app;
        $page = $this->page;
        ?>
        <div class="navbar">
            <div class="navbar-brand">
                <div class="navbar-item">
                    <a class="title" href='<?php echo $page->controller_url; ?>'><?php echo $app->title; ?></a>
                </div>
            </div>
            <div class="navbar-end">
                <?php if ($page->is_admin && $page->action === 'page' && (!$app->is_password_enabled() || $app->is_logged_in())) { ?>
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
        $app = $this->app;
        $page = $this->page;
        ?>
        <div class="navbar">
            <div class="navbar-brand">
                <div class="navbar-item">
                    <a class="title" href='<?php echo $page->controller_url; ?>'><?php echo $app->title; ?></a>
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
        $app = $this->app;
        $page = $this->page;
        ?>
        <section class="section">
            <div class="columns">
                <div class="column is-3 menu">
                    <p class="menu-label">Admin</p>
                    <ul class="menu-list">
                        <li><a href='<?php echo $page->controller_url; ?>action=new'>New</a></li>

                        <?php if ($app->is_logged_in()) { ?>
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
        $app = $this->app;
        $page = $this->page;
        
        // Start of view template
        ?>
        
        <?php $this->echo_navbar(); ?>
        <?php if ($page->is_admin && ($app->is_password_enabled() && !$app->is_logged_in())) {?>
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
        if ($this->app->theme !== null) {
            $theme_label = " with <b>" . $this->app->theme . "</b> theme";
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
}
//
// ### Main App Entry
//
class FirePage {
    protected $config = [];
    protected $theme = null;
    protected $plugins = [];
    
    function read_config($config_file) {
        $json = file_get_contents($config_file);
        $config = json_decode($json, true);
        if ($config === null) {
            die("Invalid config JSON file: $config_file");
        }

        return $config;
    }

    function init_plugins() {
        $this->plugins = $this->config['plugins'] ?? []; // plugins list
        // Invoke plugins/<plugin_name>/<plugin_name>.php if exists
        foreach ($this->plugins as $plugin) {
            $plugin_file = FIREPAGE_PLUGINS_DIR . "/{$plugin}/{$plugin}.php";
            if (file_exists($plugin_file)) {
                require_once $plugin_file;
            }
        }
    }

    function init_theme() {
        $this->theme = $this->config['theme'] ?? null; // theme name
        // Invoke theme/<theme_name>/<theme_name>.php if exists
        $theme = $this->theme;
        if ($theme !== null) {
            $theme_file = FIREPAGE_THEMES_DIR . "/{$theme}/{$theme}.php";
            if (file_exists($theme_file)) {
                require_once $theme_file;
            }
        }
    }

    function run () {
        // Read in external config file for override
        $config_file = getenv(FIREPAGE_CONFIG_ENV_KEY) ?: (FIREPAGE_DEAFULT_ROOT_DIR . "/" . FIREPAGE_CONFIG_NAME);
        if (file_exists($config_file)) {
            $this->config = $this->read_config($config_file);
        }

        $this->init_plugins();
        $this->init_theme();
        
        // Instantiate app controller and process the request
        $config = $this->config;
        $app_controller_class = $config['app_controller_class'] ?? 'FirePageController';
        $app = new $app_controller_class($config);
        $app->init();
        $view = $app->process_request();
        if ($view !== null) {
            $view->echo_header();
            $view->echo_body_content();
            $view->echo_footer();
        }
        $app->destroy();
    }
}

// Run it
$main = new FirePage();
$main->run();
