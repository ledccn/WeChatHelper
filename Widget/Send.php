<?php
/**
 * @copyright (c) 2018
 * @file TemplateMessage.php
 * @brief 微信模板消息数据组装类
 * @author 大卫
 * @date 2018年4月12日 23:02:26
 * @version 1.0
 */
class WeChatHelper_Widget_Send extends Widget_Abstract
{
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
    public function select(){

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
	 * @brief 发送模板消息
	 * @return array 或 字符串
	 */
	public function send() {
		p($this->options->routingTable);
		echo 'yes!!';
	}
	/**
	 * @brief 模板消息公共部分
	 * @param string $openid		用户openid
	 * @param string $templateId	模板ID
	 * @param array  $data			模板数据
	 * @param string $url			跳转的url
	 * @param string $topcolor		页头颜色
	 */
	public static function header($openid = null,$templateId,$data = [],$url = '')
	{
		$params = [
            'touser'      => $openid,
            'template_id' => $templateId,
            'url'         => $url,
            'data'        => $data,
        ];
		return $params;
	}
	/**
	 * @brief TM00015	订单支付成功	IT科技	互联网|电子商务
	 * @param
	 * 详细内容：
		{{first.DATA}}
		支付金额：{{orderMoneySum.DATA}}
		商品信息：{{orderProductName.DATA}}
		{{Remark.DATA}}
	 */
	public static function ok($openid,$orderMoneySum = '',$orderProductName = '',$url = '')
	{
		$templateId = 'niGho0GS_gORGtqrrZWYC9ZllJpKdp3RokGl2iHAFes';
		$data = [
			'first'	=>	['value' => "我们已收到您的货款，开始为您打包商品，请耐心等待！",'color' => '#0000ff'],
			'orderMoneySum'	=>	['value' => $orderMoneySum,'color' => '#339933'],
			'orderProductName' =>	['value' => $orderProductName,'color' => '#339933'],
			'Remark' =>	['value' => "如有问题请致电0898-65399901或直接在微信留言，小微将第一时间为您服务！",'color' => '#7d7d7d'],
		];
		return self::header($openid,$templateId,$data,$url);
	}
	/**
	 * @brief OPENTM407362300	商户注册审核通知	IT科技	互联网|电子商务
	 * @param
	 * 详细内容：
		{{first.DATA}}
		用户名：{{keyword1.DATA}}
		手机号：{{keyword2.DATA}}
		时间：{{keyword3.DATA}}
		{{remark.DATA}}
	 */
	public static function sellerReg($openid,$name = '',$mobile = '',$url = '')
	{
		$templateId = 'nnBn_cKwtES-J3khuW2zKA3KLdfZ0Cl7dJI5qq7n4Ac';
		$data = [
			'first'		=>	['value' => "亲爱的管理员，有商户申请加盟平台，请及时审核！",'color' => '#0000ff'],
			'keyword1'	=>	['value' => $name,'color' => '#339933'],
			'keyword2'	=>	['value' => $mobile,'color' => '#339933'],
			'keyword3'	=>	['value' => date("Y-m-d H:i:s"),'color' => '#339933'],
			'Remark'	=>	['value' => "如有问题请致电0898-65399901或直接在微信留言！",'color' => '#7d7d7d'],
		];
		return self::header($openid,$templateId,$data,$url);
	}

	//方法不存在
	public static function __callStatic($funcname, $arguments)
	{
		return "";
	}
}