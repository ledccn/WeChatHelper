<?php
class Utils {
    const MENU_CREATE_URL = 'https://api.weixin.qq.com/cgi-bin/menu/create';
    const MENU_REMOVE_URL = 'https://api.weixin.qq.com/cgi-bin/menu/delete';
    public static function getAccessToken(){
        $db = Typecho_Db::get();
        $WCH_expires_in = Typecho_Widget::widget('Widget_Options')->WCH_expires_in;
        $WCH_access_token = Typecho_Widget::widget('Widget_Options')->WCH_access_token;
        $options =Helper::options()->plugin('WeChatHelper');
        if(isset($options->WCH_appid) && isset($options->WCH_appsecret)){
            var_dump($options);
            if(isset($WCH_access_token) && isset($WCH_expires_in) && $WCH_expires_in > time()){
                return $WCH_access_token;
            }else{
                $client = Typecho_Http_Client::get();
                $params = array('grant_type' => 'client_credential',
                                'appid' => $options->WCH_appid, 'secret' => $options->WCH_appsecret);
                $response = $client->setQuery($params)->send('https://api.weixin.qq.com/cgi-bin/token');
                $response = json_decode($response);
                if(isset($response->errcode)){
                    //throw new Typecho_Plugin_Exception(_t('对不起，请求错误。ErrCode：'.$response->errcode.' - ErrMsg：'.$response->errmsg));
                    return NULL;
                }else{
                    $db->query($db->update('table.options')->rows(array('value' => $response->access_token))->where('name = ?', 'WCH_access_token'));
                    $db->query($db->update('table.options')->rows(array('value' => time() + $response->expires_in))->where('name = ?', 'WCH_expires_in'));
                    return $response->access_token;
                }
            }
        }else{
            //throw new Typecho_Plugin_Exception(_t('对不起, 请先在高级功能中填写正确的APP ID和APP Secret。'));
            return NULL;
        }
    }
}
