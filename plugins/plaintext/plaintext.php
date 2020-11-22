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
    
    public function transform_content($file, $content) {
        if (FirePageUtils::ends_with($file, '.txt')) {
            // Do not transform .txt file
            return $content;
        }
        
        // Reuse default implementation
        return $this->controller->transform_content($file, $content);
    }

    function process_view($page) {
        // Do not provide any view object for .json file
        if (FirePageUtils::ends_with($page->page_name, '.txt')) {
            header('Content-Type: text/plain');
            echo $page->file_content;
            return null;
        }

        // Reuse default implementation
        return $this->controller->create_view($page);
    }
}