# CFImgBedUploader for Typecho

![GitHub last commit](https://img.shields.io/github/last-commit/Lord2333/CF-ImgBed-Uploader_for_Typecho?label=%E4%B8%8A%E6%AC%A1%E6%9B%B4%E6%96%B0&link=https%3A%2F%2Fgithub.com%2FLord2333%2FCF-ImgBed-Uploader_for_Typecho)

## 插件介绍

CFImgBedUploader 是一个 Typecho 插件，可自动将附件中的图片上传到 [CloudFlare ImgBed](https://github.com/MarSeventh/CloudFlare-ImgBed) 图床。插件可配置多种上传渠道、自定义上传路径和文件名格式，提供完善的日志记录功能。

## 功能特点

- 支持多种上传渠道（telegram、cfr2、s3）
- 自定义上传目录和路径格式
- 多种文件名格式选项（原文件名、时间戳、日期时间、随机字符串）
- 完善的日志记录系统，支持多级日志
- 支持图片删除功能

## 安装方法

1. 下载本插件，解压后将文件夹重命名为 `CFImgBedUploader`
2. 将插件上传到 Typecho 的 `/usr/plugins/` 目录下
3. 登录 Typecho 后台，进入「控制台」->「插件」
4. 找到 CFImgBedUploader，点击「启用」
5. 进入插件设置页面，配置相关参数

## 配置说明

![settings.png](https://cao.n1ma.de/file/Blog/Static/1755615442712_image.png)

### 基本配置

- **Api地址**：CloudFlare ImgBed 的 API 地址，格式为 `https://example.com`（不需要以 `/` 结尾）
- **上传认证码**：如果图床设置了上传认证码，请填写（可选）
- **API Token**：用于删除操作的 API Token（可选）
- **上传渠道**：选择上传渠道，可选 `telegram`、`cfr2` 或 `s3`
- **上传目录**：指定上传到图床的目录路径，留空则上传到根目录

### 高级配置

- **日志级别**：选择日志记录的详细程度
  - 仅错误：只记录错误信息
  - 信息和错误：记录一般信息和错误信息
  - 调试（详细）：记录所有详细信息，包括调试信息
- **日志保存时间**：设置日志文件保存的天数（1天、7天、15天或30天）
- **允许上传的文件类型**：允许上传的文件类型，默认支持常见图片格式(gif,jpg,jpeg,png,webp)
- **服务端压缩**：是否在服务端对图片进行压缩处理

### 路径和文件名配置

- **上传路径格式**：自定义上传路径的格式，支持以下魔法参数：
  - `{Y}` - 年
  - `{m}` - 月
  - `{d}` - 日
  - `{H}` - 时
  - `{i}` - 分
  - `{s}` - 秒
  - `{timestamp}` - 时间戳
  
  默认为空，即上传到上传目录根目录下

- **文件名格式**：选择上传后的文件名格式
  - 保持原文件名：使用原始文件名
  - 时间戳：使用时间戳作为文件名
  - 年月日时分秒：使用 `YmdHis` 格式的日期时间作为文件名
  - 随机字符串：使用随机字符串作为文件名

## 使用方法

配置完成后，插件会自动接管 Typecho 的附件上传功能。当您在编辑文章时上传图片，图片将会被上传到您配置的 CloudFlare ImgBed 图床，而不是保存在本地服务器。

### 上传图片

1. 在编辑文章时，点击编辑器右边的“附件”按钮
2. 选择要上传的图片文件，拖拽到附件栏中；或者复制图片，在编辑栏中需要插入的地方粘贴图片
3. 图片将自动上传到 CloudFlare ImgBed 图床
4. 上传成功后，图片链接会自动插入到编辑器中

![PixPin_2025-08-19.gif](https://cao.n1ma.de/file/Blog/Static/1755615826688_PixPin_2025-08-19.gif)

### 查看日志

插件会在 `plugins/CFImgBedUploader/logs/` 目录下生成日志文件，您可以通过查看日志文件来了解上传过程中的详细信息或排查问题。

## 常见问题

1. **上传失败**
   - 检查 API 地址是否正确（末尾不要带上`/`，也不要带上`/upload`！！！）
   - 确认上传认证码是否正确（如果启用）
   - 查看日志文件获取详细错误信息

2. **图片无法显示**
   - 确认图床服务是否正常运行
   - 检查上传目录权限是否正确

3. **删除图片失败**
   - 确认 API Token 是否正确设置
   - 查看日志文件获取详细错误信息

## 更新日志

### v1.0.0
- 初始版本发布
- 支持基本的图片上传功能
- 支持多种上传渠道和自定义路径

### v1.0.1
- 支持粘贴上传图片
- 用户自定义允许上传的文件类型

## 许可证

本插件采用 MIT 许可证。详见 [LICENSE](LICENSE) 文件。

## 鸣谢

- [CloudFlare ImgBed](https://github.com/MarSeventh/CloudFlare-ImgBed) - 提供图床服务
- [Typecho](https://typecho.org/) - 优秀的博客平台
