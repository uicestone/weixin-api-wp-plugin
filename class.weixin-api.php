<?php

class WeixinAPI {
	
	public $token, // 微信公众账号后台 / 高级功能 / 开发模式 / 服务器配置
			$app_id, // 开发模式 / 开发者凭据
			$app_secret, // 同上
			$mch_id, // 微信商户ID
			$mch_key; // 微信商户Key
	
	function __construct($force_mp = false) {
		// 从WordPress配置中获取这些公众账号身份信息
		foreach(array(
			'app_id',
			'app_secret',
			'mch_id',
			'mch_key',
			'token'
		) as $item){
			if ((self::in_wx() || $force_mp) && defined(strtoupper('wx_' . $item))) {
				$this->$item = constant(strtoupper('wx_' . $item));
			}
			else if (defined(strtoupper('wx_' . $item . '_web'))) {
				$this->$item = constant(strtoupper('wx_' . $item . '_web'));
			}
		}

	}
	
	/*
	 * 验证来源为微信
	 * 放在用于响应微信消息请求的脚本最上端
	 */
	function verify(){
		$sign = array(
			$this->token,
			$_GET['timestamp'],
			$_GET['nonce']
		);

		sort($sign, SORT_STRING);

		if(sha1(implode($sign)) !== $_GET['signature']){
			exit('Signature verification failed.');
		}
		
		if(isset($_GET['echostr'])){
			return $_GET['echostr'];
		}

	}

  function call($url, $data = null, $method = 'GET', $type = 'form-data')
  {
    if (!is_null($data) && $method === 'GET') {
      $method = 'POST';
    }

    switch (strtoupper($method)) {
      case 'GET':
        $response = file_get_contents($url);
        break;
      case 'POST':
        $ch = curl_init($url);

        if ($type === 'xml') {
          $xml = '<xml>';
          foreach ($data as $key => $value) {
            if (is_numeric($value)) {
              $xml .= '<' . $key . '>' . $value . '</' . $key . '>';
            } else {
              $xml .= ' <' . $key . '><![CDATA[' . $value . ']]></' . $key . '>';
            }
          }
          $xml .= '</xml>';
          $data = $xml;

          $content_type = 'application/xml';

        } elseif ($type === 'json') {
          $data = json_encode($data, JSON_UNESCAPED_UNICODE);
          $content_type = 'application/json';
        }

        curl_setopt_array($ch, [
          CURLOPT_POST => TRUE,
          CURLOPT_RETURNTRANSFER => TRUE,
          CURLOPT_POSTFIELDS => $data,
          CURLOPT_HTTPHEADER => isset($content_type) ? [
            'Content-Type: ' . $content_type
          ] : [],
          CURLOPT_SSL_VERIFYHOST => FALSE,
          CURLOPT_SSL_VERIFYPEER => FALSE,
        ]);
        $response = curl_exec($ch);

        if (!$response) {
          // error_log('Weixin API call failed. ' . curl_error($ch) . ' url:' . $url);
        }

        curl_close($ch);
        break;
      default:
        $response = null;
    }

    if(!is_null(json_decode($response)))
    {
      $response = json_decode($response);
    }
    elseif(strpos($response, '<xml') === 0)
    {
      $response = json_decode(json_encode(simplexml_load_string($response, null, LIBXML_NOCDATA)));
    }

    // error_log('Weixin API called: ' . $url);

    if(isset($response->errcode) && $response->errcode)
    {
      // error_log('Weixin API call failed. ' . json_encode($response));
    }

    return $response;
  }
	
	/**
	 * 获得站点到微信的access_token
	 * 并缓存于站点数据库
	 * 可以判断过期并重新获取
	 */
	function get_access_token(){
		
		$stored = json_decode(get_option('wx_access_token'));
		
		if($stored && $stored->expires_at > time()){
			return $stored->token;
		}
		
		$query_args = array(
			'grant_type'=>'client_credential',
			'appid'=>$this->app_id,
			'secret'=>$this->app_secret
		);
		
		$return = $this->call('https://api.weixin.qq.com/cgi-bin/token?' . http_build_query($query_args));
		
		if($return->access_token){
			update_option('wx_access_token', json_encode(array('token'=>$return->access_token, 'expires_at'=>time() + $return->expires_in - 60)));
			return $return->access_token;
		}
		
		// error_log('Get access token failed. ' . json_encode($return));
		
	}
	
