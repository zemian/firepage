<?php

class DocsFPView extends FirePageView {
    public function echo_navbar_site() {
        $page = $this->page;
        $title = $this->app->config->title;
        ?>
        <nav class="navbar" role="navigation" aria-label="main navigation">
            <div class="navbar-brand">
                <div class="navbar-item">
                    <a class="title" href='<?php echo $page->controller_url; ?>'><?php echo $title; ?></a>
                </div>

                <a role="button" class="navbar-burger burger" aria-label="menu" aria-expanded="false" data-target="docsMenu">
                    <span aria-hidden="true"></span>
                    <span aria-hidden="true"></span>
                    <span aria-hidden="true"></span>
                </a>
            </div>

            <div id="docsMenu" class="navbar-menu">
                <div class="navbar-start">
                    <?php foreach ($this->get_menu_links()['links'] as $link) { ?>
                        <a class="navbar-item" href="<?php echo $page->controller_url . 'page=' . $link['url']; ?>">
                            <?php echo $link['label']; ?>
                        </a>
                    <?php } ?>

                    <?php foreach ($this->get_menu_links()['child_menu_links'] as $menu_links) { ?>
                        <div class="navbar-item has-dropdown is-hoverable">
                            <a class="navbar-link">
                                <?php echo $menu_links['menu_label']; ?>
                            </a>

                            <div class="navbar-dropdown">
                                <?php foreach ($menu_links['links'] as $link) { ?>
                                    <a class="navbar-item" href="<?php echo $page->controller_url . 'page=' . $link['url']; ?>">
                                        <?php echo $link['label']; ?>
                                    </a>
                                <?php } ?>
                            </div>
                        </div>
                    <?php } ?>
                </div>

                <div class="navbar-end">
                    <div class="navbar-item">
                        <div class="buttons">
                            <a class="button is-primary" href="https://github.com/zemian/firepage/releases">
                                <strong>Download</strong>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </nav>
        <?php
    }

    public function echo_site_content() {
        $page = $this->page;
        ?>
        <section class="section">
            <div class="columns">
                <div class="column is-3 menu">
                </div>
                <div class="column is-9">
                    <div class="content">
                        <?php echo $page->file_content; ?>
                    </div>
                </div>
            </div>
        </section>
        <?php
    }
}
