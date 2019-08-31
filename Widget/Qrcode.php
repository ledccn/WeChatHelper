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
	 * @brief 生成临时整型QR_SCENE带参数二维码 https://developers.weixin.qq.com/doc/offiaccount/Account_Management/Generating_a_Parametric_QR_Code.html
	 * 两种认证方式：方法一：ticket+QRkey；方法二：url+QRkey【$urlToken = substr($QRarray['url'],23);】，目前采用第一种！
	 * 工作流程：QRkey为键，QRCode存入Redis缓存
	 */
	public function qrcode() {
		try{
			$QRkey = $this->getQRkey();
			$QRCode = Utils::getQRCode($QRkey,0,$this->expire_seconds);
			if($QRCode){
				//认证方法：ticket+QRkey
				$QRCode['uid'] = $QRkey;
				unset($QRCode['url']);
				$json = json_encode($QRCode);
				$this->C->set('qrcode'.$QRkey,$QRCode,$this->expire_seconds);
				echo $json;
				return;
			}
			$code = -1;
			$msg = 'server error';
		}catch(Exception $e){
			$code = -1;
			$msg = $e->getMessage();
		}
		$result['errcode'] = $code;
		$result['errmsg'] = $msg;
		die(Json::encode($result));
	}
	/**
	 * @brief 获取唯一带参数二维码的场景值ID
	 * @return string 唯一场景值ID
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