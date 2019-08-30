<?php
class Utils {
    const MENU_CREATE_URL = 'https://api.weixin.qq.com/cgi-bin/menu/create';
    const MENU_REMOVE_URL = 'https://api.weixin.qq.com/cgi-bin/menu/delete';
    const API_URL_PREFIX = 'https://api.weixin.qq.com/cgi-bin';
    const AUTH_URL = '/token';		//请求access_token
	const TEMPLATE_SEND_URL = '/message/template/send?access_token=';	//模板消息发送
	const QRCODE_CREATE_URL='/qrcode/create?';	//生成带参数二维码
	const QRCODE_IMG_URL='https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=';	//通过ticket换取二维码链接
	const SHORT_URL='/shorturl?';	//长链接转短链接接口
    private static $options;
	private static $access_token;
	private static $expires_in;
	/**
     * 获取access_token，从机只能从redis获取
	 * @param bool $master	是否主机的标志，只有主机能从微信获取token刷新redis缓存，从机只能读redis缓存！！！否则会造成模板发送进程执行错误！！！
     * @return NULL | string
     */
    public static function getAccessToken($master = false){
		//取redis缓存
		$C = new Typecho_Cache();
		self::$access_token = $C->get('WCH_access_token');
		self::$expires_in = $C->get('WCH_expires_in');
        //$WCH_expires_in = Typecho_Widget::widget('Widget_Options')->WCH_expires_in;
        //$WCH_access_token = Typecho_Widget::widget('Widget_Options')->WCH_access_token;
		if (self::valid_access_token()) {
			return self::$access_token;
		}else{
			//access_token只能从redis获取
			if(!$master){
				return NULL;
			}
			self::$options = Helper::options()->plugin('WeChatHelper');
			if(isset(self::$options->WCH_appid) && isset(self::$options->WCH_appsecret)){
				$client = Typecho_Http_Client::get();
				$params = array('grant_type' => 'client_credential','appid' => self::$options->WCH_appid, 'secret' => self::$options->WCH_appsecret);
				$response = $client->setQuery($params)->send(self::API_URL_PREFIX.self::AUTH_URL);
				$response = json_decode($response);
				if(isset($response->errcode)){
					//throw new Typecho_Plugin_Exception(_t('对不起，请求错误。ErrCode：'.$response->errcode.' - ErrMsg：'.$response->errmsg));
					return NULL;
				}else{
					//存数据库
					$db = Typecho_Db::get();
					$db->query($db->update('table.options')->rows(array('value' => $response->access_token))->where('name = ?', 'WCH_access_token'));
					$db->query($db->update('table.options')->rows(array('value' => time() + $response->expires_in))->where('name = ?', 'WCH_expires_in'));
					self::$access_token = $response->access_token;
					//存redis缓存
					$C->set('WCH_access_token',$response->access_token,$response->expires_in-300);
					$C->set('WCH_expires_in',time()+$response->expires_in-300,$response->expires_in-300);
					return $response->access_token;
				}
			}else{
				//throw new Typecho_Plugin_Exception(_t('对不起, 请先在高级功能中填写正确的APP ID和APP Secret。'));
				return NULL;
			}
		}
    }
	/**
     * 校验access_token是否过期
     * @return bool
     */
    private static function valid_access_token()
    {
        return isset(self::$access_token) && isset(self::$expires_in) && self::$expires_in > time();
	}
    /**
	 * 发送模板消息
	 * @param array $data 消息结构
	 * ｛
			"touser":"OPENID",
			"template_id":"ngqIpbwh8bUfcSsECmogfXcV14J0tQlEpBO27izEYtY",
			"url":"http://weixin.qq.com/download",
			"topcolor":"#FF0000",
			"data":{
				"参数名1": {
					"value":"参数",
					"color":"#173177"	 //参数颜色
					},
				"Date":{
					"value":"06月07日 19时24分",
					"color":"#173177"
					},
				"CardNumber":{
					"value":"0426",
					"color":"#173177"
					},
				"Type":{
					"value":"消费",
					"color":"#173177"
					}
			}
		}
	 * @return boolean|array
	 */
	public static function sendTemplateMessage($data){
		if(!self::getAccessToken()){
			return false;
		}
		$client = Typecho_Http_Client::get();
		$response = $client->setData(self::json_encode($data))->send(self::API_URL_PREFIX.self::TEMPLATE_SEND_URL.self::$access_token);
		if($response){
			$json = json_decode($response,true);
			if (!$json || !empty($json['errcode'])) {
				return false;
			}
			return $json;
		}
		return false;
    }
	/**
	 * 创建二维码ticket
	 * @param int|string $scene_id 自定义追踪id,临时二维码只能用数值型
	 * @param int $type 0:临时整形二维码；1:临时字符串形二维码；2:永久整形二维码(此时expire参数无效)；3:永久字符串型二维码(此时expire参数无效)
	 * @param int $expire 临时二维码有效期，最大2592000（即30天）
	 * @return array('ticket'=>'qrcode字串','expire_seconds'=>604800,'url'=>'二维码图片解析后的地址')
	 */
	public static function getQRCode($scene_id='',$type=0,$expire=120){
		if(!self::getAccessToken()) return false;
		if (empty($scene_id)) return false;
		switch ((string)$type) {
			case '0':
				if (!is_numeric($scene_id))
					return false;	//场景值ID，临时二维码时为32位非0整型
				$action_name = 'QR_SCENE';
				$action_info = array('scene'=>(array('scene_id'=>$scene_id)));
				break;
			case '1':
				if (!is_string($scene_id))
					return false;	//场景值ID（字符串形式的ID），字符串类型，长度限制为1到64
				$action_name = 'QR_STR_SCENE';
				$action_info = array('scene'=>(array('scene_str'=>$scene_id)));
				break;
			case '2':
				if (!is_numeric($scene_id))
					return false;	//永久二维码时最大值为100000（目前参数只支持1--100000）
				$action_name = 'QR_LIMIT_SCENE';
				$action_info = array('scene'=>(array('scene_id'=>$scene_id)));
				break;
			case '3':
				if (!is_string($scene_id))
					return false;	//场景值ID（字符串形式的ID），字符串类型，长度限制为1到64
				$action_name = 'QR_LIMIT_STR_SCENE';
				$action_info = array('scene'=>(array('scene_str'=>$scene_id)));
				break;

			default:
				return false;
		}

		$data = array(
			'action_name'    => $action_name,
			'expire_seconds' => $expire,
			'action_info'    => $action_info
		);
		if ($type > 1) {
			unset($data['expire_seconds']);
		}
		$client = Typecho_Http_Client::get();
		$result = $client->setData(self::json_encode($data))->send(self::API_URL_PREFIX.self::QRCODE_CREATE_URL.'access_token='.self::$access_token);
		if ($result) {
			$json = json_decode($result,true);
			if (!$json || !empty($json['errcode'])) {
				//$this->errCode = $json['errcode'];
				//$this->errMsg = $json['errmsg'];
				return false;
			}
			return $json;
		}
		return false;
	}