	/**
	 * 直接获得用户信息
	 * 仅在用户与公众账号发生消息交互的时候才可以使用
	 * 换言之仅可用于响应微信消息请求的脚本中
	 */
	function get_user_info($openid, $lang = 'zh_CN'){
		
		$url = 'https://api.weixin.qq.com/cgi-bin/user/info?';
		
		$query_vars = array(
			'access_token'=>$this->get_access_token(),
      'openid' => $openid,
			'lang'=>$lang
		);

		$url .= http_build_query($query_vars);
		
		$user_info = $this->call($url);
		
		return $user_info;
		
	}
	
	/**
	 * 根据open_id自动在系统中查找或注册用户，并获得微信用户信息
	 * 仅在用户与公众账号发生消息交互的时候才可以使用
	 */
	function loggin($open_id){
		
		$users = get_users(array('meta_key'=>'wx_openid','meta_value'=>$open_id));

		if(!$users){
			$user_info = $this->get_user_info($open_id);
			$user_id = wp_create_user($user_info->nickname, $open_id);
			add_user_meta($user_id, 'wx_openid', $open_id, true);
			add_user_meta($user_id, 'sex', $user_info->sex, true);
			add_user_meta($user_id, 'country', $user_info->country, true);
			add_user_meta($user_id, 'province', $user_info->province, true);
			add_user_meta($user_id, 'language', $user_info->language, true);
			add_user_meta($user_id, 'headimgurl', $user_info->headimgurl, true);
			add_user_meta($user_id, 'subscribe_time', $user_info->subscribe_time, true);
		}
		else{
			$user_id = $users[0]->ID;
			if($users[0]->user_login === substr($open_id, -8, 8)){
				$user_info = $this->get_user_info($open_id);
				update_user_meta($user_id, 'nickname', $user_info->nickname);
				add_user_meta($user_id, 'sex', $user_info->sex, true);
				add_user_meta($user_id, 'country', $user_info->country, true);
				add_user_meta($user_id, 'province', $user_info->province, true);
				add_user_meta($user_id, 'language', $user_info->language, true);
				add_user_meta($user_id, 'headimgurl', $user_info->headimgurl, true);
				add_user_meta($user_id, 'subscribe_time', $user_info->subscribe_time, true);
			}
		}
		
		wp_set_current_user($user_id);
		
		return $user_id;
		
	}
	
	/**
	 * 生成OAuth授权地址
	 */
	function generate_oauth_url($redirect_uri = null, $state = '', $scope = 'snsapi_userinfo'){

		if (!self::in_wx()) {
			return $this->generate_web_qr_oauth_url($redirect_uri);
		}

		$url = 'https://open.weixin.qq.com/connect/oauth2/authorize?';
		
		$query_args = array(
			'appid'=>$this->app_id,
			'redirect_uri'=>is_null($redirect_uri) ? site_url($_SERVER['REQUEST_URI']) : $redirect_uri,
			'response_type'=>'code',
			'scope'=>$scope,
			'state'=>$state
		);
		
		$url .= http_build_query($query_args) . '#wechat_redirect';
		
		return $url;
		
	}

	function generate_web_qr_oauth_url($redirect_uri = null, $state = '', $scope = 'snsapi_login'){

		$url = 'https://open.weixin.qq.com/connect/qrconnect?';

		$query_args = array(
			'appid'=>$this->app_id,
			'redirect_uri'=>is_null($redirect_uri) ? site_url($_SERVER['REQUEST_URI']) : $redirect_uri,
			'response_type'=>'code',
			'scope'=>$scope,
			'state'=>$state
		);

		$url .= http_build_query($query_args) . '#wechat_redirect';

		return $url;

	}
	
	/**
	 * 生成授权地址并跳转
	 */
	function oauth_redirect($redirect_uri = null, $state = '', $scope = 'snsapi_base'){
		
		if(headers_sent()){
			exit('Could not perform an OAuth redirect, headers already sent');
		}
		
		$url = $this->generate_oauth_url($redirect_uri, $state, $scope);
		
		header('Location: ' . $url);
		exit;
		
	}
	
