# WeChatHelper
一款基于Typecho的微信助手插件（修改版），本插件依赖Redis缓存。


## 插件功能 ##

 - 自定义菜单（技术栈：缓存）
 - 关注者列表
 - 模板消息（技术栈：队列、缓存、守护进程）
 - 带参数二维码（技术栈：缓存）
 - 最新博客文章
 - 随机博客文章
 - 搜索博客文章

## 使用方法 ##

 1. 下载插件

​       直接下载解压，或者在/typecho/usr/plugins目录下git clone https://gitee.com/ledc/WeChatHelper.git

 2. 登陆Typecho后台，在“控制台”下拉菜单中进入“插件管理”，启用插件。

 3. 登陆微信公众平台（ https://mp.weixin.qq.com ）后台，在“开发”-》“基本配置”里，修改服务器地址为 http(s)://{域名}/wechat，（例如https://www.iyuu.cn/wechat ） 。 

 4. 在插件设置中填写微信的“令牌(Token)”，与微信公众平台中的“令牌(Token)”保持一致。

 5. 在微信公众平台启用服务器配置。

 6. 在“自定义菜单”下中进入修改微信公众号菜单，并通过“微信接口操作”-》“创建自定义菜单”来发布菜单到公众号。（发布菜单需在插件设置里APP_ID、APP_Secret）


## 效果测试 ##

添加微信号iyuucn查看效果。

![qrcode_for_gh_dd7c6f27ae9a_258](https://www.iyuu.cn/usr/uploads/qrcode/qrcode_iyuucn_258.jpg)

