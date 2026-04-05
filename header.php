<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
    <?php wp_body_open(); ?>

    <header class="site-header">
        <div class="container header-inner">
            <div class="site-branding">
                <h1 class="site-title"><a
                        href="<?php echo esc_url(home_url('/')); ?>"><?php bloginfo('name'); ?></a></h1>
                <p class="site-description"><?php bloginfo('description'); ?></p>
            </div>

            <?php if (has_nav_menu('menu-principal')): ?>
                <button class="menu-toggle" aria-controls="primary-menu" aria-expanded="false" aria-label="Menú">
                    <span class="hamburger-icon"></span>
                </button>
                <nav class="main-navigation">
                    <?php
                    wp_nav_menu(array(
                        'theme_location' => 'menu-principal',
                        'menu_id' => 'primary-menu',
                        'container_class' => 'menu-container'
                    ));
                    ?>
                </nav>
            <?php endif; ?>
        </div>
    </header>