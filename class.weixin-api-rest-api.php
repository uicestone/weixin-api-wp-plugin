<?php

class WXAPI_REST_Controller extends WP_REST_Controller {

	public function __construct() {
		$this->namespace = 'v1';
		$this->rest_base = 'wx';
	}

	/**
	 * Serve wechat events
	 *
	 * @param WP_REST_Request $request
	 * @return WP_Error|WP_REST_Response
	 */
	public static function serve($request) {

		$wx = new WeixinAPI();

		if ($request->get_param('echostr')) {
			return rest_ensure_response($wx->verify());
		}

	}

	/**
	 * Get JSAPI args
	 *
	 * @param WP_REST_Request $request
	 * @return mixed|WP_REST_Response
	 */
	public static function getJsapiArgs($request) {
		$wx = new WeixinAPI(true);
		$url = $request->get_param('url');
		error_log('getjsapiargeurl:' . $url);
		$args = $wx->generate_jsapi_args($url ?: null);
		return rest_ensure_response($args);
	}

	/**
	 * get mini program session from code
	 *
	 * @param WP_REST_Request $request
	 * @return mixed|WP_REST_Response
	 */
	public static function jsCodeToSession($request) {
		$code = $request->get_param('code');
		$wx = new WeixinAPI(true);
		$auth_result = $wx->js_code_to_session($code);

		$users = get_users(array('meta_key' => 'openid', 'meta_value' => $auth_result->openid));

		if (!$users) {
			$user_id = wp_insert_user(array(
				'user_login' => $auth_result->openid,
				'display_name' => '游客'
			));
			error_log("User {$user_id} created, openid {$auth_result->openid}.");
			add_user_meta($user_id, 'openid', $auth_result->openid);
			$user = get_user_by('ID', $user_id);
		} elseif (count($users) > 1) {
			return rest_ensure_response(new WP_Error('duplicate_openid', '重复的openid，鉴权失败', array('status' => 409)));
		} else {
			$user = $users[0];
		}

		$auth_result->user = (object) [
			'id' => $user->ID,
			'name' => $user->data->display_name,
			'roles' => $user->roles,
			'avatarUrl' => get_user_meta($user->ID, 'avatar_url', true),
			'region' => get_user_meta($user->ID, 'region', true),
			'gender' => get_user_meta($user->ID, 'gender', true)
		];


		return rest_ensure_response($auth_result);
	}

	/**
	 * update wechat user info to wordpress user meta
	 *
	 * @param WP_REST_Request $request
	 * @return mixed|WP_REST_Response
	 */
	public static function updateUserInfo($request) {
		$openid = $_SERVER['HTTP_OPENID'];

		if (!$openid) {
			return rest_ensure_response(new WP_Error('invalid_openid', ['message' => '未获取openid'], array('status' => 403)));
		}

		$users = get_users(array('meta_key' => 'openid', 'meta_value' => $openid));

		if (!$users) {
			return rest_ensure_response(new WP_Error('user_not_found','openid对应的用户不存在', array('status' => 404)));
		} elseif (count($users) > 1) {
			return rest_ensure_response(new WP_Error('duplicate_openid', '重复的openid，鉴权失败', array('status' => 409)));
		} else {
			$user = $users[0];
		}

		$user_info = $request->get_json_params();

		wp_update_user(['ID' => $user->ID, 'display_name' => $user_info['nickName']]);

		switch($user_info['gender']) {
			case 1:
				$gender = '男'; break;
			case 2:
				$gender = '女'; break;
			default:
				$gender = '未知';
		}

		update_user_meta($user->ID, 'avatar_url', $user_info['avatarUrl']);
		update_user_meta($user->ID, 'gender', $gender);
		update_user_meta($user->ID, 'region', $user_info['country'] . ' ' . $user_info['province'] . ' ' . $user_info['city']);

		return rest_ensure_response([
			'id' => $user->ID,
			'name' => $user->data->display_name,
			'roles' => $user->roles,
			'avatarUrl' => get_user_meta($user->ID, 'avatar_url', true),
			'region' => get_user_meta($user->ID, 'region', true),
			'gender' => get_user_meta($user->ID, 'gender', true)
		]);
	}

	/**
	 * Register the REST API routes.
	 */
	public function register_routes() {

		register_rest_route($this->namespace, $this->rest_base, array(
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array($this, 'serve'),
			)
		));

		register_rest_route($this->namespace, $this->rest_base . '/jsapi-args', array(
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array($this, 'getJsapiArgs'),
			)
		));

		register_rest_route($this->namespace, $this->rest_base . '/auth/code-to-session', array(
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array($this, 'jsCodeToSession'),
			)
		));

		register_rest_route($this->namespace, $this->rest_base . '/auth/user-info', array(
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array($this, 'updateUserInfo'),
			)
		));
	}

}
