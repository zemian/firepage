<?php
/*
 * A plugin to support Markdown file (extension: '.md' or 'markdown') as Page view.
 *  
 * License: The MIT License (MIT)
 * Author: Zemian Deng
 */

require_once 'parsedown-1.7.4/Parsedown.php';
require_once 'parsedown-extra-0.8.1/ParsedownExtra.php';

class markdownPlugin {
    public $controller;
    public $md_parser;
    
    public function init($controller) {    
        $this->controller = $controller;
        $this->md_parser = new ParsedownExtra();
    }

    function convert_to_markdown($plain_text) {
        return $this->md_parser->text($plain_text);
    }
    
    function transform_content($file, $content) {
        if (FirePageUtils::ends_with($file, '.md') || FirePageUtils::ends_with($file, '.markdown')) {
            return $this->convert_to_markdown($content);
        }
        
        return null; // Let the controller do the transform.
    }
}
