<?php
/**
 * WeChatHelper Plugin
 *
 * @license    GNU General Public License 2.0
 *
 */
class WeChatHelper_Action extends Typecho_Widget implements Widget_Interface_Do
{
    private $db;
    private $redis;
    private $_debug = false;         //调试开关true false，记录微信发来的所有数据
    private $_debugResult = false;   //调试开关true false，记录本接口返回的数据
    private $_WeChatHelper;
    private $_textTpl;
    private $_imageTpl;
    private $_itemTpl;
    private $_imageNum;

    public function __construct($request, $response, $params = NULL)
    {
        parent::__construct($request, $response, $params);

        $this->db = Typecho_Db::get();
        $this->redis = new Typecho_Cache();
        $this->_WeChatHelper = Helper::options()->plugin('WeChatHelper');
        $this->_imageNum = $this->_WeChatHelper->imageNum;
        $this->_imageDefault = $this->_WeChatHelper->imageDefault;
        $this->_textTpl = "<xml>
            <ToUserName><![CDATA[%s]]></ToUserName>
            <FromUserName><![CDATA[%s]]></FromUserName>
            <CreateTime>%s</CreateTime>
            <MsgType><![CDATA[text]]></MsgType>
            <Content><![CDATA[%s]]></Content>
            <FuncFlag>0</FuncFlag>
            </xml>";
        $this->_imageTpl = "<xml>
            <ToUserName><![CDATA[%s]]></ToUserName>
            <FromUserName><![CDATA[%s]]></FromUserName>
            <CreateTime>%s</CreateTime>
            <MsgType><![CDATA[news]]></MsgType>
            <ArticleCount>%s</ArticleCount>
            <Articles>%s</Articles>
            <FuncFlag>1</FuncFlag>
            </xml>";
        $this->_itemTpl = "<item>
            <Title><![CDATA[%s]]></Title>
            <Description><![CDATA[%s]]></Description>
            <PicUrl><![CDATA[%s]]></PicUrl>
            <Url><![CDATA[%s]]></Url>
            </item>";
    }
    /**
     * 链接重定向
     *
     */
    public function link()
    {
        if($this->request->isGet()){
            $this->getAction();
        }
        if($this->request->isPost()){
            $this->postAction();
        }
    }
    /**
     * 校验token
     *
     */
    public function getAction(){
        $echoStr = $this->request->get('echostr');
        if($this->checkSignature($this->_WeChatHelper->token)){
            echo $echoStr;
            exit;
        }else {
            die('Token验证不通过');
        }
    }
    /**
     * 数据
     *
     */
    public function postAction(){
        $options = $this->_WeChatHelper;
        $postStr = file_get_contents("php://input");
        //调试：记录微信发来的所有数据
        if ($this->_debug) {
            $dir = __TYPECHO_ROOT_DIR__ . __TYPECHO_PLUGIN_DIR__ . '/WeChatHelper/';
            $myfile = $dir.'/wechatPostDebug.txt';
            $file_pointer = @fopen($myfile,"a");
            @fwrite($file_pointer,$postStr);
            @fwrite($file_pointer,"\r\n\r\n");
            @fclose($file_pointer);
        }
        //微信消息处理：主流程
        if ($this->checkSignature($options->token) && !empty($postStr)){
            $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
            #$postArr = (array)$postObj;     //转数组
            $fromUsername = $postObj->FromUserName;
            $toUsername = $postObj->ToUserName;
            $time = time();
            $msgType = $postObj->MsgType;
            //消息处理流程
            if($msgType== 'event'){         //事件推送
                $Event = strtolower($postObj->Event);	//事件类型（转为小写）
                switch($Event){
                    case 'subscribe'			:   //订阅事件（扫描带参数二维码事件(用户未关注)）
                        Typecho_Widget::widget('WeChatHelper_Widget_Users')->subscribe($postObj);
                        if(isset($postObj->EventKey) || isset($postObj->Ticket)){
                            // 扫描带参数二维码,未关注推送
                            $EventKey = $postObj->EventKey; //事件KEY值，qrscene_为前缀,后面为二维码的参数值
                            $Ticket   = $postObj->Ticket;   //二维码的ticket
                            $EventKey = str_replace('qrscene_','',$EventKey);
                            $contentStr = Typecho_Widget::widget('WeChatHelper_Widget_Users')->qrcode($postObj, $EventKey);
                            $resultStr = sprintf($this->_textTpl, $fromUsername, $toUsername, $time, $contentStr);
                        }else{
                            // 普通关注
                            $resultStr = $this->baseText($postObj, $options->welcome);
                        }
                        break;
                    case 'unsubscribe'			:   //取消订阅事件
                        Typecho_Widget::widget('WeChatHelper_Widget_Users')->unsubscribe($postObj);
                        break;
                    case 'scan'					:   //扫描带参数二维码事件(用户已关注)
						Typecho_Widget::widget('WeChatHelper_Widget_Users')->subscribe($postObj);
                        $EventKey = $postObj->EventKey;// 事件KEY值，是一个32位无符号整数，即创建二维码时的二维码scene_id
                        $Ticket   = $postObj->Ticket;  //二维码的ticket
                        $contentStr = Typecho_Widget::widget('WeChatHelper_Widget_Users')->qrcode($postObj, $EventKey);
                        $resultStr = sprintf($this->_textTpl, $fromUsername, $toUsername, $time, $contentStr);
                        break;
                    case 'templatesendjobfinish':   //模板消息发送结果提醒
                        $status = $postObj->Status;
                        if($status == 'success'){
                            // 送达成功
                        }elseif($status == 'failed:user block'){
                            // 送达由于用户拒收
                        }elseif($status == 'failed: system failed'){
                            // 其他原因
                        }else{
                            echo "success";
                        }
                        break;
                    case 'location'				:   //上报地理位置事件
                        break;
                    case 'click'				:   //自定义菜单事件（点击菜单拉取消息时的事件推送）
                        $eventkey = strtolower($postObj->EventKey);	    //事件KEY值 转小写
                        switch ($eventkey) {
                            case 'l':   //手气不错
                                $resultStr = $this->luckyPost($postObj);
                                break;
                            case 'n':   //最新文章
                                $resultStr = $this->newPost($postObj);
                                break;
                            case 'r':   //随机文章
                                $resultStr = $this->randomPost($postObj);
                                break;
                            case 'is_send': //取消通知
                                $str = <<<EOF
请输入数字指令：
「91」临时取消 （24小时自动恢复接收）
「92」停止通知
「93」开启通知

说明：20秒内指令有效，超时后需重新点击菜单触发指令。
EOF;
                                $resultStr = $this->baseText($postObj,$str);
                                break;
                            default:
                                # code...
                                break;
                        }
                        break;
                    case 'view'					:   //自定义菜单事件（点击菜单跳转链接时的事件推送）
                        break;
                    case 'scancode_push'		:   //自定义菜单事件（扫码推事件的事件推送）
                        break;
                    case 'scancode_waitmsg'		:   //自定义菜单事件（扫码推事件且弹出“消息接收中”提示框的事件推送）
                        //扫码类型 ScanType                        
                        $ScanType = $postObj->ScanCodeInfo->ScanType;
                        //扫码结果 ScanResult
                        $ScanResult = $postObj->ScanCodeInfo->ScanResult;
                        switch($ScanType){
                            case 'qrcode':	//二维码
                                $resultStr = $this->baseText($postObj, $ScanResult);
                                break;
                            case 'barcode':	//条码
                                $arr = explode(',',$ScanResult);
                                $resultStr = $this->baseText($postObj,'条码类型：'. $arr['0'] ."\n".'内容：'. $arr['1']);
                                break;
                            default :
                                break;
                        }
                        break;
                    case 'pic_sysphoto'			:   //自定义菜单事件（弹出系统拍照发图的事件推送 ）
                        break;
                    case 'pic_photo_or_album'	:   //自定义菜单事件（弹出拍照或者相册发图的事件推送）
                        break;
                    case 'pic_weixin'			:   //自定义菜单事件（弹出微信相册发图器的事件推送）
                        break;
                    case 'location_select'		:   //自定义菜单事件（弹出地理位置选择器的事件推送）
                        break;
                    default						:   //未知事件类型
                        $resultStr = $this->baseText($postObj, $Event.'未知事件类型！');
                        break;
                }
            }else{                         //普通消息
                switch($msgType){
                    case 'text'       :   //文本信息
                        $keyword = trim($postObj->Content);
                        $cmd = strtolower(substr($keyword, 0, 1));  //转小写
                        switch ($cmd) {
                            case 'h':
                                $contentStr = "s 关键词 搜索日志\n ";
                                $resultStr = $this->baseText($postObj, $contentStr);
                                break;
                            case 's':   //搜索
                                $searchParam = substr($keyword, 1);
                                $resultStr = $this->searchPost($postObj, $searchParam);
                                break;
                            default:    //未匹配
                                $resultStr = $this->baseText($postObj);
                                break;
                        }
                        break;
                    case 'image'      :   //图片消息
                        $keyword = '您发送的为图片，链接为:'.$postObj->PicUrl;
                        break;
                    case 'voice'      :   //语音消息
                        $keyword = '您发送的为语音，媒体ID为:'.$postObj->MediaId;
                        break;
                    case 'video'      :   //视频消息
                        $keyword = '您发送的为视频，媒体ID为:'.$postObj->MediaId;
                        break;
                    case 'shortvideo' :   //小视频消息
                        $keyword = '您发送的为小视频，媒体ID为:'.$postObj->MediaId;
                        break;
                    case 'location'   :   //地理位置消息
                        $keyword = '您发送的为地理位置，位置为: '.$postObj->Label.'纬度为: '.$postObj->Location_X.'经度为: '.$postObj->Location_Y;
                        break;
                    case 'link'       :   //链接消息
                        $keyword = '您发送的为链接消息，标题为: '.$postObj->Title.'内容为: '.$postObj->Description.'链接地址为: '.$postObj->Url;
                        break;
                    default           :   //未知消息类型
                        $resultStr = $this->baseText($postObj, $msgType.'未知消息类型！');
                        break;
                }
                //普通消息 功能响应测试
                if (empty($resultStr)){
                    $resultStr = $this->baseText($postObj, $keyword);
                }
            }
            //5秒内唯一回复，切勿删除
            echo empty($resultStr) ? 'success' : $resultStr;
        }else {
            die('Token验证不通过');
        }
        //调试：记录本接口返回的数据
        if ($this->_debugResult) {
            $dir = __TYPECHO_ROOT_DIR__ . __TYPECHO_PLUGIN_DIR__ . '/WeChatHelper/';
            $myfile = $dir.'wechatResultDebug.txt';
            $file_pointer = @fopen($myfile,"a");
            @fwrite($file_pointer, $resultStr);
            @fwrite($file_pointer,"\r\n\r\n");
            @fclose($file_pointer);
        }
    }
    //动作入口
    public function action(){
		if($this->request->is('menus')){  //菜单业务
            Typecho_Widget::widget('WeChatHelper_Widget_Menus')->action();
        }else if($this->request->is('users')){  //用户业务
            Typecho_Widget::widget('WeChatHelper_Widget_Users')->action();
        }
    }
    //校验签名
    private function checkSignature($_token)
    {
        $signature = $this->request->get('signature');
        $timestamp = $this->request->get('timestamp');
        $nonce = $this->request->get('nonce');
        $tmpArr = array($_token, $timestamp, $nonce);
        sort($tmpArr,SORT_STRING);
        $tmpStr = sha1(join('',$tmpArr));
        if($tmpStr == $signature){
            return true;
        }else{
            return false;
        }
    }
    //文本信息
    private function baseText($postObj, $contentStr=''){
        if(empty($contentStr)){
            $contentStr =  '你在说什么? 可以发送\'h\'来查看帮助！';
        }
        $fromUsername = $postObj->FromUserName;
        $toUsername = $postObj->ToUserName;
        $time = time();
        $resultStr = sprintf($this->_textTpl, $fromUsername, $toUsername, $time, $contentStr);
        return $resultStr;
    }
    //最新
    private function newPost($postObj){
        $ArticleCount = $postObj->MsgType == 'event' ? $this->_imageNum : 1;
        #redis取
        $result = $this->redis->get('wechat_newpost'.$ArticleCount);
        if (empty($result)){
            $sql = $this->db->select()->from('table.contents')
            ->where('table.contents.status = ?', 'publish')
            ->where('table.contents.type = ?', 'post')
            ->order('table.contents.created', Typecho_Db::SORT_DESC)
            ->limit($ArticleCount);
            $result = $this->db->fetchAll($sql);
            #redis存
            $this->redis->set('wechat_newpost'.$ArticleCount,$result,21600);
        }
        $resultStr = $this->sqlData($postObj, $result);
        return $resultStr;
    }
    //随机
    private function randomPost($postObj){
        $ArticleCount = $postObj->MsgType == 'event' ? $this->_imageNum : 1;
        #redis取
        $result = $this->redis->get('wechat_randomPost'.rand(1,10));
        if (empty($result)){
            $sql =  $this->db->select()->from('table.contents')
            ->where('table.contents.status = ?','publish')
            ->where('table.contents.type = ?', 'post')
            ->where('table.contents.created <= unix_timestamp(now())', 'post') //添加这一句避免未达到时间的文章提前曝光
            ->limit($ArticleCount)
            ->order('RAND()');
            $result =  $this->db->fetchAll($sql);
            #redis存
            $this->redis->set('wechat_randomPost'.rand(1,10),$result,21600);
        }
        $resultStr = $this->sqlData($postObj, $result);
        return $resultStr;
    }
    //手气不错
    private function luckyPost($postObj){
        $sql =  $this->db->select()->from('table.contents')
            ->where('table.contents.status = ?','publish')
            ->where('table.contents.type = ?', 'post')
            ->where('table.contents.password IS NULL')
            ->limit(1)
            ->order('RAND()');
        $result =  $this->db->fetchAll($sql);

        $resultStr = $this->sqlData($postObj, $result);
        return $resultStr;
    }
    //搜索
    private function searchPost($postObj, $searchParam){
        $ArticleCount = $postObj->MsgType == 'event' ? $this->_imageNum : 1;
        $searchParam = '%' . str_replace(' ', '%', $searchParam) . '%';

        $sql =  $this->db->select()->from('table.contents')
            ->where('table.contents.password IS NULL')
            ->where('table.contents.title LIKE ? OR table.contents.text LIKE ?', $searchParam, $searchParam)
            ->where('table.contents.status = ?', 'publish')
            ->where('table.contents.type = ?', 'post')
            ->order('table.contents.created', Typecho_Db::SORT_DESC)
            ->limit($ArticleCount);
        #redis取
        $hash = md5($sql);
        $result = $this->redis->get('wechat_searchPost'.$hash);
        if (empty($result)){
            $result =  $this->db->fetchAll($sql);
            #redis存
            $this->redis->set('wechat_searchPost'.$hash,$result,3600);
        }
        $resultStr = $this->sqlData($postObj, $result);
        return $resultStr;
    }
    //组装图文消息
    private function sqlData($postObj, $data){
        $_subMaxNum = $this->_WeChatHelper->subMaxNum;
        $resultStr = "";
        $tmpPicUrl = "";
        $num = 0;
        if($data != null){
        	foreach($data as $val){
                $val = Typecho_Widget::widget('Widget_Abstract_Contents')->filter($val);
                $content = Typecho_Common::subStr(strip_tags($val['text']), 0, $_subMaxNum, '...');
                $preg = "/\[.*?\]:\s*(http(s)?:\/\/.*?(jpg|png))/i";            //For markdown first pic.
				preg_match_all( $preg, $val['text'], $matches );
				if(isset($matches) && isset($matches[1][0])){
				 	$tmpPicUrl = $matches[1][0];
				}else{
					$preg = '/<img\ssrc=(\'|\")(.*?)\.(jpg|png)(\'|\")/is';     //default src pic
					preg_match_all( $preg, $val['text'], $matches );
					if(isset($matches) && isset($matches[1][0])){
						$tmpPicUrl = $matches[1][0];
					}else{
						$preg = '/\!\[.*?\]\((http(s)?:\/\/.*?(jpg|png))/i';
						preg_match_all( $preg, $val['text'], $matches );
						if(isset($matches) && isset($matches[1][0])){
							$tmpPicUrl = $matches[1][0];
						}else{
							$tmpPicUrl = $this->_imageDefault;
						}
					}
                }
				//$tmpPicUrl = $tmpPicUrl."?imageView2/1/w/300";        //图片API
                $resultStr .= sprintf($this->_itemTpl, $val['title'], $content, $tmpPicUrl, $val['permalink']);
                $num++;
            }
        }else{
			$resultStr = "没有找到任何信息！";
        }
        $fromUsername = $postObj->FromUserName;
        $toUsername = $postObj->ToUserName;
        $time = time();
        if($data != null){
            $resultStr = sprintf($this->_imageTpl, $fromUsername, $toUsername, $time, $num, $resultStr);
        }else{
            $resultStr = sprintf($this->_textTpl, $fromUsername, $toUsername, $time, $resultStr);
        }
        return $resultStr;
    }
}
