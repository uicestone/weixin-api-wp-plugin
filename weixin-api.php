<?php
/**
 * Plugin Name: Weixin API
 * Description: 在WordPress中调用微信公众号和小程序API，实现用户鉴权，微信支付，菜单更新等功能
 * Version: 0.7.0
 * Author: Uice Lu
 * Author URI: https://cecilia.uice.lu
 * License: GPLv2 or later
 * Text Domain: weixin-api
 */

// Make sure we don't expose any info if called directly
if (!function_exists('add_action')) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}

define('WXAPI_VERSION', '0.6.0');
define('WXAPI__MINIMUM_WP_VERSION', '4.8');
define('WXAPI__PLUGIN_DIR', plugin_dir_path(__FILE__));

require_once(WXAPI__PLUGIN_DIR . 'class.weixin-api.php');
require_once(WXAPI__PLUGIN_DIR . 'class.weixin-api-rest-api.php');
require_once(WXAPI__PLUGIN_DIR . 'functions.php');

add_action('rest_api_init', function () {
	(new WXAPI_REST_Controller())->register_routes();
});
