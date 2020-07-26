<?php
/**
 * WeChatHelper Plugin
 *
 * @copyright  Copyright (c) 2013 Binjoo (http://binjoo.net)
 * @license    GNU General Public License 2.0
 *
 */
include_once 'Utils.php';
class WeChatHelper_Widget_Users extends Widget_Abstract implements Widget_Interface_Do
{
    private $siteUrl;
    private $pageSize;
    private $_currentPage;
    private $_countSql;
    private $_total = false;

    public function __construct($request, $response, $params = null)
    {
        parent::__construct($request, $response, $params);
        $this->siteUrl = Helper::options()->siteUrl;
    }
    public function getCurrentPage()
    {
        return $this->_currentPage ? $this->_currentPage : 1;
    }
    public function select()
    {
        return $this->db->select()->from('table.wch_users');
    }
    public function insert(array $options)
    {
        return $this->db->query($this->db->insert('table.wch_users')->rows($options));
    }
    public function update(array $options, Typecho_Db_Query $condition)
    {
        return $this->db->query($condition->update('table.wch_users')->rows($options));
    }
    public function delete(Typecho_Db_Query $condition)
    {
        return $this->db->query($condition->delete('table.wch_users'));
    }
    public function size(Typecho_Db_Query $condition)
    {
        return $this->db->fetchObject($condition->select(array('COUNT(table.wch_users.uid)' => 'num'))->from('table.wch_users'))->num;
    }

    public function execute()
    {
        $this->parameter->setDefault('pageSize=10');
        $this->_currentPage = $this->request->get('page', 1);

        /** 构建基础查询 */
        $select = $this->db->select()->from('table.wch_users')->where('table.wch_users.status = ?', '1');

        /** 给计算数目对象赋值,克隆对象 */
        $this->_countSql = clone $select;

        /** 提交查询 */
        $select->page($this->_currentPage, $this->parameter->pageSize)->order('table.wch_users.uid', Typecho_Db::SORT_DESC);
        $this->db->fetchAll($select, array($this, 'push'));
    }

    public function filter(array $value)
    {
        $date = new Typecho_Date($value['subscribe_time']);
        $value['subscribeFormat'] = $date->format('Y-m-d H:i:s');

        $value['bindVal'] = $value['bind'] ? '是' : '否';

        switch ($value['sex']) {
            case '1':
                $value['sexVal'] = '男';
                break;
            case '2':
                $value['sexVal'] = '女';
                break;
            default:
                $value['sexVal'] = '不明';
                break;
        }

        $value['address'] = $value['country'] . ',' . $value['province'] . ',' . $value['city'];
        if ($value['address'] == ',,') {
            $value['address'] = '';
        }

        if ($value['headimgurl']) {
            #
        } else {
            $value['headimgurl'] = Helper::options()->pluginUrl .'/WeChatHelper/Images/UserHeadDefault.jpg';
        }

        $date = new Typecho_Date($value['created']);
        $value['createdFormat'] = $date->format('Y-m-d H:i:s');
        return $value;
    }

    public function push(array $value)
    {
        $value = $this->filter($value);
        return parent::push($value);
    }

    /**
     * 输出分页
     */
    public function pageNav()
    {
        $query = $this->request->makeUriByRequest('page={page}');

        /** 使用盒状分页 */
        $nav = new Typecho_Widget_Helper_PageNavigator_Box(false === $this->_total ? $this->_total = $this->size($this->_countSql) : $this->_total, $this->_currentPage, $this->parameter->pageSize, $query);
        $nav->render('&laquo;', '&raquo;');
    }

    /**
     * 生成表单
     *
     * @access public
     * @param string $action 表单动作
     * @return Typecho_Widget_Helper_Form_Element
     */
    public function form($action = null)
    {
    }

