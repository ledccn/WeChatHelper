<?php
class Utils {
    const MENU_CREATE_URL = 'https://api.weixin.qq.com/cgi-bin/menu/create';
    const MENU_REMOVE_URL = 'https://api.weixin.qq.com/cgi-bin/menu/delete';
    const API_URL_PREFIX = 'https://api.weixin.qq.com/cgi-bin';
    const AUTH_URL = '/token';
    const TEMPLATE_SEND_URL = '/message/template/send?access_token=';
    private static $options;
	private static $access_token;
    public static function getAccessToken(){
        $db = Typecho_Db::get();
        $WCH_expires_in = Typecho_Widget::widget('Widget_Options')->WCH_expires_in;
        $WCH_access_token = Typecho_Widget::widget('Widget_Options')->WCH_access_token;
        self::$options = Helper::options()->plugin('WeChatHelper');
        if(isset(self::$options->WCH_appid) && isset(self::$options->WCH_appsecret)){
            if(isset($WCH_access_token) && isset($WCH_expires_in) && $WCH_expires_in > time()){
                self::$access_token = $WCH_access_token;
                return $WCH_access_token;
            }else{
                $client = Typecho_Http_Client::get();
                $params = array('grant_type' => 'client_credential','appid' => self::$options->WCH_appid, 'secret' => self::$options->WCH_appsecret);
                $response = $client->setQuery($params)->send(self::API_URL_PREFIX.self::AUTH_URL);
                $response = json_decode($response);
                if(isset($response->errcode)){
                    //throw new Typecho_Plugin_Exception(_t('对不起，请求错误。ErrCode：'.$response->errcode.' - ErrMsg：'.$response->errmsg));
                    return NULL;
                }else{
                    $db->query($db->update('table.options')->rows(array('value' => $response->access_token))->where('name = ?', 'WCH_access_token'));
                    $db->query($db->update('table.options')->rows(array('value' => time() + $response->expires_in))->where('name = ?', 'WCH_expires_in'));
                    self::$access_token = $response->access_token;
                    return $response->access_token;
                }
            }
        }else{
            //throw new Typecho_Plugin_Exception(_t('对不起, 请先在高级功能中填写正确的APP ID和APP Secret。'));
            return NULL;
        }
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
        if (self::getAccessToken()) {
            $client = Typecho_Http_Client::get();
            $response = $client->setData(self::json_encode($data))->send(self::API_URL_PREFIX.self::TEMPLATE_SEND_URL.self::access_token);
            if($response){
                $json = json_decode($response,true);
                if (!$json || !empty($json['errcode'])) {
                    return false;
                }
                return $json;
            }
            return false;
        }else {
            return false;
        }		
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

}
