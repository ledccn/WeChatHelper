<?php
/**
 * @copyright (c) 2014-2019
 * @file send.php
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
	public function __construct($request, $response, $params = NULL) {
        parent::__construct($request, $response, $params);
    }

    /**
     * 查询方法
     *
     * @access public
     * @return Typecho_Db_Query
     */
    public function select($uid = ''){
		return $this->db->fetchRow($this->db->select('uid','openid','nickname','status','is_send','synctime','token','sendsum')->from('table.wch_users')->where('uid = ?', $uid)->limit(1));
	}

    /**
     * 获得所有记录数
     *
     * @access public
     * @param Typecho_Db_Query $condition 查询对象
     * @return integer
     */
    public function size(Typecho_Db_Query $condition){

	}

    /**
     * 增加记录方法
     *
     * @access public
     * @param array $rows 字段对应值
     * @return integer
     */
    public function insert(array $rows){

	}

    /**
     * 更新记录方法
     *
     * @access public
     * @param array $rows 字段对应值
     * @param Typecho_Db_Query $condition 查询对象
     * @return integer
     */
    public function update(array $rows, Typecho_Db_Query $condition){

	}

    /**
     * 删除记录方法
     *
     * @access public
     * @param Typecho_Db_Query $condition 查询对象
     * @return integer
     */
    public function delete(Typecho_Db_Query $condition){

	}
	/**
	 * @brief 发送模板消息 https://www.iyuu.cn/IYUU570100T24654654564654.send?text=abc&desp=defg
	 * 接口发送token算法：IYUU + uid + T + sha1(openid+time+盐) + .send
	 * 消息提取token算法：sha1(openid+time+盐)
	 */
	public function send() {
		$result = array();
		//取请求token：/IYUU1T93951a87f774303da72b37b39cfef2a4f12f86a4.send
		$token = substr(trim($this->request->getPathInfo(),'/'),0,-5);
		//分离用户ID
		$uid = $this->getUid($token);
		if(empty($uid)){
			$result['errcode'] = 404;
			$result['errmsg'] = 'token验证失败。';
			die(Json::encode($result));
		}
		//缓存，取用户数据
		$C = new Typecho_Cache();
		$userArr = $C->get($uid);
		if(empty($userArr)){
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
				$C->set($uid,$userArr,$this->wchUsersExpire);
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
		$push['url'] = 'https://www.iyuu.cn';
		$push['template_id']= '';
		$push['text'] = $this->request->get('text');
		$push['desp'] = $this->request->get('desp');
		//标题、内容长度限制
		#code...

		try{
			$json = Json::encode($push);
			//p($json);
			//放入redis队列，返回队列总数
			$redisNum = $C->rpush("wechatTemplateMessage",$json);
			if (isset($redisNum) && ($redisNum>0)) {
				$code = 0;
				$msg = 'ok';
				//流量监控
				# code...
			}
		}catch(Exception $e){
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
	 * @brief 分离token中uid
	 * 接口发送token算法：IYUU + uid + T + sha1(openid+time+盐)
	 * @param string $token		用户请求token
	 */
	public function getUid($token){
		//验证是否IYUU开头，strpos($token,'T')<15,token总长度小于60(40+10+5)
		return (strlen($token)<60)&&(strpos($token,'IYUU')===0)&&(strpos($token,'T')<15) ? substr($token,4,strpos($token,'T')-4): false;
	}
}