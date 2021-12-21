# jsDelivr CDN For Typecho
>2021-12-21 [jsDelivr 的备案已被注销](https://twitter.com/jsDelivr/status/1472870623051456522)，现在没有大陆节点了
>根据 [UploadGithubForTypecho 1.1.1](https://github.com/AyagawaSeirin/UploadGithubForTypecho/) 修改而来

本插件用于将文章附件上传至Github，在插入附件的时候使用 jsDelivr 的 CDN 地址，达到文件访问加速。

通过设置，可以将文件上传到指定仓库、指定分支、指定文件夹下。

例如下图设置，文件同时上传到 Typecho 网站默认附件目录（.../usr/uploads）和 Github 目录（ https://github.com/0x401/0x401/tree/dev/te ）

![image](https://user-images.githubusercontent.com/22230112/140312521-f17c2ecd-208c-45c2-b555-988daa827678.png)

当点击插入附件时，URL 会自动替换成 jsDelivr 的地址。

![image](https://user-images.githubusercontent.com/22230112/140314837-a88eafae-92b4-432a-8861-fe38291cb318.png)

当更新、删除附件时，GitHub 上的文件也会被同时更新、删除。

当插件开启时，后台附件管理页的文件地址都会被替换为 jsDelivr 的地址，要正常显示需要将历史文件上传至 Github。

当插件禁用时，重新插入附件仍然能正确引用网站服务器上的图片。

