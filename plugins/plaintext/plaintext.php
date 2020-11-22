<?php
/*
 * A simple plugin to return Content-Type: 'text/plain' for .txt instead of pre-formatted page.
 *  
 * License: The MIT License (MIT)
 * Author: Zemian Deng
 */

/**
 * A plugin must define a class named "<plugin-name>Plugin", and you may "redefine" any methods you see
 * in the FirePageController to act as a hook. 
 */
class plaintextPlugin {
    public $controller;

    // Note that init() method will receive the FirePageController instance
    public function init($controller) {
        $this->controller = $controller;
    }

    // We will override process_request() as filter chain
    function process_request($page) {
        if (FirePageUtils::ends_with($page->page_name, '.txt')) {
            header('Content-Type: text/plain');
            echo $this->controller->get_file_content($page->page_name);
            return null;
        }
        // Ensure next plugin or the default controller will continue
        return $page;
    }
}