    /**
     * 关注事件
     */
    public function subscribe($postObj)
    {
        $accessToken = Utils::getAccessToken();
        if (!$this->openIdExists((String) $postObj->FromUserName)) {
            if ($accessToken) {
                $result = $this->apiGetUser((String) $postObj->FromUserName, $accessToken);
                $result = json_decode($result);
                $user['openid'] = $result->openid;
                $user['nickname'] = $result->nickname;
                $user['sex'] = $result->sex;
                $user['language'] = $result->language;
                $user['city'] = $result->city;
                $user['province'] = $result->province;
                $user['country'] = $result->country;
                $user['headimgurl'] = $result->headimgurl;
                $user['subscribe_time'] = $result->subscribe_time;
                $user['synctime'] = time();
                $user['created'] = time();
                $user['status'] = '1';
            } else {
                $user = array('openid' => $postObj->FromUserName, 'subscribe_time' => time(), 'created' => time());
            }
            $user['credits'] = isset($this->options->WCH_subscribe_credit) ? $this->options->WCH_subscribe_credit : '0';
            $user['uid'] = $this->insert($user);
        } else {
            $user['status'] = '1';
            $user['uid'] = $this->update($user, $this->db->sql()->where('openid = ?', (String) $postObj->FromUserName));
        }
    }

    /**
     * 取消关注事件
     */
    public function unsubscribe($postObj)
    {
        if ($this->openIdExists((String) $postObj->FromUserName)) {
            $user['status'] = '0';
            $user['uid'] = $this->update($user, $this->db->sql()->where('openid = ?', (String) $postObj->FromUserName));
        }
    }
    //带参数二维码场景值ID解析
    public function qrcode($postObj, $EventKey='')
    {
        $redis = new Typecho_Cache();
        $fromUsername = $postObj->FromUserName;
        $toUsername = $postObj->ToUserName;
        $time = time();
        $data = $redis->get('qrcode'.$EventKey);
        //$redis->delete('qrcode'.$EventKey);
        if (empty($EventKey) || empty($data)) {
            return '场景值ID获取失败，请重新扫码！';
        }
        //二维码来源及指令解析
        if (isset($data['cmd'])) {
            switch ($data['cmd']) {
                case 'login':
                    # code...
                    break;
                case 'scan':
                    # code...
                    break;
                default:
                    # code...
                    break;
            }
        } else {
            //查询openid是否存在
            $user = $this->openIdExists($fromUsername);
            if (empty($user)) {
                //新用户
                # code...
            } else {
                if (isset($user['token']) && $user['token']) {
                    //token存在
                    $contentStr = "您原来设置的token是："."\r\n".$user['token'];
                } else {
                    //生成新token
                    $newToken['token'] = Utils::getToken($user['uid'], $fromUsername);
                    $newToken['uid'] = $this->update($newToken, $this->db->sql()->where('openid = ?', (String)$fromUsername));
                    $user['token'] = $newToken['token'];
                    $contentStr = "申请消息发送token成功："."\r\n".$user['token'];
                }
                $data['token'] = $user['token'];
                $this->sendMessageByUid($EventKey, $data);
                return $contentStr;
            }
        }
    }
    /**
     * 针对uid推送数据
     * 推送的数据，包含uid字段，表示是给这个uid推送
     */
    public function sendMessageByUid($uid, $message)
    {
        // 建立socket连接到内部推送端口
        $client = stream_socket_client('tcp://127.0.0.1:5678', $errno, $errmsg);
        if (!$client) {
            return "ERROR: $errno - $errmsg";
        }
        // 发送数据，注意5678端口是Text协议的端口，Text协议需要在数据末尾加上换行符
        fwrite($client, json_encode($message)."\n");
        // 读取推送结果
        //$ret = fread($client, 8192);
        fclose($client);
        return 'ok';
    }
    /**
     * 判断OpenId是否存在
     */
    public function openIdExists($openid)
    {
        $select = $this->select()->where('openid = ?', $openid)->limit(1);
        return $this->db->fetchRow($select);
    }

