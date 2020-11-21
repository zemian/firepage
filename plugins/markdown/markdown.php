<?php

require_once 'parsedown-1.7.4/Parsedown.php';
require_once 'parsedown-extra-0.8.1/ParsedownExtra.php';

class MarkdownFirePageController extends FirePageController {
    var object $md_parser;
    
    public function __construct($config) {
        parent::__construct($config);
        
        $this->md_parser = $this->create_markdown_parser();
    }

    function create_markdown_parser() {
        return new ParsedownExtra();
    }
    
    function convert_to_markdown($plain_text) {
        return $this->md_parser->text($plain_text);
    }
    
    public function transform_content($file, $content) {
        if (FirePageUtils::ends_with($file, '.md') || FirePageUtils::ends_with($file, '.markdown')) {
            $content = $this->convert_to_markdown($content);
        } else {
            $content = parent::transform_content($file, $content);
        }
        return $content;
    }
}
