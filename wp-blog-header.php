<?php
/**
 * Loads the WordPress environment and template.
 *
 * @package WordPress
 */

# 此文件将 WordPress 的处理过程分成三个阶段：
# 1. 初 始 化： 初始化常量，加载 WordPress 核心文件；
# 2. 内容生成： 根据用户请求生成输出内容； 
# 3. 主题应用： 应用主题模板，以指定主题样式展现内容。
#
# 导读： index.php -> wp-blog-header.php -> wp-load.php

if ( !isset($wp_did_header) ) {

	$wp_did_header = true;

	// Load the WordPress library.
    # 1. 初 始 化： 初始化常量，加载 WordPress 核心文件
	require_once( dirname(__FILE__) . '/wp-load.php' );

	// Set up the WordPress query.
    # 2. 内容生成： 根据用户请求生成输出内容
	wp();

	// Load the theme template.
    # 3. 主题应用： 应用主题模板，以指定主题样式展现内容
	require_once( ABSPATH . WPINC . '/template-loader.php' );

}