	/**
	 * 获取二维码图片
	 * @param string $ticket 传入由getQRCode方法生成的ticket参数
	 * @return string url 返回http地址
	 */
	public static function getQRUrl($ticket) {
		return self::QRCODE_IMG_URL.urlencode($ticket);
	}

	/**
	 * 长链接转短链接接口
	 * @param string $long_url 传入要转换的长url
	 * @return boolean|string url 成功则返回转换后的短url
	 */
	public static function getShortUrl($long_url){
		if(!self::getAccessToken()) return false;
		if (empty($long_url)) return false;
	    $data = array(
            'action'=>'long2short',
            'long_url'=>$long_url
		);
		$client = Typecho_Http_Client::get();
		$result = $client->setData(self::json_encode($data))->send(self::API_URL_PREFIX.self::SHORT_URL.'access_token='.self::$access_token);
	    if ($result)
	    {
	        $json = json_decode($result,true);
	        if (!$json || !empty($json['errcode'])) {
	            //$this->errCode = $json['errcode'];
	            //$this->errMsg = $json['errmsg'];
	            return false;
	        }
	        return $json['short_url'];
	    }
	    return false;
	}
    /**
	 * 微信api不支持中文转义的json结构
	 * @param array $arr
	 */
	public static function json_encode($arr) {
		if (count($arr) == 0) return "[]";
		$parts = array ();
		$is_list = false;
		//Find out if the given array is a numerical array
		$keys = array_keys ( $arr );
		$max_length = count ( $arr ) - 1;
		if (($keys [0] === 0) && ($keys [$max_length] === $max_length )) { //See if the first key is 0 and last key is length - 1
			$is_list = true;
			for($i = 0; $i < count ( $keys ); $i ++) { //See if each key correspondes to its position
				if ($i != $keys [$i]) { //A key fails at position check.
					$is_list = false; //It is an associative array.
					break;
				}
			}
		}
		foreach ( $arr as $key => $value ) {
			if (is_array ( $value )) { //Custom handling for arrays
				if ($is_list)
					$parts [] = self::json_encode ( $value ); /* :RECURSION: */
				else
					$parts [] = '"' . $key . '":' . self::json_encode ( $value ); /* :RECURSION: */
			} else {
				$str = '';
				if (! $is_list)
					$str = '"' . $key . '":';
				//Custom handling for multiple data types
				if (!is_string ( $value ) && is_numeric ( $value ) && $value<2000000000)
					$str .= $value; //Numbers
				elseif ($value === false)
				$str .= 'false'; //The booleans
				elseif ($value === true)
				$str .= 'true';
				else
					$str .= '"' . addslashes ( $value ) . '"'; //All other things
				// :TODO: Is there any more datatype we should be in the lookout for? (Object?)
				$parts [] = $str;
			}
		}
		$json = implode ( ',', $parts );
		if ($is_list)
			return '[' . $json . ']'; //Return numerical JSON
		return '{' . $json . '}'; //Return associative JSON
	}
	/**
	 * @brief 分离token中的用户uid
	 * token算法：IYUU + uid + T + sha1(openid+time+盐)
	 * @param string $token		用户请求token
	 */
	public static function getUid($token){
		//验证是否IYUU开头，strpos($token,'T')<15,token总长度小于60(40+10+5)
		return (strlen($token)<60)&&(strpos($token,'IYUU')===0)&&(strpos($token,'T')<15) ? substr($token,4,strpos($token,'T')-4): false;
	}
	/**
     * 生成消息发送token和消息提取token
     * 算法：IYUU + uid + T + sha1(openid+time+盐)
     * @access public
     * @param string $uid 用户uid
     * @param string $openid 微信用户唯一
     * @return string
     */
    public static function getToken($uid='', $openid=''){
		$str = self::createNoncestr(32);
        return 'IYUU'.$uid.'T'.sha1($str.$openid.time());
    }
	/**
     * 产生随机字符串
     * @param int $length 指定字符长度
     * @param string $str 字符串前缀
     * @return string
     */
    public static function createNoncestr($length = 32, $str = "")
    {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }
}
