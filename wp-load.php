<?php
/**
 * Bootstrap file for setting the ABSPATH constant
 * and loading the wp-config.php file. The wp-config.php
 * file will then load the wp-settings.php file, which
 * will then set up the WordPress environment.
 *
 * If the wp-config.php file is not found then an error
 * will be displayed asking the visitor to set up the
 * wp-config.php file.
 *
 * Will also search for wp-config.php in WordPress' parent
 * directory to allow the WordPress directory to remain
 * untouched.
 *
 * @package WordPress
 */

# 此文件主要用于：
# 1. 定义 ABSPATH 常量（我们必须尽可能早地确认 WordPress 的根目录，因为后续文件和目录定位都将基于此目录路径）；
# 2. 加载 wp-config.php 文件（其中包含数据库信息、 WordPress 配置信息等内容）。
#
# 关于 wp-config.php 文件的说明：
#     wp-config.php 文件是在安装 WordPress 时自动创建的（或手动拷贝 wp-config-sample.php 而来的），本源码仓库中不包含该文件。
#     后续注释说明中，若提到 wp-config.php 文件，请参考 wp-config-sample.php 文件内容。
#
# 导读： index.php -> wp-blog-header.php -> wp-load.php -> wp-config.php (或 wp-config-sample.php)


/** Define ABSPATH as this file's directory */
# 定义常量 ABSPATH ，它用于表示 WordPress 源码根目录路径，后续代码中会经常用到
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __FILE__ ) . '/' );
}

# 设置错误报告级别（Level）：
# 1. 关于 error_reporting()，请参考： http://php.net/manual/zh/function.error-reporting.php
# 2. 关于 E_CORE_ERROR 等预定义常量，请参考： http://php.net/manual/zh/errorfunc.constants.php
error_reporting( E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_ERROR | E_WARNING | E_PARSE | E_USER_ERROR | E_USER_WARNING | E_RECOVERABLE_ERROR );

/*
 * If wp-config.php exists in the WordPress root, or if it exists in the root and wp-settings.php
 * doesn't, load wp-config.php. The secondary check for wp-settings.php has the added benefit
 * of avoiding cases where the current directory is a nested installation, e.g. / is WordPress(a)
 * and /blog/ is WordPress(b).
 *
 * If neither set of conditions is true, initiate loading the setup process.
 */
# 判断 wp-config.php 文件是否存在：
# 1. 若存在，则说明 WordPress 已经安装过了，则加载 wp-config.php 文件
# 2. 否则，则进入安装向导页面（安装过程见： https://shawaner.com/tech/web-dev/wordpress-tutorials-1-setup#wordpress-setup）
if ( file_exists( ABSPATH . 'wp-config.php') ) {
	# 加载 wp-config.php 文件（本仓库不包含此文件，请参阅 wp-config-sample.php 文件）
	/** The config file resides in ABSPATH */
	require_once( ABSPATH . 'wp-config.php' );

} elseif ( @file_exists( dirname( ABSPATH ) . '/wp-config.php' ) && ! @file_exists( dirname( ABSPATH ) . '/wp-settings.php' ) ) {
	# 初次阅读源码时，可忽略此特殊情形
	/** The config file resides one level above ABSPATH but is not part of another installation */
	require_once( dirname( ABSPATH ) . '/wp-config.php' );

} else {
	# 启动安装向导页面，只有在初次安装时才会进入这里。
	# 如果使用“著名的5分安装”（https://codex.wordpress.org/zh-cn:%E5%AE%89%E8%A3%85_WordPress）也不会进入这里，
	# 因此阅读时，可忽略代码此部分内容。

	// A config file doesn't exist

	define( 'WPINC', 'wp-includes' );
	require_once( ABSPATH . WPINC . '/load.php' );

	// Standardize $_SERVER variables across setups.
	wp_fix_server_vars();

	require_once( ABSPATH . WPINC . '/functions.php' );

	$path = wp_guess_url() . '/wp-admin/setup-config.php';

	/*
	 * We're going to redirect to setup-config.php. While this shouldn't result
	 * in an infinite loop, that's a silly thing to assume, don't you think? If
	 * we're traveling in circles, our last-ditch effort is "Need more help?"
	 */
	# 重定向到 /wp-admin/setup-config.php ，启动安装向导页面
	if ( false === strpos( $_SERVER['REQUEST_URI'], 'setup-config' ) ) {
		header( 'Location: ' . $path );
		exit;
	}

	define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
	require_once( ABSPATH . WPINC . '/version.php' );

	wp_check_php_mysql_versions();
	wp_load_translations_early();

	// Die with an error message
	$die  = sprintf(
		/* translators: %s: wp-config.php */
		__( "There doesn't seem to be a %s file. I need this before we can get started." ),
		'<code>wp-config.php</code>'
	) . '</p>';
	$die .= '<p>' . sprintf(
		/* translators: %s: Codex URL */
		__( "Need more help? <a href='%s'>We got it</a>." ),
		__( 'https://codex.wordpress.org/Editing_wp-config.php' )
	) . '</p>';
	$die .= '<p>' . sprintf(
		/* translators: %s: wp-config.php */
		__( "You can create a %s file through a web interface, but this doesn't work for all server setups. The safest way is to manually create the file." ),
		'<code>wp-config.php</code>'
	) . '</p>';
	$die .= '<p><a href="' . $path . '" class="button button-large">' . __( "Create a Configuration File" ) . '</a>';

	wp_die( $die, __( 'WordPress &rsaquo; Error' ) );
}
