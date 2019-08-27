<?php
/**
 * Typecho微信助手 <strong style="color:red;">依赖Redis缓存</strong>
 * 对<a href="https://github.com/binjoo/WeChatHelper" target="_blank">冰剑</a>进行精简的版本。
 *
 * @package WeChatHelper
 * @author 大卫科技Blog
 * @version 2.3.0
 * @link https://www.iyuu.cn
 */
class WeChatHelper_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     *
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {
        $msg = self::installDb();
        //微信接口
        Helper::addRoute('wechat', '/wechat', 'WeChatHelper_Action', 'link');
        //模板消息发送接口
        Helper::addRoute('send', '/[send:alpha].send', 'WeChatHelper_Widget_Send', 'send');
		//带参数二维码接口
        Helper::addRoute('qrcode', '/qrcode', 'WeChatHelper_Widget_Qrcode', 'qrcode');
        //后台管理接口
        Helper::addAction('WeChat', 'WeChatHelper_Action');
        //后台菜单
        $index = Helper::addMenu('微信助手');
        Helper::addPanel($index, 'WeChatHelper/Page/Users.php', '用户管理', '用户管理', 'administrator');
        Helper::addPanel($index, 'WeChatHelper/Page/Menus.php', '自定义菜单', '自定义菜单', 'administrator');
        return $msg;
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     *
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate()
    {
        $dropTable = Typecho_Widget::Widget('Widget_Options')->plugin('WeChatHelper')->dropTable;
        if (isset($dropTable) && $dropTable){
            $db = Typecho_Db::get();
            if ("Pdo_Mysql" === $db->getAdapterName() || "Mysql" === $db->getAdapterName()) {
                $db->query("drop table " . $db->getPrefix() . "wch_menus, ".$db->getPrefix()."wch_users");
                $db->query($db->sql()->delete('table.options')->where('name like ?', "WCH_%"));
                $db->query("drop table " . $db->getPrefix() . "wch_template_message ");
            }
        }
        Helper::removeRoute('wechat');
        Helper::removeRoute('send');
		Helper::removeRoute('qrcode');
        Helper::removeAction('WeChat');
        $index = Helper::removeMenu('微信助手');
        Helper::removePanel($index, 'WeChatHelper/Page/Users.php');
        Helper::removePanel($index, 'WeChatHelper/Page/Menus.php');
    }
    /**
	 * 安装数据库
	 */
	public static function installDb(){
		try {
            $db = Typecho_Db::get();
            if ("Pdo_Mysql" === $db->getAdapterName() || "Mysql" === $db->getAdapterName()) {
                //插入WCH_access_token、WCH_expires_in
                $db->query($db->sql()->insert('table.options')->rows(array("name"=>"WCH_access_token","user"=>"0","value"=>"0")));
                $db->query($db->sql()->insert('table.options')->rows(array("name"=>"WCH_expires_in","user"=>"0","value"=>"0")));
                //创建自定义菜单表
                $db->query("CREATE TABLE IF NOT EXISTS " . $db->getPrefix() . 'wch_menus' . " (
                    `mid` int(11) NOT NULL AUTO_INCREMENT,
                    `level` varchar(15) DEFAULT 'button',
                    `name` varchar(32) DEFAULT '',
                    `type` varchar(32) DEFAULT 'view',
                    `value` varchar(200) DEFAULT '',
                    `sort` int(3) DEFAULT '0',
                    `order` int(3) DEFAULT '1',
                    `parent` int(11) DEFAULT '0',
                    `created` int(10) DEFAULT '0',
                    PRIMARY KEY (`mid`)
                  ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1");
                //插入默认菜单数据
                $db->query("INSERT INTO `" . $db->getPrefix() . 'wch_menus' . "` (`mid`, `level`, `name`, `type`, `value`, `sort`, `order`, `parent`, `created`) VALUES
                    (1, 'button', '首页', 'click', 'https://www.iyuu.cn', 10, 1, 0, 1566383054),
                    (2, 'button', '最新文章', 'click', 'n', 20, 2, 0, 1566383095),
                    (3, 'button', '功能', 'click', NULL, 30, 3, 0, 1566383145),
                    (4, 'sub_button', '随机文章', 'click', 'r', 31, 1, 3, 1566383166),
                    (5, 'sub_button', '手气不错', 'click', 'l', 32, 2, 3, 1566383187),
                    (6, 'sub_button', '地理位置', 'location_select', 'location', 33, 3, 3, 1566383279),
                    (7, 'sub_button', '拍照相册', 'pic_photo_or_album', 'photo', 34, 4, 3, 1566383312),
                    (8, 'sub_button', '扫一扫', 'scancode_waitmsg', 'scancode', 35, 5, 3, 1566383356);");

                //创建用户管理表
                $db->query("CREATE TABLE IF NOT EXISTS " . $db->getPrefix() . 'wch_users' . " (
                    `uid` int(11) NOT NULL AUTO_INCREMENT COMMENT '用户uid',
                    `openid` varchar(50) DEFAULT NULL COMMENT '微信用户唯一标识',
                    `nickname` varchar(100) DEFAULT NULL COMMENT '昵称',
                    `sex` tinyint(1) DEFAULT NULL COMMENT '性别1男,2女',
                    `language` varchar(50) DEFAULT NULL COMMENT '用户的语言',
                    `city` varchar(50) DEFAULT NULL COMMENT '城市',
                    `province` varchar(50) DEFAULT NULL COMMENT '省份',
                    `country` varchar(50) DEFAULT NULL COMMENT '国家',
                    `headimgurl` varchar(200) DEFAULT NULL COMMENT '用户头像',
                    `subscribe_time` int(10) DEFAULT '0' COMMENT '关注时间',
                    `credits` int(10) NOT NULL DEFAULT '0' COMMENT '积分',
                    `bind` int(10) DEFAULT NULL COMMENT '是否绑定',
                    `status` tinyint(1) unsigned DEFAULT '1' COMMENT '1已关注,0未关注',
                    `is_send` tinyint(1) unsigned DEFAULT '0' COMMENT '是否禁用:正常0,禁用1',
                    `created` int(10) DEFAULT '0' COMMENT '创建时间',
                    `synctime` int(10) DEFAULT '0' COMMENT '同步时间',
                    `token` varchar(60) DEFAULT NULL COMMENT '发送token：sha1(openid+time+盐)',
                    `sendsum` int(10) NOT NULL DEFAULT '0' COMMENT '累计发送',
                    PRIMARY KEY (`uid`),
                    UNIQUE KEY `openid` (`openid`),
                    KEY `token` (`token`)
                  ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1");
                //创建微信模板消息发送记录表
                $db->query("CREATE TABLE IF NOT EXISTS " . $db->getPrefix() . 'wch_template_message' . " (
                    `mid` int(16) NOT NULL AUTO_INCREMENT COMMENT '自增主键',
                    `uid` int(10) NOT NULL COMMENT '关联wch_users表',
                    `MsgId` int(20) DEFAULT NULL COMMENT '第三方消息id',
                    `message` text NOT NULL COMMENT '消息体',
                    `hash` varchar(50) NOT NULL COMMENT '消息读取sha1(openid+time+盐)',
                    `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '消息投递状态',
                    `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
                    PRIMARY KEY (`mid`),
                    UNIQUE KEY `hash` (`hash`),
                    UNIQUE KEY `MsgId` (`MsgId`),
                    KEY `uid` (`uid`)
                  ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1");
            } else {
                throw new Typecho_Plugin_Exception(_t('对不起, 本插件仅支持MySQL数据库。'));
            }
            return "数据表安装成功！微信助手已经成功激活，请进入设置Token!";
        } catch (Typecho_Db_Exception $e) {
            if ('23000' == $e->getCode()) {
                $msg = '数据表已存在!微信助手已经成功激活，请进入设置Token!';
                return $msg;
            }else{
                return $e->getCode().'微信助手已经成功激活，请进入设置Token!';
            }
        }
	}
    /**
     * 获取插件配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        /** 用户添加订阅欢迎语 **/
        $welcome = new Typecho_Widget_Helper_Form_Element_Textarea('welcome', NULL, '欢迎！' . chr(10) . '发送\'h\'让小的给您介绍一下！', '订阅欢迎语', '用户订阅之后主动发送的一条欢迎语消息。');
        $form->addInput($welcome);
        /** 图文默认图片 **/
        $imageDefault = new Typecho_Widget_Helper_Form_Element_Text('imageDefault', NULL, 'https://iyuu-1251099245.file.myqcloud.com/usr/uploads/2019/08/1175510365.jpg', _t('默认显示图片'), '图片链接，支持JPG、PNG格式，推荐图为80*80。');
        $form->addInput($imageDefault);
        /** 返回最大结果条数 **/
        $imageNum = new Typecho_Widget_Helper_Form_Element_Text('imageNum', NULL, '5', _t('返回图文数量'), '图文消息数量，限制为8条以内。');
        $imageNum->input->setAttribute('class', 'mini');
        $form->addInput($imageNum);
        /** 日志截取字数 **/
        $subMaxNum = new Typecho_Widget_Helper_Form_Element_Text('subMaxNum', NULL, '50', _t('日志截取字数'), '显示单条日志时，截取日志内容字数。');
        $subMaxNum->input->setAttribute('class', 'mini');
        $form->addInput($subMaxNum);

        /** Token **/
        $token = new Typecho_Widget_Helper_Form_Element_Text('token', NULL, '', _t('令牌(Token)'), '需要与开发模式服务器配置中填写一致。服务器地址(URL)：<strong class="warning">' . Helper::options()->index . '/wechat</strong>');
        $form->addInput($token);

        /** APP_ID **/
        $appid = new Typecho_Widget_Helper_Form_Element_Text('WCH_appid', NULL, NULL,
            _t('APP_ID'), _t('需要管理菜单时填写，与开发模式服务器配置中填写一致。'));
        $form->addInput($appid);

        /** APP_Secret **/
        $appsecret = new Typecho_Widget_Helper_Form_Element_Text('WCH_appsecret', NULL, NULL,
            _t('APP_Secret'), _t('需要管理菜单时填写，与开发模式服务器配置中填写一致。'));
        $form->addInput($appsecret);

        //禁用插件是否删除数据
        $dropTable = new Typecho_Widget_Helper_Form_Element_Radio('dropTable',array('1' => _t('开启'), '0' => _t('关闭')),0,
            _t('<span style="color:#B94A48">数据删除</span>'), _t('<span style="color:#B94A48">开启后，禁用插件会删除插件设置数据和数据表。</span>'));
        $form->addInput($dropTable);
    }

    /**
     * 个人用户的配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form) {}
}
