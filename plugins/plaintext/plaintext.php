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
    const TEXT_EXT = '.txt';
    
    public function init(): void {
        // Ensure .txt is registered
        $ext_list = $this->app->config->file_extension_list;
        if (!in_array(self::TEXT_EXT, $ext_list)) {
            array_push($this->app->config->file_extension_list, self::TEXT_EXT);
        }
    }

    public function process_request(FirePageContext $page, FPView $view): ?FPView {
        if (FirePageUtils::ends_with($page->page_name, self::TEXT_EXT)) {
            header('Content-Type: text/plain');
            echo $this->app->controller->get_file_content($page->page_name);
            return null;
        }
        
        // Return view object to be process as normal
        return $view;
    }
}