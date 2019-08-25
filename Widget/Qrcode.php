<?php
/**
 * @copyright (c) 2014-2019
 * @file Qrcode.php
 * @brief 微信带参数二维码
 * @author 大卫科技Blog
 * @date 2019年8月22日
 * @version 1.0
 * @link https://www.iyuu.cn
 */
include_once 'Utils.php';
class WeChatHelper_Widget_Qrcode extends Widget_Abstract
{
	//缓存实例
	private $C;
	//二维码有效时间，以秒为单位。最大不超过2592000（即30天），此字段如果不填，则默认有效期为30秒。
	private $expire_seconds = 120;
	/**
	 * 构造方法，配置应用信息
	 * @param array
	 */
	public function __construct($request, $response, $params = NULL) {
		parent::__construct($request, $response, $params);
		$this->C = new Typecho_Cache();
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
	public function qrcode() {		
		try{
			$QRkey = $this->getQRkey();		
			$QRCode = Utils::getQRCode($QRkey,0,$this->expire_seconds);
			p($QRCode);
			if($QRCode){
				$urlToken = substr($QRCode['url'],23);
				$this->C->set('qrcode'.$QRkey,$urlToken,$this->expire_seconds);
				return;
			}
			$code = 0;
			$msg = 'ok';
		}catch(Exception $e){
			$code = -1;
			$msg = $e->getMessage();
		}
		$result['errcode'] = $code;		//成功是0
		$result['errmsg'] = $msg;	//成功ok
		die(Json::encode($result));
	}
	/**
	 * @brief 获取带参数二维码的场景值ID
	 * @return string 带参数二维码的场景值ID
	 */
	public function getQRkey(){
		$QRkey = rand(1,4294967200);
		if($this->C->get('qrcode'.$QRkey)){
			$this->getQRkey();
		}
		$this->C->set('qrcode'.$QRkey,'iyuu.cn',120);
		return $QRkey;
	}
}