    /**
     * 同步微信用户
     */
    public function syncUserList()
    {
        $accessToken = Utils::getAccessToken();
        if (!$accessToken) {
            $this->widget('Widget_Notice')->set(_t('对不起，更新微信关注者数据失败，请重试！'), 'error');
            $this->response->redirect(Helper::url('WeChatHelper/Page/Users.php&page='.$this->_currentPage));
        }
        $client = Typecho_Http_Client::get();
        $params = array('access_token' => $accessToken);
        $response = $client->setQuery($params)->send('https://api.weixin.qq.com/cgi-bin/user/get');
        $response = json_decode($response);

        foreach ($response->data->openid as $val) {
            $user = array();
            /*
            $client = Typecho_Http_Client::get();
            $params['openid'] = $val;
            $result = $client->setQuery($params)->send('https://api.weixin.qq.com/cgi-bin/user/info');
            $result = json_decode($result);
            $user['openid'] = $result->openid;
            $user['nickname'] = $result->nickname;
            $user['sex'] = $result->sex;
            $user['language'] = $result->language;
            $user['city'] = $result->city;
            $user['province'] = $result->province;
            $user['country'] = $result->country;
            $user['headimgurl'] = $result->headimgurl;
            $user['subscribe_time'] = $result->subscribe_time;
            $user['synctime'] = time();
            */
            $exists = $this->openIdExists($val);
            if ($exists) {
                $user['status'] = '1';
                $user['uid'] = $this->update($user, $this->db->sql()->where('openid = ?', $val));
            } else {
                $user['openid'] = $val;
                $user['created'] = time();
                $user['uid'] = $this->insert($user);
            }
        }

        $this->widget('Widget_Notice')->set(_t('恭喜您，同步微信关注者列表成功！'), 'success');
        $this->response->redirect(Helper::url('WeChatHelper/Page/Users.php&page='.$this->_currentPage));
    }

    /**
     * 同步微信用户信息
     * https://mp.weixin.qq.com/wiki?t=resource/res_main&id=mp1421140839
     */
    public function syncUserInfo()
    {
        $uid = $this->request->get('uid');
        $user = $this->db->fetchRow($this->select()->where('uid = ?', $uid)->limit(1));

        $accessToken = Utils::getAccessToken();
        $client = Typecho_Http_Client::get();
        $params = array('access_token' => $accessToken, 'openid' => $user['openid']);
        try {
            $result = $client->setQuery($params)->send('https://api.weixin.qq.com/cgi-bin/user/info');
        } catch (Exception $e) {
            $this->widget('Widget_Notice')->set(_t('对不起，更新微信用户信息失败，请重试！'), 'error');
            $this->response->redirect(Helper::url('WeChatHelper/Page/Users.php&page='.$this->_currentPage));
        }
        $result = json_decode($result);

        if ($result->subscribe) {
            $user['status'] = '1';
            $user['openid'] = $result->openid;
            $user['nickname'] = $result->nickname;
            $user['sex'] = $result->sex;
            $user['language'] = $result->language;
            $user['city'] = $result->city;
            $user['province'] = $result->province;
            $user['country'] = $result->country;
            $user['headimgurl'] = $result->headimgurl;
            $user['subscribe_time'] = $result->subscribe_time;
            $user['synctime'] = time();
        } else {
            $user['status'] = '0';
        }
        $this->update($user, $this->db->sql()->where('uid = ?', $user['uid']));

        $this->widget('Widget_Notice')->highlight('users-uid-'.$user['uid']);
        $this->widget('Widget_Notice')->set(_t('微信用户 OpenID %s 更新成功！', $user['openid']), 'success');
        $this->response->redirect(Helper::url('WeChatHelper/Page/Users.php&page='.$this->_currentPage));
    }
    //https://mp.weixin.qq.com/wiki?t=resource/res_main&id=mp1421140839
    public function apiGetUser($openid, $accessToken = null)
    {
        if (!$accessToken) {
            $accessToken = Utils::getAccessToken();
        }
        if ($accessToken) {
            $client = Typecho_Http_Client::get();
            $params = array('access_token' => $accessToken, 'openid' => $openid);
            $result = $client->setQuery($params)->send('https://api.weixin.qq.com/cgi-bin/user/info');
        } else {
            throw new Typecho_Plugin_Exception(_t('对不起，请求AccessToken出现异常。'));
        }
        return $result;
    }
    //动作入口
    public function action()
    {
        $this->security->protect();
        $this->on($this->request->is('do=syncList'))->syncUserList();
        $this->on($this->request->is('do=syncInfo'))->syncUserInfo();
        $this->response->redirect($this->options->adminUrl);
    }
}
