<?php
/**
 * A simple php to browse a DocumentRoot directory where it list all dirs and files.
 *
 * NOTE: 
 * Exposing directory and files is consider security risk for publicly hosted server! This 
 * script is only intended for internal web site and serve as tool. 
 * 
 * Features:
 *   - List files alphabetically.
 *   - Each file should be listed as a link and go to the page when clicked.
 *   - List dir separately on the side as navigation.
 *   - Each dir is a link to browse sub dir content recursively. 
 *   - Provide parent link to go back up one directory when browsing sub dir.
 * 
 * Author: Zemian Deng
 * Date: 2020-11-04
 */

// Page vars
$title = 'Index Listing';
$browse_dir = $_GET['dir'] ?? '';
$parent_browse_dir = $_GET['parent'] ?? '';
$error = '';
$dirs = [];
$files = [];

// Internal vars
$root_path = __DIR__;
$list_path = "$root_path/$browse_dir";

// Validate Inputs
if ( (substr_count($browse_dir, '.') > 0) /* It should not contains '.' or '..' relative paths */
    || (!is_dir($list_path)) /* It should exists. */
) {
    $error = "ERROR: Invalid directory.";
}

// Get files and dirs listing
if (!$error) {
    // We need to get rid of the first two entries for "." and ".." returned by scandir().
    $list = array_slice(scandir($list_path), 2);
    foreach ($list as $item) {
        // NOTE: To avoid security risk, we always use $list_path as base path! Never go outside of it!
        if (is_dir("$list_path/$item")) {
            // We will not show hidden ".folder" folders
            if (substr_compare($item, '.', 0, 1) !== 0) {
                array_push($dirs, $item);
            }
        } else {
            array_push($files, $item);
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="https://unpkg.com/bulma">
    <title><?= $title ?></title>
</head>
<body>
<div class="section">
    <div class="level">
        <div class="level-item">
            <a href="index.php"><h1 class="title"><?= $title ?></h1></a>
        </div>
    </div>
    <div class="columns">
        <div class="column is-one-third">
            <!-- List of Directories -->
            <div class="menu">                
                <p class="menu-label">Directory: <?= $browse_dir; ?></p>
                <ul class="menu-list">
                    <?php if ($browse_dir) { ?>
                        <li><a href="index.php?dir=<?= $parent_browse_dir ?>">..</a></li>
                    <?php } ?>
                    <?php foreach ($dirs as $item) { ?>
                    <li><a href="index.php?dir=<?= "$browse_dir/$item" ?>&parent=<?= $browse_dir ?>"><?= $item ?></a></li>
                    <?php } ?>
                </ul>
            </div>
        </div>
        <div class="column">
            <?php if ($error) { ?>
                <div class="notification is-danger">
                    <?= $error ?>
                </div>
            <?php } else { ?>
                <!-- List of Files -->
                <div class="content">
                    <ul>
                        <?php foreach ($files as $item) { ?>
                            <li><a href="<?= "$browse_dir/$item" ?>"><?= $item ?></a></li>
                        <?php } ?>
                    </ul>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

</body>
</html>
