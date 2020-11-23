<?php
/*
 * A plugin to support Markdown file (extension: '.md' or 'markdown') as Page view.
 *  
 * License: The MIT License (MIT)
 * Author: Zemian Deng
 */

require_once 'parsedown-1.7.4/Parsedown.php';
require_once 'parsedown-extra-0.8.1/ParsedownExtra.php';

class markdownPlugin extends FirePagePlugin {
    public FirePageApp $app;
    public Parsedown $md_parser;

    public function __construct(FirePageApp $app) {
        $this->app = $app;
        $this->md_parser = new ParsedownExtra();
    }

    function convert_to_markdown($content) {
        return $this->md_parser->text($content);
    }

    function transform_content(FirePageContext $page) {
        $file = $page->page_name;
        if (FirePageUtils::ends_with($file, '.md') || FirePageUtils::ends_with($file, '.markdown')) {
            $page->file_content = $this->convert_to_markdown($page->file_content);
            $page->is_content_transformed = true;
        }
    }
}
