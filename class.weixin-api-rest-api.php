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

		if (is_wp_error($user = get_user_by_openid($auth_result->openid, true))) {
			return rest_ensure_response($user);
		}

		$auth_result->user = $user;

		return rest_ensure_response($auth_result);
	}

	/**
	 * web OAuth (code to openid)
	 *
	 * @param WP_REST_Request $request
	 * @return mixed|WP_REST_Response
	 */
	public static function webOAuth($request) {
		$code = $request->get_param('code');
		$wx = new WeixinAPI(true);
		$auth_result = $wx->get_oauth_token($code);

		if (is_wp_error($user = get_user_by_openid($auth_result->openid, true))) {
			return rest_ensure_response($user);
		}

		$auth_result->user = $user;

		return rest_ensure_response($auth_result);
	}

	/**
	 * get user from openid
	 *
	 * @param WP_REST_Request $request
	 * @return mixed|WP_REST_Response
	 */
	public static function getAuthUser($request) {
		$openid = $request->get_param('openid');
		$user = get_user_by_openid($openid, true);

		return rest_ensure_response($user);
	}

	/**
	 * update wechat user info to wordpress user meta
	 *
	 * @param WP_REST_Request $request
	 * @return mixed|WP_REST_Response
	 */
	public static function updateUserInfo($request) {

		if (is_wp_error($user = get_user_by_openid())) {
			return rest_ensure_response($user);
		}

		$user_info = $request->get_json_params();

		wp_update_user(['ID' => $user->id, 'first_name' => $user_info['nickName'], 'display_name' => $user_info['nickName']]);

		switch($user_info['gender']) {
			case 1:
				$gender = '男'; break;
			case 2:
				$gender = '女'; break;
			default:
				$gender = '未知';
		}

		update_user_meta($user->id, 'avatar_url', $user_info['avatarUrl']);
		update_user_meta($user->id, 'gender', $gender);
		update_user_meta($user->id, 'region', $user_info['country'] . ' ' . $user_info['province'] . ' ' . $user_info['city']);

		return rest_ensure_response(get_user_by_openid());
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

		register_rest_route($this->namespace, $this->rest_base . '/auth', array(
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array($this, 'webOAuth'),
			)
		));

		register_rest_route($this->namespace, $this->rest_base . '/auth/user', array(
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array($this, 'getAuthUser'),
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
