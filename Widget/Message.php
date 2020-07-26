<?php
/**
 * @brief 微信模板消息
 * @author 大卫科技Blog
 * @date 2019年8月22日
 * @version 1.0
 * @link https://www.iyuu.cn
 */
include_once 'Utils.php';
class WeChatHelper_Widget_Message extends Widget_Abstract
{
    //缓存时间设置，默认1天
    private $wchUsersExpire = 86400;
    /**
     * 构造方法，配置应用信息
     * @param array
     */
    public function __construct($request, $response, $params = null)
    {
        parent::__construct($request, $response, $params);
    }

    /**
     * 查询方法
     *
     * @access public
     * @return Typecho_Db_Query
     */
    public function select($uid = '')
    {
        return $this->db->fetchRow($this->db->select('uid', 'openid', 'nickname', 'status', 'is_send', 'synctime', 'token', 'sendsum')->from('table.wch_users')->where('uid = ?', $uid)->limit(1));
    }

    /**
     * 获得所有记录数
     *
     * @access public
     * @param Typecho_Db_Query $condition 查询对象
     * @return integer
     */
    public function size(Typecho_Db_Query $condition)
    {
    }

    /**
     * 增加记录方法
     *
     * @access public
     * @param array $rows 字段对应值
     * @return integer
     */
    public function insert(array $rows)
    {
    }

    /**
     * 更新记录方法
     *
     * @access public
     * @param array $rows 字段对应值
     * @param Typecho_Db_Query $condition 查询对象
     * @return integer
     */
    public function update(array $rows, Typecho_Db_Query $condition)
    {
    }

