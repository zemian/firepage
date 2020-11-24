<?php
/*
 * A plugin to support Markdown file (extension: '.md' or 'markdown') as Page view.
 *  
 * License: The MIT License (MIT)
 * Author: Zemian Deng
 */

require_once 'parsedown-1.7.4/Parsedown.php';
require_once 'parsedown-extra-0.8.1/ParsedownExtra.php';
require_once 'parsedown-extension_table-of-contents-1.1.2/Extension.php';

class markdownFPPlugin extends FirePagePlugin {
    const MD_EXTS = ['.md', '.markdown'];
    public Parsedown $md_parser;

    public function __construct(FirePageApp $app) {
        parent::__construct($app);
        $this->md_parser = new ParsedownToC();
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
                $page->file_content = $this->convert_to_markdown($page->file_content, $page);
                $page->is_content_transformed = true;
                return true;
            }
        }
        return false;
    }

    function convert_to_markdown(string $content, FirePageContext $page) {
        $ret = $this->md_parser->text($content);
        
        // Save TOC separately into page context
        $page->file_content_toc = $this->md_parser->contentsList('string');
        
        return $ret;
    }
}

class MarkdownSideTocFPView extends FirePageView {
    function echo_site_content() {
        ?>
        <section class="section">
            <div class="columns">
                <div class="column is-3">
                    <div class="menu">
                        <?php $this->echo_menu_links(); ?>
                    </div>
                </div>
                <div class="column is-7">
                    <div class="content" style="min-height: 60vh;">
                        <?php $this->echo_content(); ?>
                    </div>
                </div>
                <div class="column is-2">
                    <div class="toc content">
                        <?php echo $this->page->file_content_toc; ?>
                    </div>
                </div>
            </div>
        </section>
        <?php
    }
}
