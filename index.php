<?php
/**
 * Front to the WordPress application. This file doesn't do anything, but loads
 * wp-blog-header.php which does and tells WordPress to load the theme.
 *
 * @package WordPress
 */

# 此文件是通往 WordPress 世界的“南天门”，它是用户访问网站的“前端入口”（相对于后台管理界面来说）。
# 它本身并不做任何事情，仅通过加载 wp-blog-header.php 文件来完成后续主题和内容的加载（输出）。
#
# 导读： index.php -> wp-blog-header.php

/**
 * Tells WordPress to load the WordPress theme and output it.
 *
 * @var bool
 */
define('WP_USE_THEMES', true);

/** Loads the WordPress Environment and Template */
require( dirname( __FILE__ ) . '/wp-blog-header.php' );