	/**
	 * 根据一个OAuth授权请求中的code，获得并存储用户授权信息
	 * 通常不应直接调用此方法，而应调用get_oauth_info()
	 */
	function get_oauth_token($code = null, $force = false){
		
		if(is_user_logged_in() && $auth_result = get_user_meta(get_current_user_id(), 'oauth_info', true)){
			if(json_decode($auth_result)->expires_at >= time()){
				return $auth_result->access_token;
			}
		}
		
		if(is_null($code)){
			if(empty($_GET['code'])){

				// 非强制微信网页授权，跳过
				if (!$force) {
					return null;
				}

				header('Location: ' . $this->generate_oauth_url(site_url() . $_SERVER['REQUEST_URI']));
				exit;
			}
			$code = $_GET['code'];
		}
		
		$url = 'https://api.weixin.qq.com/sns/oauth2/access_token?';

		$query_args = array(
			'appid'=>$this->app_id,
			'secret'=>$this->app_secret,
			'code'=>$code,
			'grant_type'=>'authorization_code'
		);

		$auth_result = $this->call($url . http_build_query($query_args));

		if(!isset($auth_result->openid)){
			// error_log('Get OAuth token failed. ' . json_encode($auth_result));
			return false;
		}
		
		$auth_result->expires_at = $auth_result->expires_in + time();
		
		if(is_user_logged_in()){
			$existing_users = get_users(['meta_key' => 'wx_openid', 'meta_value' => $auth_result->openid]);

			if ($existing_users & $existing_users[0]->ID !== wp_get_current_user()->ID) {
				exit('此微信号已经绑定到其他账号。<a href=' . site_url() . '>返回首页</a>');
			}

			update_user_meta(get_current_user_id(), 'wx_openid', $auth_result->openid);
			update_user_meta(get_current_user_id(), 'wx_oauth_info', json_encode($auth_result));
		}else{
			update_option('wx_oauth_token_' . $auth_result->access_token, json_encode($auth_result));
		}
		
		return $auth_result;
	}
	
	/**
	 * 刷新用户OAuth access token
	 * 通常不应直接调用此方法，而应调用get_oauth_info()
	 */
	function refresh_oauth_token($refresh_token){
		
		$url = 'https://api.weixin.qq.com/sns/oauth2/refresh_token?';
		
		$query_args = array(
			'appid'=>$this->app_id,
			'grant_type'=>'refresh_token',
			'refresh_token'=>$refresh_token,
		);
		
		$url .= http_build_query($query_args);
		
		$auth_result = $this->call($url);
		
		return $auth_result;
	}

  public function get_user_openids($next_openid = null)
  {
    $url = 'https://api.weixin.qq.com/cgi-bin/user/get?access_token=' . $this->get_access_token() . '&next_openid=' . $next_openid;

    $result = $this->call($url);

    if(!isset($result->data))
    {
      return [];
    }

    $openids = $result->data->openid;

    // global $wpdb;
    // $wpdb->query("insert ignore into {$wpdb->usermeta} ");

    if($result->next_openid)
    {
      $next_openids = $this->get_user_openids($result->next_openid);

      if($next_openids)
      {
        $openids = array_merge($openids, $next_openids);
      }
    }
    //
    // if(!$next_openid)
    // {
    //   // TODO 取消关注的同步到数据库
    // }

    return $openids;
  }
	
	/**
	 * 根据用户请求的access token，获得用户OAuth信息
	 * 所谓OAuth信息，是用户和站点交互的凭据，里面包含了用户的openid，access token等
	 * 并不包含用户的信息，我们需要根据OAuth信息，通过oauth_get_user_info()去获得
	 */
	function get_oauth_info($access_token = null, $force = false){
		
		// 尝试从请求中获得access token
		if(is_null($access_token) && isset($_GET['access_token'])){
			$access_token = $_GET['access_token'];
		}

		// 如果没能获得access token，我们猜这是一个OAuth授权请求，直接根据code获得OAuth信息
		if (empty($access_token)) {
			return $this->get_oauth_token(null, $force);
		}

		$auth_info = json_decode(get_option('wx_oauth_token_' . $access_token));

		// 从数据库中拿到的access token发现是过期的，那么需要刷新
		if ($auth_info->expires_at <= time()) {
			$auth_info = $this->refresh_oauth_token($auth_info->refresh_token);
		}

		return $auth_info;
	}