    /**
     * 删除记录方法
     *
     * @access public
     * @param Typecho_Db_Query $condition 查询对象
     * @return integer
     */
    public function delete(Typecho_Db_Query $condition)
    {
    }
    /**
     * 查询方法
     *
     * @access public
     * @return Typecho_Db_Query
     */
    public function selectMessage($hash = '')
    {
        return $this->db->fetchRow($this->db->select('uid', 'msgid', 'message', 'created')->from('table.wch_template_message')->where('hash = ?', $hash)->limit(1));
    }
    /**
     * @brief 发送模板消息 https://www.iyuu.cn/IYUU570100T24654654564654.send?text=abc&desp=defg
     * 接口发送token算法：IYUU + uid + T + sha1(openid+time+盐) + .send
     */
    public function send()
    {
        $result = array();
        //取请求token：/IYUU1T93951a87f774303da72b37b39cfef2a4f12f86a4.send
        $token = substr(trim($this->request->getPathInfo(), '/'), 0, -5);
        //分离用户ID
        $uid = Utils::getUid($token);
        if (empty($uid)) {
            $result['errcode'] = 404;
            $result['errmsg'] = 'token验证失败。';
            die(Json::encode($result));
        }
        //缓存，取用户数据
        $C = new Typecho_Cache();
        $userArr = $C->get($uid);
        if (empty($userArr)) {
            //缓存取失败
            $userArr = $this->select($uid);	//数据库取uid
            if (empty($userArr)) {
                //数据库取失败
                //加入流控：同IP 1分钟失败10次，IP封禁30分钟；同用户 1分钟失败30次，IP封禁30分钟；
                $result['errcode'] = 404;		//成功是0
                $result['errmsg'] = 'token验证失败';		//请求成功ok success
                die(Json::encode($result));
            } else {
                //数据库取成功、存缓存
                $C->set($uid, $userArr, $this->wchUsersExpire);
            }
        }
        //验证token
        if ($userArr['token'] === $token) {
            //p($userArr);

            //is_send值：正常0，临时禁1，永久禁止2;
            if (empty($userArr['is_send'])) {
                //验证每天上限500条、同内容5分钟不能重复发送、不同内容1分钟30条、24小时请求超过1000次临时封禁24小时。
            } else {
                $msg = $userArr['is_send']==1 ? '临时禁用' : '永久禁用';
                $result['errcode'] = 404;
                $result['errmsg'] = '账户被'.$msg;
                die(Json::encode($result));
            }
        } else {
            $result['errcode'] = 404;		//成功是0
            $result['errmsg'] = 'token验证失败';	//成功ok
            die(Json::encode($result));
        }
        //获取模板消息各项参数：openid,text,desp,url
        $push['uid'] = $userArr['uid'];
        $push['openid'] = $userArr['openid'];
        $push['url'] = Typecho_Common::url('/read?hash=', Typecho_Widget::Widget('Widget_Options')->index);
        $push['template_id']= '';
        $push['text'] = $this->request->get('text');
        $push['desp'] = $this->request->get('desp');
        //标题、内容长度限制
        #code...

        try {
            $json = Json::encode($push);
            //p($json);
            //放入redis队列，返回队列总数
            $redisNum = $C->rpush("wechatTemplateMessage", $json);
            if (isset($redisNum) && ($redisNum>0)) {
                $code = 0;
                $msg = 'ok';
                //流量监控
                # code...
            }
        } catch (Exception $e) {
            //echo $e->getMessage();
            $code = -1;
            $msg = 'server error';
            //入队异常，发送警报
            # code...
        }
        $result['errcode'] = $code;		//成功是0
        $result['errmsg'] = $msg;	//成功ok
        die(Json::encode($result));
        //p($this->request->getPathInfo());
        //p(unserialize(Helper::options()->panelTable));
    }
    /**
     * @brief 微信模板消息读取接口
     * 消息提取token算法：IYUU + uid + T + sha1(openid+time+盐)
     * @param string $hash 消息提取凭证 GET请求携带的参数
     * 缓存内的消息结构：
       Array
        (
            [uid] => 3
            [msgid] => 965377193167781890
            [message] => Array
                (
                    [url] => https://www.iyuu.cn
                    [text] => 有人在您的博客发表了评论
                    [desp] => **lingguang** 在 [「简单美化了一下w」](https://www.38blog.com/index.php/archives/61.html "简单美化了一下w") 中说到:

                    > test
                )
        )
     */
    public function read()
    {
        //消息提取凭证
        $hash = $this->request->get('hash');
        if (empty($hash)) {
            return;
        }
        //分离用户ID
        $uid = Utils::getUid($hash);
        if (empty($uid)) {
            $result['errcode'] = 404;
            $result['errmsg'] = 'token验证失败。';
            die(Json::encode($result));
        }
        //缓存，取消息
        $C = new Typecho_Cache();
        $message = $C->get('message'.$hash);
        if (empty($message)) {
            //缓存取失败
            $message = $this->selectMessage($hash);	//数据库取消息
            if (empty($message)) {
                //数据库取失败
                //加入流控：同IP 1分钟失败10次，IP封禁30分钟；同用户 1分钟失败30次，IP封禁30分钟；
                $result['errcode'] = 404;
                $result['errmsg'] = 'token验证失败';
                die(Json::encode($result));
            } else {
                $json = $message['message'];
                $message['message'] = json_decode($json, true);
                //数据库取成功、存缓存
                $C->set('message'.$hash, $message, 3600);
            }
        }

        $html = '<h1>'.$message['message']['text'].'</h1>';
        $desp = $message['message']['desp'];
        $html.= Markdown::convert($desp);
        $header = <<<html
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
<title>查看消息 - 爱语飞飞</title>
<style>
.markdown-here-wrapper{word-wrap:break-word;}
p{font-size:15px; line-height:28px; color:#595959;font-family:微软雅黑}
pre, code{font-size:14px;  font-family: Roboto, 'Courier New', Consolas, Inconsolata, Courier, monospace;}
code{margin:0 3px;  padding:0 6px;  white-space: pre-wrap;  background-color:#F8F8F8;  border-radius:2px;  display: inline;}
pre{font-size:15px;  line-height:20px;}
precode{white-space: pre; overflow:auto; border-radius:3px; padding:5px10px; display: block !important;}
strong, b{color:#BF360C;}
em, i{color:#009688;}
big{font-size:22px;  color:#009688;  font-weight: bold;  vertical-align: middle;  border-bottom:1px solid #eee;}
small{font-size:12px;  line-height:22px;}
hr{border-bottom:0.05em dotted #eee;  margin:10px auto;}
p{margin:15px 5px!important;}
table, pre, dl, blockquote, q, ul, ol{margin:10px 5px;}
ul, ol{padding-left:10px;}
li{margin:5px;}
lip{margin:5px 0!important;}
ulul, ulol, olul, olol{margin:0;  padding-left:10px;}
olol, ulol{list-style-type: lower-roman;}
ululol, ulolol, olulol, ololol{list-style-type: lower-alpha;}
dl{padding:0;}
dldt{font-size:1em;  font-weight: bold;  font-style: italic;}
dldd{margin:0 0 10px;  padding:0 10px;}
blockquote, q{border-left:3px solid #009688;  padding:0 10px;  color:#777;  quotes: none;}
blockquote::before, blockquote::after, q::before, q::after{content: none;}
h1, h2, h3, h4, h5, h6{margin:20px 0 10px;  padding:0;  font-weight: bold;  color:#009688;}
h1{font-size:24px;  border-bottom:1px solid #ddd;}
h2{font-size:22px;  border-bottom:1px solid #eee;}
h3{font-size:18px; text-align: center;}
h4{font-size:18px;}
h5{font-size:16px;}
h6{font-size:16px; color:#777;}
table{padding:0;  border-collapse: collapse;  border-spacing:0;  font-size:1em;  font: inherit;  border:0;}
tbody{margin:0;  padding:0;  border:0;}
tabletr{border:0;  border-top:1px solid #CCC;  background-color: white;  margin:0;  padding:0;}
tabletr:nth-child(2n){background-color:#F8F8F8;}
tabletrth, tabletrtd{font-size:16px;  border:1px solid #CCC;  margin:0;  padding:5px10px;}
tabletrth{font-weight: bold;  background-color:#F0F0F0;}
</style>
</head>
<body>
<div class="markdown-here-wrapper">
html;

        $footer = <<<html
</div>
</body>
</html>
html;
        echo $header.$html.$footer;
        //p($message);
    }
}
