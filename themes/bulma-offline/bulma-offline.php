<?php
/*
 * The default built-in FirePage theme uses Bulma and CodeMirror from https://unpkg.com CDN. This theme will render same 
 * look by Bulma, but it will load it from offline bulma-css instead from CDN location.
 * 
 * This theme will also override .json page type to return header Content-Type with "application/json".
 * 
 * License: The MIT License (MIT)
 * Author: Zemian Deng
 */

class BulmaOfflineView extends FirePageView {
    public function echo_header_scripts() {
        $page = $this->page;
        ?>
        <link rel='stylesheet' href='<?php echo $page->theme_url ?>/bulma-0.9.1/css/bulma.css'>
        <?php if ($page->is_admin && ($page->action === 'new' || $page->action === 'edit')) { ?>
            <link rel="stylesheet" href="<?php echo $page->theme_url ?>/codemirror-5.58.2/lib/codemirror.css">
            <script src="<?php echo $page->theme_url ?>/codemirror-5.58.2/lib/codemirror.js"></script>
            <script src="<?php echo $page->theme_url ?>/codemirror-5.58.2/addon/mode/overlay.js"></script>
            <script src="<?php echo $page->theme_url ?>/codemirror-5.58.2/mode/javascript/javascript.js"></script>
            <script src="<?php echo $page->theme_url ?>/codemirror-5.58.2/mode/css/css.js"></script>
            <script src="<?php echo $page->theme_url ?>/codemirror-5.58.2/mode/xml/xml.js"></script>
            <script src="<?php echo $page->theme_url ?>/codemirror-5.58.2/mode/htmlmixed/htmlmixed.js"></script>
            <script src="<?php echo $page->theme_url ?>/codemirror-5.58.2/mode/markdown/markdown.js"></script>
            <script src="<?php echo $page->theme_url ?>/codemirror-5.58.2/mode/gfm/gfm.js"></script>
        <?php } ?>
        <?php
    }
}

class BulmaOfflineController extends FirePageController {
    public function create_view($page) {
        return new BulmaOfflineView($this, $page);
    }

    public function transform_content($file, $content) {
        if (FirePageUtils::ends_with($file, '.json')) {
            return $content; // Do not transform.
        }
        return parent::transform_content($file, $content);
    }
}
