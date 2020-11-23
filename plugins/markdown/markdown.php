<?php
/*
 * A plugin to support Markdown file (extension: '.md' or 'markdown') as Page view.
 *  
 * License: The MIT License (MIT)
 * Author: Zemian Deng
 */

require_once 'parsedown-1.7.4/Parsedown.php';
require_once 'parsedown-extra-0.8.1/ParsedownExtra.php';

class markdownFPPlugin extends FirePagePlugin {
    const MD_EXTS = ['.md', '.markdown'];
    public Parsedown $md_parser;

    public function __construct(FirePageApp $app) {
        parent::__construct($app);
        $this->md_parser = new ParsedownExtra();
    }

    public function init(): void {
        // Ensure extensions are registered
        $ext_list = $this->app->config->file_extension_list;
        foreach (self::MD_EXTS as $ext) {
            if (!in_array($ext, $ext_list)) {
                array_push($this->app->config->file_extension_list, $ext);
            }
        }
    }
    
    public function handle_event($event_name, $params): bool {
        if ($event_name === 'transform_content') {
            $page = $params[0];
            return $this->transform_content($page);
        }
        return false;
    }

    function transform_content(FirePageContext $page) {
        $file = $page->page_name;
        foreach (self::MD_EXTS as $ext) {
            if (FirePageUtils::ends_with($file, $ext)) {
                $page->file_content = $this->convert_to_markdown($page->file_content);
                $page->is_content_transformed = true;
                return true;
            }
        }
        return false;
    }

    function convert_to_markdown($content) {
        return $this->md_parser->text($content);
    }
}
