<?php
/**
 * MarkNotes is a single `index.php` page application for managing Markdown notes.
 *
 * Project Home: https://github.com/zemian/marknotes
 * License: The MIT License (MIT)
 * Author: Zemian Deng
 */

//
// ## MarkNotes
//

// Global Vars
define('MARKNOTES_VERSION', '1.3.0');
define('MARKNOTES_CONFIG_ENV_KEY', 'MARKNOTES_CONFIG');
define('MARKNOTES_CONFIG_NAME', '.marknotes.json');
define('MARKNOTES_DEAFULT_ROOT_DIR', __DIR__);

//
// ### The MarkNotes Application
// 
class MarkNotesApp {
    
    function __construct() {
        // Read in external config file for override
        $config_file = getenv(MARKNOTES_CONFIG_ENV_KEY) ?: (MARKNOTES_DEAFULT_ROOT_DIR . "/" . MARKNOTES_CONFIG_NAME);
        $config = $this->read_config($config_file);

        // Config parameters
        $this->root_dir = ($config['root_dir'] ?? '') ?: MARKNOTES_DEAFULT_ROOT_DIR;
        $this->title = $config['title'] ?? 'MarkNotes';
        $this->admin_password = $config['admin_password'] ?? '';
        $this->root_menu_label = $config['root_menu_label'] ?? 'Notes';
        $this->max_menu_levels = $config['max_menu_levels'] ?? 2;
        $this->default_dir_name = $config['default_dir_name'] ?? '';
        $this->default_file_name = $config['default_file_name'] ?? 'readme.md';
        $this->file_extension_list = $config['file_extension_list'] ?? ['.md'];
        $this->exclude_file_list = $config['exclude_file_list'] ?? [];
        $this->menu_links = $config['menu_links'] ?? null;
        $this->files_to_menu_links = $config['files_to_menu_links'] ?? [];
        $this->pretty_file_to_label = $config['pretty_file_to_label'] ?? false;
        
        // Init service
        $this->init_parsedown();
        
        // Page property
        $this->page = new class ($this) {
            function __construct($app) {
                $this->action = $_GET['action'] ?? 'file'; // Default action is to GET file
                $this->file = $_GET['file'] ?? $app->default_file_name;
                $this->notes_dir = $_GET['notes_dir'] ?? $app->default_dir_name;
                $this->is_admin = isset($_GET['admin']);
                $this->url_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
                $this->file_content = null;
                $this->form_error = null;
                $this->delete_status = null;

                // Calculate controller path
                $this->controller = $this->url_path . '?' . ($this->is_admin ? 'admin=true&' : '');
                // If notes dir is not default, ensure we retain it on next request in the controller.
                if ($this->notes_dir !== $app->default_dir_name) {
                    $this->controller .= "notes_dir={$this->notes_dir}&";
                }
            }
        };
    }
    
    function init_parsedown() {
        $this->parsedown = new ParsedownExtra();        
    }

    function process_request() {
        // If this is admin request, and if password is enabled start session
        if ($this->page->is_admin && $this->is_password_enabled()) {
            if (!session_start()) {
                die("Unable to create PHP session!");
            }
            
            // If admin session is not set, then force action to be login first.
            if (!$this->is_logged_in()) {
                $this->page->action = "login";
            }
        }
        
        // Process Request and return $this->page var
        $action = $this->page->action;

        // Process POST requests
        if (isset($_POST['action'])) {
            $this->page->action = $_POST['action'];

            $action = $this->page->action;
            if ($action === 'login_submit') {
                $password = $_POST['password'];
                $admin_password = $this->admin_password;
                
                $this->page->form_error = $this->login($password, $admin_password);
                if ($this->page->form_error === null) {
                    $this->redirect($this->page->controller);
                }
            } else if ($action === 'new_submit' || $action === 'edit_submit') {
                $this->page->file = $_POST['file'];
                $this->page->file_content = $_POST['file_content'];
                
                $file = $this->page->file;
                $file_content = $this->page->file_content;
                if ($this->page->form_error === null) {
                    $is_exists_check = $action === 'new_submit';
                    $this->page->form_error = $this->validate_note_name($file, $is_exists_check);
                }
                if ($this->page->form_error === null) {
                    $this->page->form_error = $this->validate_note_content($file_content);
                }
                if ($this->page->form_error === null) {
                    $this->write($file, $file_content);
                    $this->redirect($this->page->controller . "file=$file");
                }
            } else {
                die("Unknown action=$action");
            }
        } else {
            // Process GET request
            $is_admin = $this->page->is_admin;
            if ($is_admin) {                
                if ($action === 'new') {
                    $this->page->file = '';
                    $this->page->file_content = '';
                } else if ($action === 'edit') {
                    // Process GET Edit Form
                    $file = $this->page->file;
                    if ($this->exists($file)) {
                        $this->page->file_content = $this->read($file);
                    } else {
                        $this->page->file_content = "File not found: $file";
                    }
                } else if ($action === 'delete-confirmed') {
                    // Process GET - DELETE file
                    $file = $this->page->file;
                    $delete_status = &$this->page->delete_status; // Use Ref
                    if ($this->delete($file)) {
                        $delete_status = "File $file deleted";
                    } else {
                        $delete_status = "File not found: $file";
                    }
                } else if ($action === 'logout') {
                    $this->logout();
                    $this->redirect($this->page->controller);
                } else if ($action === 'file') {
                    $this->page->file_content = $this->get_file_content($this->page->file);
                }
            } else {
                // GET - Not Admin Actions
                if ($action === 'file'){
                    $this->page->file_content = $this->get_file_content($this->page->file);
                } else {
                    die("Unknown action=$action");
                }
            }
        }
        
        return $this->page;
    }
    