	/**
	 * OAuth方式获得用户信息
	 * 注意，access token的scope必须包含snsapi_userinfo，才能调用本函数获取
	 */
	function oauth_get_user_info($lang = 'zh_CN'){
		
		$url = 'https://api.weixin.qq.com/sns/userinfo?';
		
		$auth_info = $this->get_oauth_info();

		if (!$auth_info) {
			return false;
		}

		$query_vars = array(
			'access_token'=>$auth_info->access_token,
			'openid'=>$auth_info->openid,
			'lang'=>$lang
		);
		
		$url .= http_build_query($query_vars);
		
		$user_info = $this->call($url);
		
		return $user_info;
	}
	
	function generate_pay_sign(array $data){
		$data = array_filter($data);
		ksort($data, SORT_STRING);
		$string1 = urldecode(http_build_query($data));
		return strtoupper(md5($string1 . '&key=' . $this->mch_key));
	}
	
	/**
	 * 统一支付接口,可接受 JSAPI/NATIVE/APP下预支付订单,返回预支付订单号。 NATIVE支付返回二维码 code_url。
	 * @param string $order_id
	 * @param float $total_price
	 * @param string $order_name
	 * @param string $attach
	 */
	function unified_order($order_id, $total_price, $openid, $notify_url, $order_name, $trade_type = 'JSAPI', $attach = ' '){
		
		$url = 'https://api.mch.weixin.qq.com/pay/unifiedorder?';
		
		$args = array(
			'appid'=>$this->app_id,
			'mch_id'=>$this->mch_id,
			'nonce_str'=>rand(1E15, 1E16-1),
			'body'=>$order_name,
			'attach'=>$attach,
			'out_trade_no'=>$order_id,
			'total_fee'=>$total_price * 100,
			'spbill_create_ip'=>$_SERVER['REMOTE_ADDR'],
			'time_start'=>date('YmdHis'),
			'notify_url'=>$notify_url,
			'trade_type'=>$trade_type,
			'openid'=>$openid
		);
		
		$args['sign'] = $this->generate_pay_sign($args);
		
		$query_data = array_map(function($value){return (string) $value;}, $args);
		
		$response = $this->call($url . http_build_query($query_data));
		
		if($response->return_code === 'SUCCESS' && $response->result_code === 'SUCCESS'){
			return $trade_type === 'JSAPI' ? $response->prepay_id : $response->code_url;
		}
		else{
			return $response;
		}
	}

	function generate_jsapi_args ($url = null){
	  $nonce_str = (string) rand(1E15, 1E16-1);
	  $timestamp = time();

	  return [
      'appId' => $this->app_id,
      'timestamp' => (string) $timestamp,
      'nonceStr' => $nonce_str,
      'signature' => $this->generate_jsapi_sign($nonce_str, $timestamp, $url)
    ];
  }

  /**
   * 获得供前端使用的JSAPI签名
   */
  function generate_jsapi_sign($nonce_str, $timestamp, $url = null)
  {
    $sign_data = [
      'noncestr'=>$nonce_str,
      'jsapi_ticket'=>$this->get_jsapi_ticket()->ticket,
      'timestamp'=>$timestamp,
      'url'=>$url ?: $_SERVER['HTTP_REFERER']
    ];
    ksort($sign_data, SORT_STRING);
    $sign_string = urldecode(http_build_query($sign_data));
    $sign = sha1($sign_string);
    return $sign;
  }

  /**
   * 获得供后端使用JSAPI密钥
   * @return object
   */
  function get_jsapi_ticket(){
    $jsapi_ticket = get_option('wx_jsapi_ticket');
    if (!$jsapi_ticket || $jsapi_ticket->expires_at < time()) {
      $access_token = $this->get_access_token();
      $jsapi_ticket = $this->call('https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token=' . $access_token . '&type=jsapi');
      $jsapi_ticket->expires_at = time() + $jsapi_ticket->expires_in;
      update_option('wx_jsapi_ticket', $jsapi_ticket);
    }
    return $jsapi_ticket;
  }
	
