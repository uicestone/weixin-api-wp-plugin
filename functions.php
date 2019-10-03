<?php

if (!function_exists('get_user_by_openid')) {
	function get_user_by_openid($openid = null, $create_if_not_exist = false) {

		if (!$openid) {
			$openid = $_SERVER['HTTP_OPENID'];
		}

		if (!$openid) {
			return new WP_Error('invalid_openid', '未获取openid', array('status' => 403));
		}

		$users = get_users(array('meta_key' => 'openid', 'meta_value' => $openid));

		if (!$users && !$create_if_not_exist) {
			return new WP_Error('user_not_found','openid对应的用户不存在', array('status' => 404));
		}
		elseif (!$users) {
			$user_id = wp_insert_user(array(
				'user_login' => substr($openid, -8),
				'display_name' => '游客'
			));
			error_log("User {$user_id} created, openid {$openid}.");
			add_user_meta($user_id, 'openid', $openid);
			$user = get_user_by('ID', $user_id);
		} elseif (count($users) > 1) {
			return new WP_Error('duplicate_openid', '重复的openid，鉴权失败', array('status' => 409));
		} else {
			$user = $users[0];
		}

		return (object) [
			'id' => $user->ID,
			'name' => $user->data->display_name,
			'roles' => $user->roles,
			'avatarUrl' => get_user_meta($user->ID, 'avatar_url', true),
			'region' => get_user_meta($user->ID, 'region', true),
			'gender' => get_user_meta($user->ID, 'gender', true)
		];

	}
}