    function transform_content($file, $content) {
        if ($this->ends_with($file, '.md') || $this->ends_with($file, '.markdown')) {
            return $this->convert_to_markdown($content);
        } else if ($this->ends_with($file, '.txt')) {
            return "<pre>" . htmlentities($content) . "</pre>";
        } else if ($this->ends_with($file, '.json')) {
            return "<pre>" . $content . "</pre>";
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
    
    function ends_with($str, $sub_str) {
        $len = strlen($sub_str);
        return substr_compare($str, $sub_str, -$len) === 0;
    }

    function starts_with($str, $sub_str) {
        $len = strlen($sub_str);
        return substr_compare($str, $sub_str, 0, $len) === 0;
    }
    
    function is_file_excluded($dir_file) {
        if (count($this->exclude_file_list) > 0) {
            foreach($this->exclude_file_list as $exclude) {
                // Exclude in relative to the root_dir
                if ($this->starts_with("$dir_file", "$this->root_dir/$exclude")) {
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
            if (is_file($dir_file) && $file !== MARKNOTES_CONFIG_NAME) {
                if ($this->is_file_excluded($dir_file)) {
                    continue;
                }
                foreach ($this->file_extension_list as $ext) {
                    if (!$this->starts_with($file, '.') && $this->ends_with($file, $ext)) {
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
                if (!$this->starts_with($file, '.') && !$this->is_file_excluded($dir_file)) {
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
    
    function read_config($config_file) {
        if (file_exists($config_file)) {
            $json = file_get_contents($config_file);
            $config = json_decode($json, true);
            if ($config === null) {
                die("Invalid config JSON file: $config_file");
            }
            
            return $config;
        }
        return array();
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
    
    function convert_to_markdown($plain_text) {
        return $this->parsedown->text($plain_text);
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
        } else if (preg_match('/' . MARKNOTES_CONFIG_NAME . '$/', $name)) {
            $error .= "Must not be reversed file: " . MARKNOTES_CONFIG_NAME;
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
            $file = $link['file'];
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
    
    function get_menu_links() {
        $menu_links = null;
        if ($this->page->is_admin) {
            $menu_links = $this->get_menu_links_tree($this->page->notes_dir, $this->max_menu_levels);
        } else {
            if ($this->menu_links !== null) {
                // If config has manually given menu_links, return just that.
                $menu_links = $this->menu_links;
            } else {
                // Else, auto generate menu_links bsaed on dirs/files listing
                $menu_links = $this->get_menu_links_tree($this->page->notes_dir, $this->max_menu_levels);
                $this->remap_menu_links($menu_links);
            }
            $this->sort_menu_links($menu_links);
        }
        return $menu_links;
    }
    
    function get_link_label($file) {
        if (!$this->pretty_file_to_label) {
            return $file;
        }
        $label = pathinfo($file, PATHINFO_FILENAME);
        $label = preg_replace('/([A-Z])/', " $1", $label);
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
        $menu_links['menu_label'] = $this->get_link_label($dir_name);
        $menu_links['menu_name'] = $dir;

        $files = $this->get_files($dir);
        $i = 1;
        foreach ($files as $file) {
            $file_path = ($dir === '') ? $file : "$dir/$file";
            array_push($menu_links['links'], array(
                'file' => $file_path,
                'order' => $i++,
                'label' => $this->get_link_label($file)
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

    function echo_menu_links($menu_links) {
        echo "<p class='menu-label'>{$menu_links['menu_label']}</p>";
        echo "<ul class='menu-list'>";

        $active_file = $this->page->file;
        $controller = $this->page->controller;
        $i = 0; // Use to track last item in loop
        $files_len = count($menu_links['links']);
        foreach ($menu_links['links'] as $link) {
            $file = $link['file'];
            $label = $link['label'];
            $path_name = $this->page->notes_dir ? "$this->page->notes_dir/$file" : $file;
            $is_active = ($path_name === $active_file) ? "is-active": "";
            echo "<li><a class='$is_active' href='{$controller}file=$path_name'>$label</a>";
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
    
    function echo_content($content) {
        if ($content === '') {
            echo "<i>This note is empty!</i>";
        } else {
            echo $content;
        }
    }
}

//
// ### App Entry
// - Note that you must not generate any output before starting the PHP session!
//
$app = new MarkNotesApp();
$page = $app->process_request();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="https://unpkg.com/bulma">

    <?php if ($page->is_admin && ($page->action === 'new' || $page->action === 'edit')) { ?>
        <link rel="stylesheet" href="https://unpkg.com/codemirror@5.58.2/lib/codemirror.css">
    <?php } ?>
    
    <title><?php echo $app->title; ?></title>
</head>
<body>

<?php if ($page->is_admin) { ?>
    <div class="navbar">
        <div class="navbar-brand">
            <div class="navbar-item">
                <a class="title" href='<?php echo $page->controller; ?>'><?php echo $app->title; ?></a>
            </div>
        </div>
        <div class="navbar-end">
            <?php if ($page->is_admin && $page->action === 'file' && (!$app->is_password_enabled() || $app->is_logged_in())) { ?>
            <div class="navbar-item">
                <a href="<?php echo $page->controller; ?>action=edit&file=<?= $page->file ?>">EDIT</a>
            </div>
            <div class="navbar-item">
                <a href="<?php echo $page->controller; ?>action=delete&file=<?= $page->file ?>">DELETE</a>
            </div>
            <?php } ?>
        </div>
    </div>
<?php } ?>

<?php if ($page->is_admin && ($app->is_password_enabled() && !$app->is_logged_in())) {?>
    <section class="section">
        <?php if ($page->form_error !== null) { ?>
        <div class="notification is-danger"><?php echo $page->form_error; ?></div>
        <?php } ?>
        <div class="level">
            <div class="level-item has-text-centered">
                <form method="POST" action="<?php echo $page->controller; ?>">
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
<?php } else { /* Not login form. */ ?>
    <?php if ($page->is_admin) { ?>
        <section class="section">
            <div class="columns">
                <div class="column is-3 menu">
                    <p class="menu-label">Admin</p>
                    <ul class="menu-list">
                        <li><a href='<?php echo $page->controller; ?>action=new'>New</a></li>
        
                        <?php if ($app->is_logged_in()) { ?>
                            <li><a href='<?php echo $page->controller . "action=logout"; ?>'>Logout</a></li>
                        <?php } else { ?>
                            <li><a href='<?php echo $page->url_path; ?>'>Exit</a></li>
                        <?php } ?>
                    </ul>
        
                    <?php $app->echo_menu_links($app->get_menu_links()); ?>
                </div>
                <div class="column is-9">
                    <?php if ($page->action === 'new' || $page->action === 'new_submit') { ?>
                        <?php if ($page->form_error !== null) { ?>
                            <div class="notification is-danger"><?php echo $page->form_error; ?></div>
                        <?php } ?>
                        <form method="POST" action="<?php echo $page->controller; ?>">
                            <input type="hidden" name="action" value="new_submit">
                            <div class="field">
                                <div class="label">File Name</div>
                                <div class="control"><input id='file_name' class="input" type="text" name="file" value="<?php echo $page->file ?>"></div>
                            </div>
                            <div class="field">
                                <div class="label">Markdown</div>
                                <div class="control"><textarea id='file_content' class="textarea" rows="20" name="file_content"><?php echo $page->file_content ?></textarea></div>
                            </div>
                            <div class="field">
                                <div class="control">
                                    <input class="button is-info" type="submit" name="submit" value="Create">
                                    <a class="button" href="<?php echo $page->controller; ?>">Cancel</a>
                                </div>
                            </div>
                        </form>
                    <?php } else if ($page->action === 'edit' || $page->action === 'edit_submit') { ?>
                        <form method="POST" action="<?php echo $page->controller; ?>">
                            <input type="hidden" name="action" value="edit_submit">
                            <div class="field">
                                <div class="label">File Name</div>
                                <div class="control"><input id='file_name' class="input" type="text" name="file" value="<?= $page->file ?>"></div>
                            </div>
                            <div class="field">
                                <div class="label">Markdown</div>
                                <div class="control"><textarea id='file_content' class="textarea" rows="20" name="file_content"><?= $page->file_content ?></textarea></div>
                            </div>
                            <div class="field">
                                <div class="control">
                                    <input class="button is-info" type="submit" name="submit" value="Update">
                                    <a class="button" href="<?php echo $page->controller; ?>file=<?= $page->file ?>">Cancel</a>
                                </div>
                            </div>
                        </form>
                    <?php } else if ($page->action === 'delete') { ?>
                        <div class="message is-danger">
                            <div class="message-header">Delete Confirmation</div>
                            <div class="message-body">
                                <p class="block">Are you sure you want to delete <b><?= $page->file ?></b>?</p>
        
                                <a class="button is-info" href="<?php echo $page->controller; ?>action=delete-confirmed&file=<?= $page->file ?>">Delete</a>
                                <a class="button" href="<?php echo $page->controller; ?>file=<?= $page->file ?>">Cancel</a>
                            </div>
                        </div>
                    <?php } else if ($page->action === 'delete-confirmed') { ?>
                        <div class="message is-success">
                            <div class="message-header">Deleted!</div>
                            <div class="message-body">
                                <p class="block"><?php echo $page->delete_status; ?></p>
                            </div>
                        </div>
                    <?php } else if ($page->action === 'file') { ?>
                        <div class="content">
                            <?php $app->echo_content($page->file_content); ?>
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
            <script src="https://unpkg.com/codemirror@5.58.2/lib/codemirror.js"></script>
            <script src="https://unpkg.com/codemirror@5.58.2/addon/mode/overlay.js"></script>
            <script src="https://unpkg.com/codemirror@5.58.2/mode/javascript/javascript.js"></script>
            <script src="https://unpkg.com/codemirror@5.58.2/mode/css/css.js"></script>
            <script src="https://unpkg.com/codemirror@5.58.2/mode/xml/xml.js"></script>
            <script src="https://unpkg.com/codemirror@5.58.2/mode/htmlmixed/htmlmixed.js"></script>
            <script src="https://unpkg.com/codemirror@5.58.2/mode/markdown/markdown.js"></script>
            <script src="https://unpkg.com/codemirror@5.58.2/mode/gfm/gfm.js"></script>
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
    <?php } else { /* Not admin page */?>
        <section class="section">
            <div class="columns">
                <div class="column is-3 menu">
                    <?php $app->echo_menu_links($app->get_menu_links()); ?>
                </div>
                <div class="column is-9">
                    <div class="content">
                        <?php $app->echo_content($page->file_content); ?>
                    </div>
                </div>
            </div>
        </section>
    <?php } /* End of Not admin page */ ?>
<?php } /* End of Not login form. */ ?>

<div class="footer">
    <p>Powered by <a href="https://github.com/zemian/marknotes">MarkNotes <?php echo MARKNOTES_VERSION; ?></a></p>
</div>

</body>
</html>

<?php
//
// ## Libraries
// - We embed library into single index.php to make things simple.
//

// 
// ### Parsedown
//
#
#
# Parsedown
# http://parsedown.org
#
# (c) Emanuil Rusev
# http://erusev.com
#
# For the full license information, view the LICENSE file that was distributed
# with this source code.
#
#

class Parsedown
{
    # ~

    const version = '1.7.4';

    # ~

    function text($text)
    {
        # make sure no definitions are set
        $this->DefinitionData = array();

        # standardize line breaks
        $text = str_replace(array("\r\n", "\r"), "\n", $text);

        # remove surrounding line breaks
        $text = trim($text, "\n");

        # split text into lines
        $lines = explode("\n", $text);

        # iterate through lines to identify blocks
        $markup = $this->lines($lines);

        # trim line breaks
        $markup = trim($markup, "\n");

        return $markup;
    }

    #
    # Setters
    #

    function setBreaksEnabled($breaksEnabled)
    {
        $this->breaksEnabled = $breaksEnabled;

        return $this;
    }

    protected $breaksEnabled;

    function setMarkupEscaped($markupEscaped)
    {
        $this->markupEscaped = $markupEscaped;

        return $this;
    }

    protected $markupEscaped;

    function setUrlsLinked($urlsLinked)
    {
        $this->urlsLinked = $urlsLinked;

        return $this;
    }

    protected $urlsLinked = true;

    function setSafeMode($safeMode)
    {
        $this->safeMode = (bool) $safeMode;

        return $this;
    }

    protected $safeMode;

    protected $safeLinksWhitelist = array(
        'http://',
        'https://',
        'ftp://',
        'ftps://',
        'mailto:',
        'data:image/png;base64,',
        'data:image/gif;base64,',
        'data:image/jpeg;base64,',
        'irc:',
        'ircs:',
        'git:',
        'ssh:',
        'news:',
        'steam:',
    );

    #
    # Lines
    #

    protected $BlockTypes = array(
        '#' => array('Header'),
        '*' => array('Rule', 'List'),
        '+' => array('List'),
        '-' => array('SetextHeader', 'Table', 'Rule', 'List'),
        '0' => array('List'),
        '1' => array('List'),
        '2' => array('List'),
        '3' => array('List'),
        '4' => array('List'),
        '5' => array('List'),
        '6' => array('List'),
        '7' => array('List'),
        '8' => array('List'),
        '9' => array('List'),
        ':' => array('Table'),
        '<' => array('Comment', 'Markup'),
        '=' => array('SetextHeader'),
        '>' => array('Quote'),
        '[' => array('Reference'),
        '_' => array('Rule'),
        '`' => array('FencedCode'),
        '|' => array('Table'),
        '~' => array('FencedCode'),
    );

    # ~

    protected $unmarkedBlockTypes = array(
        'Code',
    );

    #
    # Blocks
    #

    protected function lines(array $lines)
    {
        $CurrentBlock = null;

        foreach ($lines as $line)
        {
            if (chop($line) === '')
            {
                if (isset($CurrentBlock))
                {
                    $CurrentBlock['interrupted'] = true;
                }

                continue;
            }

            if (strpos($line, "\t") !== false)
            {
                $parts = explode("\t", $line);

                $line = $parts[0];

                unset($parts[0]);

                foreach ($parts as $part)
                {
                    $shortage = 4 - mb_strlen($line, 'utf-8') % 4;

                    $line .= str_repeat(' ', $shortage);
                    $line .= $part;
                }
            }

            $indent = 0;

            while (isset($line[$indent]) and $line[$indent] === ' ')
            {
                $indent ++;
            }

            $text = $indent > 0 ? substr($line, $indent) : $line;

            # ~

            $Line = array('body' => $line, 'indent' => $indent, 'text' => $text);

            # ~

            if (isset($CurrentBlock['continuable']))
            {
                $Block = $this->{'block'.$CurrentBlock['type'].'Continue'}($Line, $CurrentBlock);

                if (isset($Block))
                {
                    $CurrentBlock = $Block;

                    continue;
                }
                else
                {
                    if ($this->isBlockCompletable($CurrentBlock['type']))
                    {
                        $CurrentBlock = $this->{'block'.$CurrentBlock['type'].'Complete'}($CurrentBlock);
                    }
                }
            }

            # ~

            $marker = $text[0];

            # ~

            $blockTypes = $this->unmarkedBlockTypes;

            if (isset($this->BlockTypes[$marker]))
            {
                foreach ($this->BlockTypes[$marker] as $blockType)
                {
                    $blockTypes []= $blockType;
                }
            }

            #
            # ~

            foreach ($blockTypes as $blockType)
            {
                $Block = $this->{'block'.$blockType}($Line, $CurrentBlock);

                if (isset($Block))
                {
                    $Block['type'] = $blockType;

                    if ( ! isset($Block['identified']))
                    {
                        $Blocks []= $CurrentBlock;

                        $Block['identified'] = true;
                    }

                    if ($this->isBlockContinuable($blockType))
                    {
                        $Block['continuable'] = true;
                    }

                    $CurrentBlock = $Block;

                    continue 2;
                }
            }

            # ~

            if (isset($CurrentBlock) and ! isset($CurrentBlock['type']) and ! isset($CurrentBlock['interrupted']))
            {
                $CurrentBlock['element']['text'] .= "\n".$text;
            }
            else
            {
                $Blocks []= $CurrentBlock;

                $CurrentBlock = $this->paragraph($Line);

                $CurrentBlock['identified'] = true;
            }
        }

        # ~

        if (isset($CurrentBlock['continuable']) and $this->isBlockCompletable($CurrentBlock['type']))
        {
            $CurrentBlock = $this->{'block'.$CurrentBlock['type'].'Complete'}($CurrentBlock);
        }

        # ~

        $Blocks []= $CurrentBlock;

        unset($Blocks[0]);

        # ~

        $markup = '';

        foreach ($Blocks as $Block)
        {
            if (isset($Block['hidden']))
            {
                continue;
            }

            $markup .= "\n";
            $markup .= isset($Block['markup']) ? $Block['markup'] : $this->element($Block['element']);
        }

        $markup .= "\n";

        # ~

        return $markup;
    }

    protected function isBlockContinuable($Type)
    {
        return method_exists($this, 'block'.$Type.'Continue');
    }

    protected function isBlockCompletable($Type)
    {
        return method_exists($this, 'block'.$Type.'Complete');
    }

    #
    # Code

    protected function blockCode($Line, $Block = null)
    {
        if (isset($Block) and ! isset($Block['type']) and ! isset($Block['interrupted']))
        {
            return;
        }

        if ($Line['indent'] >= 4)
        {
            $text = substr($Line['body'], 4);

            $Block = array(
                'element' => array(
                    'name' => 'pre',
                    'handler' => 'element',
                    'text' => array(
                        'name' => 'code',
                        'text' => $text,
                    ),
                ),
            );

            return $Block;
        }
    }

    protected function blockCodeContinue($Line, $Block)
    {
        if ($Line['indent'] >= 4)
        {
            if (isset($Block['interrupted']))
            {
                $Block['element']['text']['text'] .= "\n";

                unset($Block['interrupted']);
            }

            $Block['element']['text']['text'] .= "\n";

            $text = substr($Line['body'], 4);

            $Block['element']['text']['text'] .= $text;

            return $Block;
        }
    }

    protected function blockCodeComplete($Block)
    {
        $text = $Block['element']['text']['text'];

        $Block['element']['text']['text'] = $text;

        return $Block;
    }

    #
    # Comment

    protected function blockComment($Line)
    {
        if ($this->markupEscaped or $this->safeMode)
        {
            return;
        }

        if (isset($Line['text'][3]) and $Line['text'][3] === '-' and $Line['text'][2] === '-' and $Line['text'][1] === '!')
        {
            $Block = array(
                'markup' => $Line['body'],
            );

            if (preg_match('/-->$/', $Line['text']))
            {
                $Block['closed'] = true;
            }

            return $Block;
        }
    }

    protected function blockCommentContinue($Line, array $Block)
    {
        if (isset($Block['closed']))
        {
            return;
        }

        $Block['markup'] .= "\n" . $Line['body'];

        if (preg_match('/-->$/', $Line['text']))
        {
            $Block['closed'] = true;
        }

        return $Block;
    }

    #
    # Fenced Code

    protected function blockFencedCode($Line)
    {
        if (preg_match('/^['.$Line['text'][0].']{3,}[ ]*([^`]+)?[ ]*$/', $Line['text'], $matches))
        {
            $Element = array(
                'name' => 'code',
                'text' => '',
            );

            if (isset($matches[1]))
            {
                /**
                 * https://www.w3.org/TR/2011/WD-html5-20110525/elements.html#classes
                 * Every HTML element may have a class attribute specified.
                 * The attribute, if specified, must have a value that is a set
                 * of space-separated tokens representing the various classes
                 * that the element belongs to.
                 * [...]
                 * The space characters, for the purposes of this specification,
                 * are U+0020 SPACE, U+0009 CHARACTER TABULATION (tab),
                 * U+000A LINE FEED (LF), U+000C FORM FEED (FF), and
                 * U+000D CARRIAGE RETURN (CR).
                 */
                $language = substr($matches[1], 0, strcspn($matches[1], " \t\n\f\r"));

                $class = 'language-'.$language;

                $Element['attributes'] = array(
                    'class' => $class,
                );
            }

            $Block = array(
                'char' => $Line['text'][0],
                'element' => array(
                    'name' => 'pre',
                    'handler' => 'element',
                    'text' => $Element,
                ),
            );

            return $Block;
        }
    }

    protected function blockFencedCodeContinue($Line, $Block)
    {
        if (isset($Block['complete']))
        {
            return;
        }

        if (isset($Block['interrupted']))
        {
            $Block['element']['text']['text'] .= "\n";

            unset($Block['interrupted']);
        }

        if (preg_match('/^'.$Block['char'].'{3,}[ ]*$/', $Line['text']))
        {
            $Block['element']['text']['text'] = substr($Block['element']['text']['text'], 1);

            $Block['complete'] = true;

            return $Block;
        }

        $Block['element']['text']['text'] .= "\n".$Line['body'];

        return $Block;
    }

    protected function blockFencedCodeComplete($Block)
    {
        $text = $Block['element']['text']['text'];

        $Block['element']['text']['text'] = $text;

        return $Block;
    }

    #
    # Header

    protected function blockHeader($Line)
    {
        if (isset($Line['text'][1]))
        {
            $level = 1;

            while (isset($Line['text'][$level]) and $Line['text'][$level] === '#')
            {
                $level ++;
            }

            if ($level > 6)
            {
                return;
            }

            $text = trim($Line['text'], '# ');

            $Block = array(
                'element' => array(
                    'name' => 'h' . min(6, $level),
                    'text' => $text,
                    'handler' => 'line',
                ),
            );

            return $Block;
        }
    }

    #
    # List

    protected function blockList($Line)
    {
        list($name, $pattern) = $Line['text'][0] <= '-' ? array('ul', '[*+-]') : array('ol', '[0-9]+[.]');

        if (preg_match('/^('.$pattern.'[ ]+)(.*)/', $Line['text'], $matches))
        {
            $Block = array(
                'indent' => $Line['indent'],
                'pattern' => $pattern,
                'element' => array(
                    'name' => $name,
                    'handler' => 'elements',
                ),
            );

            if($name === 'ol')
            {
                $listStart = stristr($matches[0], '.', true);

                if($listStart !== '1')
                {
                    $Block['element']['attributes'] = array('start' => $listStart);
                }
            }

            $Block['li'] = array(
                'name' => 'li',
                'handler' => 'li',
                'text' => array(
                    $matches[2],
                ),
            );

            $Block['element']['text'] []= & $Block['li'];

            return $Block;
        }
    }

    protected function blockListContinue($Line, array $Block)
    {
        if ($Block['indent'] === $Line['indent'] and preg_match('/^'.$Block['pattern'].'(?:[ ]+(.*)|$)/', $Line['text'], $matches))
        {
            if (isset($Block['interrupted']))
            {
                $Block['li']['text'] []= '';

                $Block['loose'] = true;

                unset($Block['interrupted']);
            }

            unset($Block['li']);

            $text = isset($matches[1]) ? $matches[1] : '';

            $Block['li'] = array(
                'name' => 'li',
                'handler' => 'li',
                'text' => array(
                    $text,
                ),
            );

            $Block['element']['text'] []= & $Block['li'];

            return $Block;
        }

        if ($Line['text'][0] === '[' and $this->blockReference($Line))
        {
            return $Block;
        }

        if ( ! isset($Block['interrupted']))
        {
            $text = preg_replace('/^[ ]{0,4}/', '', $Line['body']);

            $Block['li']['text'] []= $text;

            return $Block;
        }

        if ($Line['indent'] > 0)
        {
            $Block['li']['text'] []= '';

            $text = preg_replace('/^[ ]{0,4}/', '', $Line['body']);

            $Block['li']['text'] []= $text;

            unset($Block['interrupted']);

            return $Block;
        }
    }

    protected function blockListComplete(array $Block)
    {
        if (isset($Block['loose']))
        {
            foreach ($Block['element']['text'] as &$li)
            {
                if (end($li['text']) !== '')
                {
                    $li['text'] []= '';
                }
            }
        }

        return $Block;
    }

    #
    # Quote

    protected function blockQuote($Line)
    {
        if (preg_match('/^>[ ]?(.*)/', $Line['text'], $matches))
        {
            $Block = array(
                'element' => array(
                    'name' => 'blockquote',
                    'handler' => 'lines',
                    'text' => (array) $matches[1],
                ),
            );

            return $Block;
        }
    }

    protected function blockQuoteContinue($Line, array $Block)
    {
        if ($Line['text'][0] === '>' and preg_match('/^>[ ]?(.*)/', $Line['text'], $matches))
        {
            if (isset($Block['interrupted']))
            {
                $Block['element']['text'] []= '';

                unset($Block['interrupted']);
            }

            $Block['element']['text'] []= $matches[1];

            return $Block;
        }

        if ( ! isset($Block['interrupted']))
        {
            $Block['element']['text'] []= $Line['text'];

            return $Block;
        }
    }

    #
    # Rule

    protected function blockRule($Line)
    {
        if (preg_match('/^(['.$Line['text'][0].'])([ ]*\1){2,}[ ]*$/', $Line['text']))
        {
            $Block = array(
                'element' => array(
                    'name' => 'hr'
                ),
            );

            return $Block;
        }
    }

    #
    # Setext

    protected function blockSetextHeader($Line, array $Block = null)
    {
        if ( ! isset($Block) or isset($Block['type']) or isset($Block['interrupted']))
        {
            return;
        }

        if (chop($Line['text'], $Line['text'][0]) === '')
        {
            $Block['element']['name'] = $Line['text'][0] === '=' ? 'h1' : 'h2';

            return $Block;
        }
    }

    #
    # Markup

    protected function blockMarkup($Line)
    {
        if ($this->markupEscaped or $this->safeMode)
        {
            return;
        }

        if (preg_match('/^<(\w[\w-]*)(?:[ ]*'.$this->regexHtmlAttribute.')*[ ]*(\/)?>/', $Line['text'], $matches))
        {
            $element = strtolower($matches[1]);

            if (in_array($element, $this->textLevelElements))
            {
                return;
            }

            $Block = array(
                'name' => $matches[1],
                'depth' => 0,
                'markup' => $Line['text'],
            );

            $length = strlen($matches[0]);

            $remainder = substr($Line['text'], $length);

            if (trim($remainder) === '')
            {
                if (isset($matches[2]) or in_array($matches[1], $this->voidElements))
                {
                    $Block['closed'] = true;

                    $Block['void'] = true;
                }
            }
            else
            {
                if (isset($matches[2]) or in_array($matches[1], $this->voidElements))
                {
                    return;
                }

                if (preg_match('/<\/'.$matches[1].'>[ ]*$/i', $remainder))
                {
                    $Block['closed'] = true;
                }
            }

            return $Block;
        }
    }

    protected function blockMarkupContinue($Line, array $Block)
    {
        if (isset($Block['closed']))
        {
            return;
        }

        if (preg_match('/^<'.$Block['name'].'(?:[ ]*'.$this->regexHtmlAttribute.')*[ ]*>/i', $Line['text'])) # open
        {
            $Block['depth'] ++;
        }

        if (preg_match('/(.*?)<\/'.$Block['name'].'>[ ]*$/i', $Line['text'], $matches)) # close
        {
            if ($Block['depth'] > 0)
            {
                $Block['depth'] --;
            }
            else
            {
                $Block['closed'] = true;
            }
        }

        if (isset($Block['interrupted']))
        {
            $Block['markup'] .= "\n";

            unset($Block['interrupted']);
        }

        $Block['markup'] .= "\n".$Line['body'];

        return $Block;
    }

    #
    # Reference

    protected function blockReference($Line)
    {
        if (preg_match('/^\[(.+?)\]:[ ]*<?(\S+?)>?(?:[ ]+["\'(](.+)["\')])?[ ]*$/', $Line['text'], $matches))
        {
            $id = strtolower($matches[1]);

            $Data = array(
                'url' => $matches[2],
                'title' => null,
            );

            if (isset($matches[3]))
            {
                $Data['title'] = $matches[3];
            }

            $this->DefinitionData['Reference'][$id] = $Data;

            $Block = array(
                'hidden' => true,
            );

            return $Block;
        }
    }

    #
    # Table

    protected function blockTable($Line, array $Block = null)
    {
        if ( ! isset($Block) or isset($Block['type']) or isset($Block['interrupted']))
        {
            return;
        }

        if (strpos($Block['element']['text'], '|') !== false and chop($Line['text'], ' -:|') === '')
        {
            $alignments = array();

            $divider = $Line['text'];

            $divider = trim($divider);
            $divider = trim($divider, '|');

            $dividerCells = explode('|', $divider);

            foreach ($dividerCells as $dividerCell)
            {
                $dividerCell = trim($dividerCell);

                if ($dividerCell === '')
                {
                    continue;
                }

                $alignment = null;

                if ($dividerCell[0] === ':')
                {
                    $alignment = 'left';
                }

                if (substr($dividerCell, - 1) === ':')
                {
                    $alignment = $alignment === 'left' ? 'center' : 'right';
                }

                $alignments []= $alignment;
            }

            # ~

            $HeaderElements = array();

            $header = $Block['element']['text'];

            $header = trim($header);
            $header = trim($header, '|');

            $headerCells = explode('|', $header);

            foreach ($headerCells as $index => $headerCell)
            {
                $headerCell = trim($headerCell);

                $HeaderElement = array(
                    'name' => 'th',
                    'text' => $headerCell,
                    'handler' => 'line',
                );

                if (isset($alignments[$index]))
                {
                    $alignment = $alignments[$index];

                    $HeaderElement['attributes'] = array(
                        'style' => 'text-align: '.$alignment.';',
                    );
                }

                $HeaderElements []= $HeaderElement;
            }

            # ~

            $Block = array(
                'alignments' => $alignments,
                'identified' => true,
                'element' => array(
                    'name' => 'table',
                    'handler' => 'elements',
                ),
            );

            $Block['element']['text'] []= array(
                'name' => 'thead',
                'handler' => 'elements',
            );

            $Block['element']['text'] []= array(
                'name' => 'tbody',
                'handler' => 'elements',
                'text' => array(),
            );

            $Block['element']['text'][0]['text'] []= array(
                'name' => 'tr',
                'handler' => 'elements',
                'text' => $HeaderElements,
            );

            return $Block;
        }
    }

    protected function blockTableContinue($Line, array $Block)
    {
        if (isset($Block['interrupted']))
        {
            return;
        }

        if ($Line['text'][0] === '|' or strpos($Line['text'], '|'))
        {
            $Elements = array();

            $row = $Line['text'];

            $row = trim($row);
            $row = trim($row, '|');

            preg_match_all('/(?:(\\\\[|])|[^|`]|`[^`]+`|`)+/', $row, $matches);

            foreach ($matches[0] as $index => $cell)
            {
                $cell = trim($cell);

                $Element = array(
                    'name' => 'td',
                    'handler' => 'line',
                    'text' => $cell,
                );

                if (isset($Block['alignments'][$index]))
                {
                    $Element['attributes'] = array(
                        'style' => 'text-align: '.$Block['alignments'][$index].';',
                    );
                }

                $Elements []= $Element;
            }

            $Element = array(
                'name' => 'tr',
                'handler' => 'elements',
                'text' => $Elements,
            );

            $Block['element']['text'][1]['text'] []= $Element;

            return $Block;
        }
    }

    #
    # ~
    #

    protected function paragraph($Line)
    {
        $Block = array(
            'element' => array(
                'name' => 'p',
                'text' => $Line['text'],
                'handler' => 'line',
            ),
        );

        return $Block;
    }

    #
    # Inline Elements
    #

    protected $InlineTypes = array(
        '"' => array('SpecialCharacter'),
        '!' => array('Image'),
        '&' => array('SpecialCharacter'),
        '*' => array('Emphasis'),
        ':' => array('Url'),
        '<' => array('UrlTag', 'EmailTag', 'Markup', 'SpecialCharacter'),
        '>' => array('SpecialCharacter'),
        '[' => array('Link'),
        '_' => array('Emphasis'),
        '`' => array('Code'),
        '~' => array('Strikethrough'),
        '\\' => array('EscapeSequence'),
    );

    # ~

    protected $inlineMarkerList = '!"*_&[:<>`~\\';

    #
    # ~
    #

    public function line($text, $nonNestables=array())
    {
        $markup = '';

        # $excerpt is based on the first occurrence of a marker

        while ($excerpt = strpbrk($text, $this->inlineMarkerList))
        {
            $marker = $excerpt[0];

            $markerPosition = strpos($text, $marker);

            $Excerpt = array('text' => $excerpt, 'context' => $text);

            foreach ($this->InlineTypes[$marker] as $inlineType)
            {
                # check to see if the current inline type is nestable in the current context

                if ( ! empty($nonNestables) and in_array($inlineType, $nonNestables))
                {
                    continue;
                }

                $Inline = $this->{'inline'.$inlineType}($Excerpt);

                if ( ! isset($Inline))
                {
                    continue;
                }

                # makes sure that the inline belongs to "our" marker

                if (isset($Inline['position']) and $Inline['position'] > $markerPosition)
                {
                    continue;
                }

                # sets a default inline position

                if ( ! isset($Inline['position']))
                {
                    $Inline['position'] = $markerPosition;
                }

                # cause the new element to 'inherit' our non nestables

                foreach ($nonNestables as $non_nestable)
                {
                    $Inline['element']['nonNestables'][] = $non_nestable;
                }

                # the text that comes before the inline
                $unmarkedText = substr($text, 0, $Inline['position']);

                # compile the unmarked text
                $markup .= $this->unmarkedText($unmarkedText);

                # compile the inline
                $markup .= isset($Inline['markup']) ? $Inline['markup'] : $this->element($Inline['element']);

                # remove the examined text
                $text = substr($text, $Inline['position'] + $Inline['extent']);

                continue 2;
            }

            # the marker does not belong to an inline

            $unmarkedText = substr($text, 0, $markerPosition + 1);

            $markup .= $this->unmarkedText($unmarkedText);

            $text = substr($text, $markerPosition + 1);
        }

        $markup .= $this->unmarkedText($text);

        return $markup;
    }

    #
    # ~
    #

    protected function inlineCode($Excerpt)
    {
        $marker = $Excerpt['text'][0];

        if (preg_match('/^('.$marker.'+)[ ]*(.+?)[ ]*(?<!'.$marker.')\1(?!'.$marker.')/s', $Excerpt['text'], $matches))
        {
            $text = $matches[2];
            $text = preg_replace("/[ ]*\n/", ' ', $text);

            return array(
                'extent' => strlen($matches[0]),
                'element' => array(
                    'name' => 'code',
                    'text' => $text,
                ),
            );
        }
    }

    protected function inlineEmailTag($Excerpt)
    {
        if (strpos($Excerpt['text'], '>') !== false and preg_match('/^<((mailto:)?\S+?@\S+?)>/i', $Excerpt['text'], $matches))
        {
            $url = $matches[1];

            if ( ! isset($matches[2]))
            {
                $url = 'mailto:' . $url;
            }

            return array(
                'extent' => strlen($matches[0]),
                'element' => array(
                    'name' => 'a',
                    'text' => $matches[1],
                    'attributes' => array(
                        'href' => $url,
                    ),
                ),
            );
        }
    }

    protected function inlineEmphasis($Excerpt)
    {
        if ( ! isset($Excerpt['text'][1]))
        {
            return;
        }

        $marker = $Excerpt['text'][0];

        if ($Excerpt['text'][1] === $marker and preg_match($this->StrongRegex[$marker], $Excerpt['text'], $matches))
        {
            $emphasis = 'strong';
        }
        elseif (preg_match($this->EmRegex[$marker], $Excerpt['text'], $matches))
        {
            $emphasis = 'em';
        }
        else
        {
            return;
        }

        return array(
            'extent' => strlen($matches[0]),
            'element' => array(
                'name' => $emphasis,
                'handler' => 'line',
                'text' => $matches[1],
            ),
        );
    }

    protected function inlineEscapeSequence($Excerpt)
    {
        if (isset($Excerpt['text'][1]) and in_array($Excerpt['text'][1], $this->specialCharacters))
        {
            return array(
                'markup' => $Excerpt['text'][1],
                'extent' => 2,
            );
        }
    }

    protected function inlineImage($Excerpt)
    {
        if ( ! isset($Excerpt['text'][1]) or $Excerpt['text'][1] !== '[')
        {
            return;
        }

        $Excerpt['text']= substr($Excerpt['text'], 1);

        $Link = $this->inlineLink($Excerpt);

        if ($Link === null)
        {
            return;
        }

        $Inline = array(
            'extent' => $Link['extent'] + 1,
            'element' => array(
                'name' => 'img',
                'attributes' => array(
                    'src' => $Link['element']['attributes']['href'],
                    'alt' => $Link['element']['text'],
                ),
            ),
        );

        $Inline['element']['attributes'] += $Link['element']['attributes'];

        unset($Inline['element']['attributes']['href']);

        return $Inline;
    }

    protected function inlineLink($Excerpt)
    {
        $Element = array(
            'name' => 'a',
            'handler' => 'line',
            'nonNestables' => array('Url', 'Link'),
            'text' => null,
            'attributes' => array(
                'href' => null,
                'title' => null,
            ),
        );

        $extent = 0;

        $remainder = $Excerpt['text'];

        if (preg_match('/\[((?:[^][]++|(?R))*+)\]/', $remainder, $matches))
        {
            $Element['text'] = $matches[1];

            $extent += strlen($matches[0]);

            $remainder = substr($remainder, $extent);
        }
        else
        {
            return;
        }

        if (preg_match('/^[(]\s*+((?:[^ ()]++|[(][^ )]+[)])++)(?:[ ]+("[^"]*"|\'[^\']*\'))?\s*[)]/', $remainder, $matches))
        {
            $Element['attributes']['href'] = $matches[1];

            if (isset($matches[2]))
            {
                $Element['attributes']['title'] = substr($matches[2], 1, - 1);
            }

            $extent += strlen($matches[0]);
        }
        else
        {
            if (preg_match('/^\s*\[(.*?)\]/', $remainder, $matches))
            {
                $definition = strlen($matches[1]) ? $matches[1] : $Element['text'];
                $definition = strtolower($definition);

                $extent += strlen($matches[0]);
            }
            else
            {
                $definition = strtolower($Element['text']);
            }

            if ( ! isset($this->DefinitionData['Reference'][$definition]))
            {
                return;
            }

            $Definition = $this->DefinitionData['Reference'][$definition];

            $Element['attributes']['href'] = $Definition['url'];
            $Element['attributes']['title'] = $Definition['title'];
        }

        return array(
            'extent' => $extent,
            'element' => $Element,
        );
    }

    protected function inlineMarkup($Excerpt)
    {
        if ($this->markupEscaped or $this->safeMode or strpos($Excerpt['text'], '>') === false)
        {
            return;
        }

        if ($Excerpt['text'][1] === '/' and preg_match('/^<\/\w[\w-]*[ ]*>/s', $Excerpt['text'], $matches))
        {
            return array(
                'markup' => $matches[0],
                'extent' => strlen($matches[0]),
            );
        }

        if ($Excerpt['text'][1] === '!' and preg_match('/^<!---?[^>-](?:-?[^-])*-->/s', $Excerpt['text'], $matches))
        {
            return array(
                'markup' => $matches[0],
                'extent' => strlen($matches[0]),
            );
        }

        if ($Excerpt['text'][1] !== ' ' and preg_match('/^<\w[\w-]*(?:[ ]*'.$this->regexHtmlAttribute.')*[ ]*\/?>/s', $Excerpt['text'], $matches))
        {
            return array(
                'markup' => $matches[0],
                'extent' => strlen($matches[0]),
            );
        }
    }

    protected function inlineSpecialCharacter($Excerpt)
    {
        if ($Excerpt['text'][0] === '&' and ! preg_match('/^&#?\w+;/', $Excerpt['text']))
        {
            return array(
                'markup' => '&amp;',
                'extent' => 1,
            );
        }

        $SpecialCharacter = array('>' => 'gt', '<' => 'lt', '"' => 'quot');

        if (isset($SpecialCharacter[$Excerpt['text'][0]]))
        {
            return array(
                'markup' => '&'.$SpecialCharacter[$Excerpt['text'][0]].';',
                'extent' => 1,
            );
        }
    }

    protected function inlineStrikethrough($Excerpt)
    {
        if ( ! isset($Excerpt['text'][1]))
        {
            return;
        }

        if ($Excerpt['text'][1] === '~' and preg_match('/^~~(?=\S)(.+?)(?<=\S)~~/', $Excerpt['text'], $matches))
        {
            return array(
                'extent' => strlen($matches[0]),
                'element' => array(
                    'name' => 'del',
                    'text' => $matches[1],
                    'handler' => 'line',
                ),
            );
        }
    }

    protected function inlineUrl($Excerpt)
    {
        if ($this->urlsLinked !== true or ! isset($Excerpt['text'][2]) or $Excerpt['text'][2] !== '/')
        {
            return;
        }

        if (preg_match('/\bhttps?:[\/]{2}[^\s<]+\b\/*/ui', $Excerpt['context'], $matches, PREG_OFFSET_CAPTURE))
        {
            $url = $matches[0][0];

            $Inline = array(
                'extent' => strlen($matches[0][0]),
                'position' => $matches[0][1],
                'element' => array(
                    'name' => 'a',
                    'text' => $url,
                    'attributes' => array(
                        'href' => $url,
                    ),
                ),
            );

            return $Inline;
        }
    }

    protected function inlineUrlTag($Excerpt)
    {
        if (strpos($Excerpt['text'], '>') !== false and preg_match('/^<(\w+:\/{2}[^ >]+)>/i', $Excerpt['text'], $matches))
        {
            $url = $matches[1];

            return array(
                'extent' => strlen($matches[0]),
                'element' => array(
                    'name' => 'a',
                    'text' => $url,
                    'attributes' => array(
                        'href' => $url,
                    ),
                ),
            );
        }
    }

    # ~

    protected function unmarkedText($text)
    {
        if ($this->breaksEnabled)
        {
            $text = preg_replace('/[ ]*\n/', "<br />\n", $text);
        }
        else
        {
            $text = preg_replace('/(?:[ ][ ]+|[ ]*\\\\)\n/', "<br />\n", $text);
            $text = str_replace(" \n", "\n", $text);
        }

        return $text;
    }

    #
    # Handlers
    #

    protected function element(array $Element)
    {
        if ($this->safeMode)
        {
            $Element = $this->sanitiseElement($Element);
        }

        $markup = '<'.$Element['name'];

        if (isset($Element['attributes']))
        {
            foreach ($Element['attributes'] as $name => $value)
            {
                if ($value === null)
                {
                    continue;
                }

                $markup .= ' '.$name.'="'.self::escape($value).'"';
            }
        }

        $permitRawHtml = false;

        if (isset($Element['text']))
        {
            $text = $Element['text'];
        }
        // very strongly consider an alternative if you're writing an
        // extension
        elseif (isset($Element['rawHtml']))
        {
            $text = $Element['rawHtml'];
            $allowRawHtmlInSafeMode = isset($Element['allowRawHtmlInSafeMode']) && $Element['allowRawHtmlInSafeMode'];
            $permitRawHtml = !$this->safeMode || $allowRawHtmlInSafeMode;
        }

        if (isset($text))
        {
            $markup .= '>';

            if (!isset($Element['nonNestables']))
            {
                $Element['nonNestables'] = array();
            }

            if (isset($Element['handler']))
            {
                $markup .= $this->{$Element['handler']}($text, $Element['nonNestables']);
            }
            elseif (!$permitRawHtml)
            {
                $markup .= self::escape($text, true);
            }
            else
            {
                $markup .= $text;
            }

            $markup .= '</'.$Element['name'].'>';
        }
        else
        {
            $markup .= ' />';
        }

        return $markup;
    }

    protected function elements(array $Elements)
    {
        $markup = '';

        foreach ($Elements as $Element)
        {
            $markup .= "\n" . $this->element($Element);
        }

        $markup .= "\n";

        return $markup;
    }

    # ~

    protected function li($lines)
    {
        $markup = $this->lines($lines);

        $trimmedMarkup = trim($markup);

        if ( ! in_array('', $lines) and substr($trimmedMarkup, 0, 3) === '<p>')
        {
            $markup = $trimmedMarkup;
            $markup = substr($markup, 3);

            $position = strpos($markup, "</p>");

            $markup = substr_replace($markup, '', $position, 4);
        }

        return $markup;
    }

    #
    # Deprecated Methods
    #

    function parse($text)
    {
        $markup = $this->text($text);

        return $markup;
    }

    protected function sanitiseElement(array $Element)
    {
        static $goodAttribute = '/^[a-zA-Z0-9][a-zA-Z0-9-_]*+$/';
        static $safeUrlNameToAtt  = array(
            'a'   => 'href',
            'img' => 'src',
        );

        if (isset($safeUrlNameToAtt[$Element['name']]))
        {
            $Element = $this->filterUnsafeUrlInAttribute($Element, $safeUrlNameToAtt[$Element['name']]);
        }

        if ( ! empty($Element['attributes']))
        {
            foreach ($Element['attributes'] as $att => $val)
            {
                # filter out badly parsed attribute
                if ( ! preg_match($goodAttribute, $att))
                {
                    unset($Element['attributes'][$att]);
                }
                # dump onevent attribute
                elseif (self::striAtStart($att, 'on'))
                {
                    unset($Element['attributes'][$att]);
                }
            }
        }

        return $Element;
    }

    protected function filterUnsafeUrlInAttribute(array $Element, $attribute)
    {
        foreach ($this->safeLinksWhitelist as $scheme)
        {
            if (self::striAtStart($Element['attributes'][$attribute], $scheme))
            {
                return $Element;
            }
        }

        $Element['attributes'][$attribute] = str_replace(':', '%3A', $Element['attributes'][$attribute]);

        return $Element;
    }

    #
    # Static Methods
    #

    protected static function escape($text, $allowQuotes = false)
    {
        return htmlspecialchars($text, $allowQuotes ? ENT_NOQUOTES : ENT_QUOTES, 'UTF-8');
    }

    protected static function striAtStart($string, $needle)
    {
        $len = strlen($needle);

        if ($len > strlen($string))
        {
            return false;
        }
        else
        {
            return strtolower(substr($string, 0, $len)) === strtolower($needle);
        }
    }

    static function instance($name = 'default')
    {
        if (isset(self::$instances[$name]))
        {
            return self::$instances[$name];
        }

        $instance = new static();

        self::$instances[$name] = $instance;

        return $instance;
    }

    private static $instances = array();

    #
    # Fields
    #

    protected $DefinitionData;

    #
    # Read-Only

    protected $specialCharacters = array(
        '\\', '`', '*', '_', '{', '}', '[', ']', '(', ')', '>', '#', '+', '-', '.', '!', '|',
    );

    protected $StrongRegex = array(
        '*' => '/^[*]{2}((?:\\\\\*|[^*]|[*][^*]*[*])+?)[*]{2}(?![*])/s',
        '_' => '/^__((?:\\\\_|[^_]|_[^_]*_)+?)__(?!_)/us',
    );

    protected $EmRegex = array(
        '*' => '/^[*]((?:\\\\\*|[^*]|[*][*][^*]+?[*][*])+?)[*](?![*])/s',
        '_' => '/^_((?:\\\\_|[^_]|__[^_]*__)+?)_(?!_)\b/us',
    );

    protected $regexHtmlAttribute = '[a-zA-Z_:][\w:.-]*(?:\s*=\s*(?:[^"\'=<>`\s]+|"[^"]*"|\'[^\']*\'))?';

    protected $voidElements = array(
        'area', 'base', 'br', 'col', 'command', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source',
    );

    protected $textLevelElements = array(
        'a', 'br', 'bdo', 'abbr', 'blink', 'nextid', 'acronym', 'basefont',
        'b', 'em', 'big', 'cite', 'small', 'spacer', 'listing',
        'i', 'rp', 'del', 'code',          'strike', 'marquee',
        'q', 'rt', 'ins', 'font',          'strong',
        's', 'tt', 'kbd', 'mark',
        'u', 'xm', 'sub', 'nobr',
        'sup', 'ruby',
        'var', 'span',
        'wbr', 'time',
    );
}

//
// ### Parsedown Extra
//

#
#
# Parsedown Extra
# https://github.com/erusev/parsedown-extra
#
# (c) Emanuil Rusev
# http://erusev.com
#
# For the full license information, view the LICENSE file that was distributed
# with this source code.
#
#

class ParsedownExtra extends Parsedown
{
    # ~

    const version = '0.8.1';

    # ~

    function __construct()
    {
        if (version_compare(parent::version, '1.7.4') < 0)
        {
            throw new Exception('ParsedownExtra requires a later version of Parsedown');
        }

        $this->BlockTypes[':'] []= 'DefinitionList';
        $this->BlockTypes['*'] []= 'Abbreviation';

        # identify footnote definitions before reference definitions
        array_unshift($this->BlockTypes['['], 'Footnote');

        # identify footnote markers before before links
        array_unshift($this->InlineTypes['['], 'FootnoteMarker');
    }

    #
    # ~

    function text($text)
    {
        $markup = parent::text($text);

        # merge consecutive dl elements

        $markup = preg_replace('/<\/dl>\s+<dl>\s+/', '', $markup);

        # add footnotes

        if (isset($this->DefinitionData['Footnote']))
        {
            $Element = $this->buildFootnoteElement();

            $markup .= "\n" . $this->element($Element);
        }

        return $markup;
    }

    #
    # Blocks
    #

    #
    # Abbreviation

    protected function blockAbbreviation($Line)
    {
        if (preg_match('/^\*\[(.+?)\]:[ ]*(.+?)[ ]*$/', $Line['text'], $matches))
        {
            $this->DefinitionData['Abbreviation'][$matches[1]] = $matches[2];

            $Block = array(
                'hidden' => true,
            );

            return $Block;
        }
    }

    #
    # Footnote

    protected function blockFootnote($Line)
    {
        if (preg_match('/^\[\^(.+?)\]:[ ]?(.*)$/', $Line['text'], $matches))
        {
            $Block = array(
                'label' => $matches[1],
                'text' => $matches[2],
                'hidden' => true,
            );

            return $Block;
        }
    }

    protected function blockFootnoteContinue($Line, $Block)
    {
        if ($Line['text'][0] === '[' and preg_match('/^\[\^(.+?)\]:/', $Line['text']))
        {
            return;
        }

        if (isset($Block['interrupted']))
        {
            if ($Line['indent'] >= 4)
            {
                $Block['text'] .= "\n\n" . $Line['text'];

                return $Block;
            }
        }
        else
        {
            $Block['text'] .= "\n" . $Line['text'];

            return $Block;
        }
    }

    protected function blockFootnoteComplete($Block)
    {
        $this->DefinitionData['Footnote'][$Block['label']] = array(
            'text' => $Block['text'],
            'count' => null,
            'number' => null,
        );

        return $Block;
    }

    #
    # Definition List

    protected function blockDefinitionList($Line, $Block)
    {
        if ( ! isset($Block) or isset($Block['type']))
        {
            return;
        }

        $Element = array(
            'name' => 'dl',
            'handler' => 'elements',
            'text' => array(),
        );

        $terms = explode("\n", $Block['element']['text']);

        foreach ($terms as $term)
        {
            $Element['text'] []= array(
                'name' => 'dt',
                'handler' => 'line',
                'text' => $term,
            );
        }

        $Block['element'] = $Element;

        $Block = $this->addDdElement($Line, $Block);

        return $Block;
    }

    protected function blockDefinitionListContinue($Line, array $Block)
    {
        if ($Line['text'][0] === ':')
        {
            $Block = $this->addDdElement($Line, $Block);

            return $Block;
        }
        else
        {
            if (isset($Block['interrupted']) and $Line['indent'] === 0)
            {
                return;
            }

            if (isset($Block['interrupted']))
            {
                $Block['dd']['handler'] = 'text';
                $Block['dd']['text'] .= "\n\n";

                unset($Block['interrupted']);
            }

            $text = substr($Line['body'], min($Line['indent'], 4));

            $Block['dd']['text'] .= "\n" . $text;

            return $Block;
        }
    }

    #
    # Header

    protected function blockHeader($Line)
    {
        $Block = parent::blockHeader($Line);

        if (! isset($Block)) {
            return null;
        }

        if (preg_match('/[ #]*{('.$this->regexAttribute.'+)}[ ]*$/', $Block['element']['text'], $matches, PREG_OFFSET_CAPTURE))
        {
            $attributeString = $matches[1][0];

            $Block['element']['attributes'] = $this->parseAttributeData($attributeString);

            $Block['element']['text'] = substr($Block['element']['text'], 0, $matches[0][1]);
        }

        return $Block;
    }

    #
    # Markup

    protected function blockMarkupComplete($Block)
    {
        if ( ! isset($Block['void']))
        {
            $Block['markup'] = $this->processTag($Block['markup']);
        }

        return $Block;
    }

    #
    # Setext

    protected function blockSetextHeader($Line, array $Block = null)
    {
        $Block = parent::blockSetextHeader($Line, $Block);

        if (! isset($Block)) {
            return null;
        }

        if (preg_match('/[ ]*{('.$this->regexAttribute.'+)}[ ]*$/', $Block['element']['text'], $matches, PREG_OFFSET_CAPTURE))
        {
            $attributeString = $matches[1][0];

            $Block['element']['attributes'] = $this->parseAttributeData($attributeString);

            $Block['element']['text'] = substr($Block['element']['text'], 0, $matches[0][1]);
        }

        return $Block;
    }

    #
    # Inline Elements
    #

    #
    # Footnote Marker

    protected function inlineFootnoteMarker($Excerpt)
    {
        if (preg_match('/^\[\^(.+?)\]/', $Excerpt['text'], $matches))
        {
            $name = $matches[1];

            if ( ! isset($this->DefinitionData['Footnote'][$name]))
            {
                return;
            }

            $this->DefinitionData['Footnote'][$name]['count'] ++;

            if ( ! isset($this->DefinitionData['Footnote'][$name]['number']))
            {
                $this->DefinitionData['Footnote'][$name]['number'] = ++ $this->footnoteCount; # » &
            }

            $Element = array(
                'name' => 'sup',
                'attributes' => array('id' => 'fnref'.$this->DefinitionData['Footnote'][$name]['count'].':'.$name),
                'handler' => 'element',
                'text' => array(
                    'name' => 'a',
                    'attributes' => array('href' => '#fn:'.$name, 'class' => 'footnote-ref'),
                    'text' => $this->DefinitionData['Footnote'][$name]['number'],
                ),
            );

            return array(
                'extent' => strlen($matches[0]),
                'element' => $Element,
            );
        }
    }

    private $footnoteCount = 0;

    #
    # Link

    protected function inlineLink($Excerpt)
    {
        $Link = parent::inlineLink($Excerpt);

        if (! isset($Link)) {
            return null;
        }

        $remainder = substr($Excerpt['text'], $Link['extent']);

        if (preg_match('/^[ ]*{('.$this->regexAttribute.'+)}/', $remainder, $matches))
        {
            $Link['element']['attributes'] += $this->parseAttributeData($matches[1]);

            $Link['extent'] += strlen($matches[0]);
        }

        return $Link;
    }

    #
    # ~
    #

    protected function unmarkedText($text)
    {
        $text = parent::unmarkedText($text);

        if (isset($this->DefinitionData['Abbreviation']))
        {
            foreach ($this->DefinitionData['Abbreviation'] as $abbreviation => $meaning)
            {
                $pattern = '/\b'.preg_quote($abbreviation, '/').'\b/';

                $text = preg_replace($pattern, '<abbr title="'.$meaning.'">'.$abbreviation.'</abbr>', $text);
            }
        }

        return $text;
    }

    #
    # Util Methods
    #

    protected function addDdElement(array $Line, array $Block)
    {
        $text = substr($Line['text'], 1);
        $text = trim($text);

        unset($Block['dd']);

        $Block['dd'] = array(
            'name' => 'dd',
            'handler' => 'line',
            'text' => $text,
        );

        if (isset($Block['interrupted']))
        {
            $Block['dd']['handler'] = 'text';

            unset($Block['interrupted']);
        }

        $Block['element']['text'] []= & $Block['dd'];

        return $Block;
    }

    protected function buildFootnoteElement()
    {
        $Element = array(
            'name' => 'div',
            'attributes' => array('class' => 'footnotes'),
            'handler' => 'elements',
            'text' => array(
                array(
                    'name' => 'hr',
                ),
                array(
                    'name' => 'ol',
                    'handler' => 'elements',
                    'text' => array(),
                ),
            ),
        );

        uasort($this->DefinitionData['Footnote'], 'self::sortFootnotes');

        foreach ($this->DefinitionData['Footnote'] as $definitionId => $DefinitionData)
        {
            if ( ! isset($DefinitionData['number']))
            {
                continue;
            }

            $text = $DefinitionData['text'];

            $text = parent::text($text);

            $numbers = range(1, $DefinitionData['count']);

            $backLinksMarkup = '';

            foreach ($numbers as $number)
            {
                $backLinksMarkup .= ' <a href="#fnref'.$number.':'.$definitionId.'" rev="footnote" class="footnote-backref">&#8617;</a>';
            }

            $backLinksMarkup = substr($backLinksMarkup, 1);

            if (substr($text, - 4) === '</p>')
            {
                $backLinksMarkup = '&#160;'.$backLinksMarkup;

                $text = substr_replace($text, $backLinksMarkup.'</p>', - 4);
            }
            else
            {
                $text .= "\n".'<p>'.$backLinksMarkup.'</p>';
            }

            $Element['text'][1]['text'] []= array(
                'name' => 'li',
                'attributes' => array('id' => 'fn:'.$definitionId),
                'rawHtml' => "\n".$text."\n",
            );
        }

        return $Element;
    }

    # ~

    protected function parseAttributeData($attributeString)
    {
        $Data = array();

        $attributes = preg_split('/[ ]+/', $attributeString, - 1, PREG_SPLIT_NO_EMPTY);

        foreach ($attributes as $attribute)
        {
            if ($attribute[0] === '#')
            {
                $Data['id'] = substr($attribute, 1);
            }
            else # "."
            {
                $classes []= substr($attribute, 1);
            }
        }

        if (isset($classes))
        {
            $Data['class'] = implode(' ', $classes);
        }

        return $Data;
    }

    # ~

    protected function processTag($elementMarkup) # recursive
    {
        # http://stackoverflow.com/q/1148928/200145
        libxml_use_internal_errors(true);

        $DOMDocument = new DOMDocument;

        # http://stackoverflow.com/q/11309194/200145
        $elementMarkup = mb_convert_encoding($elementMarkup, 'HTML-ENTITIES', 'UTF-8');

        # http://stackoverflow.com/q/4879946/200145
        $DOMDocument->loadHTML($elementMarkup);
        $DOMDocument->removeChild($DOMDocument->doctype);
        $DOMDocument->replaceChild($DOMDocument->firstChild->firstChild->firstChild, $DOMDocument->firstChild);

        $elementText = '';

        if ($DOMDocument->documentElement->getAttribute('markdown') === '1')
        {
            foreach ($DOMDocument->documentElement->childNodes as $Node)
            {
                $elementText .= $DOMDocument->saveHTML($Node);
            }

            $DOMDocument->documentElement->removeAttribute('markdown');

            $elementText = "\n".$this->text($elementText)."\n";
        }
        else
        {
            foreach ($DOMDocument->documentElement->childNodes as $Node)
            {
                $nodeMarkup = $DOMDocument->saveHTML($Node);

                if ($Node instanceof DOMElement and ! in_array($Node->nodeName, $this->textLevelElements))
                {
                    $elementText .= $this->processTag($nodeMarkup);
                }
                else
                {
                    $elementText .= $nodeMarkup;
                }
            }
        }

        # because we don't want for markup to get encoded
        $DOMDocument->documentElement->nodeValue = 'placeholder\x1A';

        $markup = $DOMDocument->saveHTML($DOMDocument->documentElement);
        $markup = str_replace('placeholder\x1A', $elementText, $markup);

        return $markup;
    }

    # ~

    protected function sortFootnotes($A, $B) # callback
    {
        return $A['number'] - $B['number'];
    }

    #
    # Fields
    #

    protected $regexAttribute = '(?:[#.][-\w]+[ ]*)';
}

?>
