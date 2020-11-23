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
class plaintextFPPlugin extends FirePagePlugin {    
    public function process_request(FirePageContext $page, FPView $view): ?FPView {
        if (FirePageUtils::ends_with($page->page_name, '.txt')) {
            header('Content-Type: text/plain');
            echo $this->app->controller->get_file_content($page->page_name);
            return null;
        }
        
        // Return view object to be process as normal
        return $view;
    }
}