	/**
	 * 生成支付接口参数，供前端调用
	 * @param string $notify_url 支付结果通知url
	 * @param string $order_id 订单号，必须唯一
	 * @param int $total_price 总价，单位为分
	 * @param string $order_name 订单名称
	 * @param string $attach 附加信息，将在支付结果通知时原样返回
	 * @return array
	 */
	function generate_js_pay_args($prepay_id){
		
		$args = array(
			'appId'=>$this->app_id,
			'timeStamp'=>time(),
			'nonceStr'=>rand(1E15, 1E16-1),
			'package'=>'prepay_id=' . $prepay_id,
			'signType'=>'MD5'
		);
		
		$args['paySign'] = $this->generate_pay_sign($args);
		
		return array_map(function($value){return (string) $value;}, $args);
	}
	
	/**
	 * 生成微信收货地址共享接口参数，供前端调用
	 * @return array
	 */
	function generate_js_edit_address_args(){
		
		$args = array(
			'appId'=>(string) $this->app_id,
			'scope'=>'jsapi_address',
			'signType'=>'sha1',
			'addrSign'=>'',
			'timeStamp'=>(string) time(),
			'nonceStr'=>(string) rand(1E15, 1E16-1)
		);
		
		$sign_args = array(
			'appid'=>$this->app_id,
			'url'=>"http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]",
			'timestamp'=>$args['timeStamp'],
			'noncestr'=>$args['nonceStr'],
			'accesstoken'=>$this->get_oauth_token($_GET['code'])->access_token
		);

		ksort($sign_args, SORT_STRING);
		$string1 = urldecode(http_build_query($sign_args));
		
		$args['addrSign'] = sha1($string1);

		return $args;
		
	}
	
	/**
	 * 生成一个带参数二维码的信息
	 * @param int $scene_id $action_name 为 'QR_LIMIT_SCENE' 时为最大为100000（目前参数只支持1-100000）
	 * @param array $action_info
	 * @param string $action_name 'QR_LIMIT_SCENE' | 'QR_SCENE'
	 * @param int $expires_in
	 * @return array 二维码信息，包括获取的URL和有效期等
	 */
	function generate_qr_code($action_info = array(), $action_name = 'QR_SCENE', $expires_in = '1800'){
		// TODO 过期scene应该要回收
		// TODO scene id 到达100000后无法重置
		// TODO QR_LIMIT_SCENE只能有100000个
		$url = 'https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=' . $this->get_access_token();
		
		$scene_id = get_option('wx_last_qccode_scene_id', 0) + 1;
		
		if($scene_id > 100000){
			$scene_id = 1; // 强制重置
		}
		
		$action_info['scene']['scene_id'] = $scene_id;
		
		$post_data = array(
			'expire_seconds'=>$expires_in,
			'action_name'=>$action_name,
			'action_info'=>$action_info,
		);
		
		$ch = curl_init($url);
		
		curl_setopt_array($ch, array(
			CURLOPT_POST => TRUE,
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_HTTPHEADER => array(
				'Content-Type: application/json'
			),
			CURLOPT_POSTFIELDS => json_encode($post_data)
		));
		
		$response = json_decode(curl_exec($ch));
		
		if(!property_exists($response, 'ticket')){
			return $response;
		}
		
		$qrcode = array(
			'url'=>'https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=' . urlencode($response->ticket),
			'expires_at'=>time() + $response->expire_seconds,
			'action_info'=>$action_info,
			'ticket'=>$response->ticket
		);
		
		update_option('wx_qrscene_' . $scene_id, json_encode($qrcode));
		update_option('wx_last_qccode_scene_id', $scene_id);
		
		return $qrcode;
		
	}
	
	/**
	 * 删除微信公众号会话界面菜单
	 */
	function remove_menu(){
		$url = 'https://api.weixin.qq.com/cgi-bin/menu/delete?access_token=' . $this->get_access_token();
		return $this->call($url);
	}
	
