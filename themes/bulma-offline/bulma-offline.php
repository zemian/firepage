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

// Add "head" hook to override the head section of the built-in theme page
$app->hooks['head'] = function ($app) {
    $page = $app->page;
    echo <<< EOT
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <title>{$app->title}</title>
    <link rel="stylesheet" href="{$page->theme_url}/bulma.min.css">
EOT;

    if ($page->is_admin && ($page->action === 'new' || $page->action === 'edit')) {
        echo <<< EOT
            <link rel="stylesheet" href="{$page->theme_url}/codemirror-5.58.2/lib/codemirror.css">
            <script src="{$page->theme_url}/codemirror-5.58.2/lib/codemirror.js"></script>
            <script src="{$page->theme_url}/codemirror-5.58.2/addon/mode/overlay.js"></script>
            <script src="{$page->theme_url}/codemirror-5.58.2/mode/javascript/javascript.js"></script>
            <script src="{$page->theme_url}/codemirror-5.58.2/mode/css/css.js"></script>
            <script src="{$page->theme_url}/codemirror-5.58.2/mode/xml/xml.js"></script>
            <script src="{$page->theme_url}/codemirror-5.58.2/mode/htmlmixed/htmlmixed.js"></script>
            <script src="{$page->theme_url}/codemirror-5.58.2/mode/markdown/markdown.js"></script>
            <script src="{$page->theme_url}/codemirror-5.58.2/mode/gfm/gfm.js"></script>
EOT;

    }
    
    return true;
};

// Ensure the file_content is not been transformed for .json type.
$app->hooks['transform_content'] = function ($file, $content) {
    if (FirePageUtils::ends_with($file, '.json')) {
        return $content; // Do not transform.
    }
    return false; // Let the built-in theme do it's stuff.
};