	/**
	 * 创建微信公众号会话界面菜单
	 */
	function create_menu($data){
		
		$url = 'https://api.weixin.qq.com/cgi-bin/menu/create?access_token=' . $this->get_access_token();
		
		$ch = curl_init($url);
		
		curl_setopt_array($ch, array(
			CURLOPT_POST => TRUE,
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_HTTPHEADER => array(
				'Content-Type: application/json'
			),
			CURLOPT_POSTFIELDS => json_encode($data, JSON_UNESCAPED_UNICODE)
		));
		
		$response = json_decode(curl_exec($ch));
		
		return $response;
		
	}
	
	/**
	 * 获得微信公众号会话界面菜单
	 */
	function get_menu(){
		$menu = $this->call('https://api.weixin.qq.com/cgi-bin/menu/get?access_token=' . $this->get_access_token());
		return $menu;
	}
	
	function onmessage($type, $callback){
		
		if(!isset($GLOBALS["HTTP_RAW_POST_DATA"])){
			return false;
		}
		
		xml_parse_into_struct(xml_parser_create(), $GLOBALS["HTTP_RAW_POST_DATA"], $message);

		$message = array_column($message, 'value', 'tag');

		if(!is_array($message)){
			// error_log('XML parse error.');
		}

		// 事件消息			
		if($message['MSGTYPE'] === $type){
			$callback($message);
		}
		
		return $this;
		
	}
	
	function reply_message($reply_message_content, $received_message){
		require plugin_dir_path(__FILE__) . 'template/message_reply.php';
	}
	
	function reply_post_message($reply_posts, $received_message){
		!is_array($reply_posts) && $reply_posts = array($reply_posts);
		$reply_posts_count = count($reply_posts);
		require plugin_dir_path(__FILE__) . 'template/post_message_reply.php';
	}
	
	function transfer_customer_service($received_message){
		require plugin_dir_path(__FILE__) . 'template/transfer_customer_service.php';
	}

	static function in_wx() {
		return strpos($_SERVER['HTTP_USER_AGENT'], ' MicroMessenger/') !== false;
	}

  /**
   * 发送模板消息
   * @param int $to_openid 接受消息的用户模型或用户ID
   * @param string $template_id 模版ID或模板代号，在Config模型中寻找键名为wx_template_id_{$$template_id_or_slug}的值
   * @param string $url 模板消息的链接，空字符串表示无链接
   * @param object|array $data
   * @param string $top_color
   * @return mixed
   */
  function send_template_message($to_openid, $template_id, $url = null, $data = [], $top_color = '#000000')
  {

    // error_log('即将向用户' . $to_openid . ' 发送模板消息 ' . $template_id . ' ' . $url . ' ' . json_encode($data, JSON_UNESCAPED_UNICODE));

    if(is_object($data))
    {
      $data = (array) $data;
    }

    $api = 'https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=' . $this->get_access_token();

    foreach($data as $key => &$value)
    {
      if(is_object($value))
      {
        $value = (array) $value;
      }

      if(!is_array($value))
      {
        $value = [
          'value'=>$value,
        ];
      }

      if($key === 'first')
      {
        $value['value'] = trim($value['value']) . "\n";
      }

      if($key === 'remark')
      {
        $value['value'] = "\n" . trim($value['value']);
      }

      if(!isset($value['color']))
      {
        if($key === 'first')
        {
          $value['color'] = '#888888';
        }
        elseif($key === 'remark')
        {
          $value['color'] = '#888888';
        }
        else
        {
          $value['color'] = '#6FB11B';
        }
      }
    }

    $result = $this->call($api, [
      'touser'=>$to_openid,
      'template_id'=>$template_id,
      'url'=>$url,
      'topcolor'=>$top_color,
      'data'=>$data
    ], 'POST', 'json');

    if(empty($result)) {
      // error_log('向用户 ' . $to_openid . ' 发送模板消息失败');
    } else {
      // error_log('向用户 ' . $to_openid . ' 发送了模板消息 ' . $template_id . ' ' . $url . ' ' . json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    sleep(1);

    return $result && isset($result->errcode) && $result->errcode === 0;
  }

}
