<?php
namespace TypechoPlugin\CFImgBedUploader;

use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Form\Element\Text;
use Typecho\Widget\Helper\Form\Element\Select;
use Typecho\Widget\Helper\Form\Element\Radio;
use Typecho\Widget\Helper\Layout;
use Typecho\Common;
use Widget\Options;
use Widget\Upload;
use CURLFile;

if (!defined('__TYPECHO_ROOT_DIR__')) exit;
/**
 * CFImgBedUploader - Typecho 插件
 *
 * @package CFImgBedUploader
 * @author  Lord2333
 * @version 1.0.0
 * @link https://github.com/Lord2333/CF-ImgBed-Uploader_for_Typecho
 */
class Plugin implements PluginInterface
{
    const UPLOAD_DIR  = '/usr/uploads';
    const PLUGIN_NAME = 'CFImgBedUploader';

    public static function activate()
    {
        \Typecho\Plugin::factory('Widget_Upload')->uploadHandle     = __CLASS__.'::uploadHandle';
        \Typecho\Plugin::factory('Widget_Upload')->modifyHandle     = __CLASS__.'::modifyHandle';
        \Typecho\Plugin::factory('Widget_Upload')->deleteHandle     = __CLASS__.'::deleteHandle';
        \Typecho\Plugin::factory('Widget_Upload')->attachmentHandle = __CLASS__.'::attachmentHandle';
    }

    public static function deactivate()
    {
    }

    public static function deleteHandle(array $content): bool
    {
        $ext = $content['attachment']->type;

        if (self::_isImage($ext)) {
            return self::_deleteImg($content);
        }

        return unlink($content['attachment']->path);
    }

    public static function config(Form $form)
    {
        // 添加自定义描述
        $description = new Layout();
        $description->html(
            '<div class="description">' .
            '<p>本插件用于将文章中的图片上传到<a href="https://github.com/MarSeventh/CloudFlare-ImgBed" target="_blank">CloudFlare ImgBed</a>图床。</p>' .
            '<p><a href= "https://github.com/Lord2333/CF-ImgBed-Uploader_for_Typecho" target="_blank"><img alt="GitHub last commit" src="https://img.shields.io/github/last-commit/Lord2333/CF-ImgBed-Uploader_for_Typecho?label=%E4%B8%8A%E6%AC%A1%E6%9B%B4%E6%96%B0"></a></p>' .
            '</div>'
        );
        $form->addItem($description);

        $api = new Text(
            'api',
            NULL,
            '',
            'Api地址：',
            '只需填写域名包含 http 或 https 无需<code style="padding: 2px 4px; font-size: 90%; color: #c7254e; background-color: #f9f2f4; border-radius: 4px;"> / </code>结尾<br>' .
            '<code style="padding: 2px 4px; font-size: 90%; color: #c7254e; background-color: #f9f2f4; border-radius: 4px;">示例地址：https://cfbed.sanyue.de</code>'
        );
        $form->addInput($api);

        $authCode = new Text(
            'authCode',
            NULL,
            '',
            '上传认证码（可选）：',
            '如果设置了上传认证码，则请填写您的上传认证码'
        );
        $form->addInput($authCode);
		
		$token = new Text(
            'token',
            NULL,
            '',
            'API Token（可选）：',
            '如果您的图床需要API Token进行删除操作，请在此填写'
        );
        $form->addInput($token);

        $uploadChannel = new Select(
            'uploadChannel',
            [
                'telegram' => 'telegram',
                'cfr2' => 'cfr2',
                's3' => 's3'
            ],
            'telegram',
            '上传渠道：',
            '选择上传渠道，默认为telegram'
        );
        $form->addInput($uploadChannel);

        $uploadFolder = new Text(
            'uploadFolder',
            NULL,
            '',
            '上传目录：',
            '用相对路径表示，例如上传到/Blog/test目录需填/Blog/test，留空则上传到根目录'
        );
        $form->addInput($uploadFolder);
        
        $logLevel = new Select(
            'logLevel',
            [
                'error' => '仅错误',
                'info' => '信息和错误',
                'debug' => '调试（详细）'
            ],
            'error',
            '日志级别：',
            '选择日志记录的详细程度'
        );
        $form->addInput($logLevel);
        
        $logRetention = new Select(
            'logRetention',
            [
                '1' => '1天',
                '7' => '7天',
                '15' => '15天',
                '30' => '30天'
            ],
            '7',
            '日志保存时间：',
            '选择日志文件保存的天数，超过该天数的日志将被自动清理'
        );
        $form->addInput($logRetention);
        
        $serverCompress = new Radio(
            'serverCompress',
            [
                '1' => '开启',
                '0' => '关闭'
            ],
            '0',
            '服务端压缩：',
            '开启后将在服务端对图片进行压缩处理，可能会影响图片质量'
        );
        $form->addInput($serverCompress);
        
        // 添加上传路径格式设置
        $pathFormat = new Text(
            'pathFormat',
            NULL,
            '',
            '上传路径格式（选填）：',
            '支持的魔法参数：{Y}年, {m}月, {d}日, {H}时, {i}分, {s}秒, {timestamp}时间戳。例如：{Y}/{m}/{d}。默认为上传到上传目录根目录下。'
        );
        $form->addInput($pathFormat);
        
        // 添加文件名格式设置
        $filenameFormat = new Select(
            'filenameFormat',
            [
                'original' => '保持原文件名',
                'timestamp' => '时间戳',
                'datetime' => '年月日时分秒',
                'random' => '随机字符串',
                // 暂时禁用自定义格式选项
                // 'custom' => '自定义格式'
            ],
            'original',
            '文件名格式：',
            '选择上传后的文件名格式'
        );
        $form->addInput($filenameFormat);
        
        // 暂时注释掉自定义文件名格式设置
        // 添加自定义文件名格式设置
        // $customFilenameFormat = new Text(
        //     'customFilenameFormat',
        //     NULL,
        //     '{filename}_{timestamp}',
        //     '自定义文件名格式：',
        //     '仅当文件名格式选择"自定义格式"时生效。支持的魔法参数：<br>
        //     {filename} - 原文件名（不含扩展名）<br>
        //     {ext} - 文件扩展名<br>
        //     {Y} - 年<br>
        //     {m} - 月<br>
        //     {d} - 日<br>
        //     {H} - 时<br>
        //     {i} - 分<br>
        //     {s} - 秒<br>
        //     {timestamp} - 时间戳<br>
        //     {random} - 随机字符串'
        // );
        // $form->addInput($customFilenameFormat);
    }
    
    public static function personalConfig(Form $form)
    {
    }

    public static function uploadHandle($file)
    {
        if (empty($file['name'])) {
            return false;
        }

        $ext = self::_getSafeName($file['name']);

        if (!Upload::checkFileType($ext) || Common::isAppEngine()) {
            return false;
        }

        if (self::_isImage($ext)) {
            return self::_uploadImg($file, $ext);
        }

        return self::_uploadOtherFile($file, $ext);
    }

    public static function modifyHandle($content, $file)
    {
        if (empty($file['name'])) {
            return false;
        }
        $ext = self::_getSafeName($file['name']);
        if ($content['attachment']->type != $ext || Common::isAppEngine()) {
            return false;
        }

        if (!self::_getUploadFile($file)) {
            return false;
        }

        if (self::_isImage($ext)) {
            self::_deleteImg($content);
            return self::_uploadImg($file, $ext);
        }

        return self::_uploadOtherFile($file, $ext);
    }

    public static function attachmentHandle(array $content): string
    {
        return $content['attachment']->path ?? '';
    }

    private static function _getUploadDir($ext = ''): string
    {
        if (self::_isImage($ext)) {
            $url = parse_url(Options::alloc()->siteUrl);
            $DIR = str_replace('.', '_', $url['host']);
            return '/' . $DIR . self::UPLOAD_DIR;
        } elseif (defined('__TYPECHO_UPLOAD_DIR__')) {
            return __TYPECHO_UPLOAD_DIR__;
        } else {
            $path = Common::url(self::UPLOAD_DIR, __TYPECHO_ROOT_DIR__);
            return $path;
        }
    }

    private static function _getUploadFile($file): string
    {
        return $file['tmp_name'] ?? ($file['bytes'] ?? ($file['bits'] ?? ''));
    }

    private static function _getSafeName(&$name): string
    {
        $name = str_replace(array('"', '<', '>'), '', $name);
        $name = str_replace('\\', '/', $name);
        $name = false === strpos($name, '/') ? ('a' . $name) : str_replace('/', '/a', $name);
        $info = pathinfo($name);
        $name = substr($info['basename'], 1);

        return isset($info['extension']) ? strtolower($info['extension']) : '';
    }

    private static function _makeUploadDir($path): bool
    {
        $path    = preg_replace("/\\\\+/", '/', $path);
        $current = rtrim($path, '/');
        $last    = $current;

        while (!is_dir($current) && false !== strpos($path, '/')) {
            $last    = $current;
            $current = dirname($current);
        }

        if ($last == $current) {
            return true;
        }

        if (!@mkdir($last)) {
            return false;
        }

        $stat  = @stat($last);
        $perms = $stat['mode'] & 0007777;
        @chmod($last, $perms);

        return self::_makeUploadDir($path);
    }

    private static function _isImage($ext): bool
    {
        $img_ext_arr = array('gif', 'jpg', 'jpeg', 'png', 'tiff', 'bmp', 'ico', 'psd', 'webp', 'JPG', 'BMP', 'GIF', 'PNG', 'JPEG', 'ICO', 'PSD', 'TIFF', 'WEBP');
        return in_array($ext, $img_ext_arr);
    }

    private static function _uploadOtherFile($file, $ext)
    {
        $options = Options::alloc()->plugin(self::PLUGIN_NAME);
        $pathFormat = $options->pathFormat ?? '{Y}/{m}/{d}';
        $filenameFormat = $options->filenameFormat ?? 'original';
        
        // 处理上传路径格式
        $parsedPath = self::_parseMagicParams($pathFormat);
        $dir = self::_getUploadDir($ext) . '/' . $parsedPath;
        
        if (!self::_makeUploadDir($dir)) {
            return false;
        }
        
        // 生成文件名
        $filename = self::_generateFilename($file['name'], $filenameFormat, $ext);
        
        // 记录上传信息
        $formatInfo = $filenameFormat;
        // 暂时注释掉自定义格式的特殊处理
        
        // if ($filenameFormat === 'custom') {
        //     $customFormat = $options->customFilenameFormat ?? '{filename}_{timestamp}';
        //     $formatInfo = "自定义({$customFormat})";
        // }
        
        self::_log('info', "非图片文件上传: 路径格式={$parsedPath}, 文件名格式={$formatInfo}, 最终文件名={$filename}");
        
        $path = $dir . '/' . $filename;
        if (!isset($file['tmp_name']) || !@move_uploaded_file($file['tmp_name'], $path)) {
            return false;
        }

        return [
            'name' => $file['name'],
            'path' => $path,
            'size' => $file['size'] ?? filesize($path),
            'type' => $ext,
            'mime' => @Common::mimeContentType($path)
        ];
    }

    /**
     * 处理魔法参数，将路径格式中的魔法参数替换为实际值
     * 
     * @param string $format 包含魔法参数的格式字符串
     * @return string 替换后的字符串
     */
    private static function _parseMagicParams($format)
    {
        $replacements = [
            '{Y}' => date('Y'),  // 年
            '{m}' => date('m'),  // 月
            '{d}' => date('d'),  // 日
            '{H}' => date('H'),  // 时
            '{i}' => date('i'),  // 分
            '{s}' => date('s'),  // 秒
            '{timestamp}' => time() // 时间戳
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $format);
    }
    
    /**
     * 解析自定义文件名格式，替换其中的魔法参数
     * 
     * @param string $format 自定义文件名格式
     * @param string $originalName 原始文件名
     * @param string $ext 文件扩展名
     * @return string 处理后的文件名
     */
    
    private static function _parseFilenameFormat($format, $originalName, $ext)
    {
        // 获取原文件名（不含扩展名）
        $filename = pathinfo($originalName, PATHINFO_FILENAME);
        
        // 定义替换规则
        $replacements = [
            '{filename}' => $filename,  // 原文件名（不含扩展名）
            '{ext}' => $ext,           // 文件扩展名
            '{Y}' => date('Y'),        // 年
            '{m}' => date('m'),        // 月
            '{d}' => date('d'),        // 日
            '{H}' => date('H'),        // 时
            '{i}' => date('i'),        // 分
            '{s}' => date('s'),        // 秒
            '{timestamp}' => time(),    // 时间戳
            '{random}' => substr(md5(uniqid(mt_rand(), true)), 0, 8) // 随机字符串
        ];
        
        // 替换魔法参数
        $result = str_replace(array_keys($replacements), array_values($replacements), $format);
        
        // 移除不允许的字符，确保文件名安全
        $result = preg_replace('/[\\\/:\*?"<>|]/', '_', $result);
        
        // 如果结果中不包含扩展名，则添加扩展名
        if (strpos($result, '.' . $ext) === false) {
            $result .= '.' . $ext;
        }
        
        return $result;
    }
    

    
    /**
     * 根据设置生成文件名
     * 
     * @param string $originalName 原始文件名
     * @param string $format 文件名格式
     * @param string $ext 文件扩展名
     * @return string 生成的文件名
     */
    private static function _generateFilename($originalName, $format, $ext)
    {
        $options = Options::alloc()->plugin(self::PLUGIN_NAME);
        
        switch ($format) {
            case 'original':
                // 移除扩展名，保留原文件名
                $filename = pathinfo($originalName, PATHINFO_FILENAME);
                break;
            case 'timestamp':
                $filename = time();
                break;
            case 'datetime':
                $filename = date('YmdHis');
                break;
            case 'random':
                $filename = substr(md5(uniqid(mt_rand(), true)), 0, 12);
                break;
            /*
            case 'custom':
                // 使用自定义格式
                $customFormat = $options->customFilenameFormat ?? '{filename}_{timestamp}';
                $filename = self::_parseFilenameFormat($customFormat, $originalName, $ext);
                // 自定义格式已经包含扩展名，直接返回
                return $filename;
            */
            default:
                $filename = pathinfo($originalName, PATHINFO_FILENAME);
        }
        
        return $filename . '.' . $ext;
    }
    
    private static function _uploadImg($file, $ext)
    {
        try {
            $options = Options::alloc()->plugin(self::PLUGIN_NAME);
            $api = $options->api . '/upload';
            $authCode = $options->authCode;
            $uploadChannel = $options->uploadChannel;
            $uploadFolder = $options->uploadFolder;
            $serverCompress = (bool)($options->serverCompress ?? false);
            $pathFormat = $options->pathFormat ?? '{Y}/{m}/{d}';
            $filenameFormat = $options->filenameFormat ?? 'original';

            $tmp = self::_getUploadFile($file);
            if (empty($tmp)) {
                throw new \Exception('无法获取上传文件');
            }

            // 处理上传路径格式
            $parsedPath = self::_parseMagicParams($pathFormat);
            
            // 生成文件名
            $newFilename = self::_generateFilename($file['name'], $filenameFormat, $ext);
            
            // 使用唯一的临时文件名
            $tempDir = sys_get_temp_dir();
            // $tempName = uniqid('up_', true) . '_' . $file['name']; 暂不考虑重复文件名
            $tempPath = $tempDir . '/' . $file['name'];

            if (!rename($tmp, $tempPath)) {
                throw new \Exception('临时文件处理失败');
            }

            if (!is_readable($tempPath)) {
                throw new \Exception('临时文件不可读');
            }

            // 构建请求URL
            $requestUrl = $api . '?authCode=' . urlencode($authCode);
            if ($uploadChannel) {
                $requestUrl .= '&uploadChannel=' . urlencode($uploadChannel);
            }
            
            // 处理上传文件夹路径
            $finalUploadFolder = $uploadFolder;
            if (!empty($finalUploadFolder) && !empty($parsedPath)) {
                // 确保路径以/开头且不以/结尾
                $finalUploadFolder = rtrim($finalUploadFolder, '/') . '/' . ltrim($parsedPath, '/');
            } elseif (empty($finalUploadFolder) && !empty($parsedPath)) {
                $finalUploadFolder = $parsedPath;
            }
            
            if (!empty($finalUploadFolder)) {
                $requestUrl .= '&uploadFolder=' . urlencode($finalUploadFolder);
            }
            
            // 添加文件名参数
            // 无论什么格式，都传递处理后的文件名，确保文件名正确应用
            $requestUrl .= '&filename=' . urlencode($newFilename);
            
            // 添加其他参数
            $requestUrl .= '&autoRetry=true&returnFormat=full';
            
            // 根据设置决定是否启用服务端压缩
            if ($serverCompress) {
                $requestUrl .= '&serverCompress=true';
            }

            $params = ['file' => new CURLFile($tempPath)];

            $res = self::_curlPost($requestUrl, $params);

            // 确保日志目录存在
            $logFile = __DIR__ . '/logs/upload.log';
            if (!is_dir(dirname($logFile))) {
                mkdir(dirname($logFile), 0755, true);
            }

            // 记录上传信息和API响应
            $formatInfo = $filenameFormat;
            // 暂时注释掉自定义格式的特殊处理
            /*
            if ($filenameFormat === 'custom') {
                $customFormat = $options->customFilenameFormat ?? '{filename}_{timestamp}';
                $formatInfo = "自定义({$customFormat})";
            }
            */
            self::_log('info', "上传信息: 路径格式={$parsedPath}, 文件名格式={$formatInfo}, 最终文件名={$newFilename}");
            self::_log('info', "上传响应: " . $res);

            if (!$res) {
                throw new \Exception('API请求返回为空');
            }

            $json = json_decode($res, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('JSON解析错误: ' . json_last_error_msg());
            }

            if (empty($json[0]['src'])) {
                throw new \Exception('上传失败: ' . ($json['error'] ?? '未知错误'));
            }

            $imageUrl = $json[0]['src'];
            // 如果返回的不是完整URL，则添加域名
            if (strpos($imageUrl, 'http') !== 0) {
                $imageUrl = rtrim($options->api, '/') . $imageUrl;
            }

            return [
                'img_key' => basename($imageUrl), // 使用文件名作为key
                'img_id' => md5($file['name'] . time()), // 生成唯一ID
                'name'   => $file['name'],
                'path'   => $imageUrl,
                'size'   => $file['size'] ?? filesize($tempPath),
                'type'   => $ext,
                'mime'   => $file['type'] ?? 'image/' . $ext,
                'description' => 'Uploaded to CloudFlare ImgBed',
            ];
        } catch (\Exception $e) {
            // 记录详细错误信息
            self::_log('error', "上传错误: " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        } finally {
            // 清理临时文件
            if (isset($tempPath) && file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    private static function _deleteImg(array $content): bool
    {
        try {
            $options = Options::alloc()->plugin(self::PLUGIN_NAME);
            $api = $options->api;
            $authCode = $options->authCode;
            
            // 从路径中提取文件路径
            $path = $content['attachment']->path;
            $filePath = '';
            
            // 如果是完整URL，提取路径部分
            if (strpos($path, $api) === 0) {
                $filePath = str_replace($api . '/file/', '', $path);
            } else {
                // 否则直接使用路径
                $filePath = $path;
            }
            
            // 确保路径格式正确
            $filePath = ltrim($filePath, '/');
            
            // 构建删除API URL
            $deleteUrl = $api . '/api/manage/delete/' . $filePath;
            
            // 获取API Token（如果有）
            $token = $options->token ?? '';
            
            $res = self::_curlDelete($deleteUrl, $token);
            
            // 记录删除操作
             self::_log('info', "删除响应: " . $res);
            
            $json = json_decode($res, true);
            
            // 检查删除是否成功
            if (isset($json['success']) && $json['success'] === true) {
                return true;
            }
            
            // 记录删除失败的原因
             $errorMsg = isset($json['error']) ? $json['error'] : '未知错误';
             self::_log('error', "删除失败: " . $errorMsg);
            
            return false;
        } catch (\Exception $e) {
            // 记录异常
             self::_log('error', "删除错误: " . $e->getMessage());
            return false;
        }
    }
    
    private static function _curlDelete($api, $token = '')
    {
        $headers = array(
            "Accept: application/json",
            "User-Agent: Typecho-CFImgBedUploader/1.0"
        );
        
        // 如果有token，添加到请求头
        if (!empty($token)) {
            $headers[] = "Authorization: Bearer " . $token;
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/85.0.4183.102 Safari/537.36');
        
        $res = curl_exec($ch);
        
        // 记录curl错误信息
         if (!$res) {
             $error = curl_error($ch);
             $errno = curl_errno($ch);
             self::_log('error', "删除CURL错误: " . $error . " (" . $errno . ")");
         }
         
         // 记录HTTP状态码
         $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
         self::_log('debug', "删除HTTP状态码: " . $httpCode);
        
        curl_close($ch);
        return $res;
    }

    /**
     * 记录日志
     * 
     * @param string $level 日志级别：error, info, debug
     * @param string $message 日志消息
     * @param array $context 上下文信息
     * @return void
     */
    private static function _log(string $level, string $message, array $context = []): void
    {
        try {
            $options = Options::alloc()->plugin(self::PLUGIN_NAME);
            $logLevel = $options->logLevel ?? 'error';
            
            // 根据配置的日志级别决定是否记录
            if ($level === 'error' || 
                ($level === 'info' && in_array($logLevel, ['info', 'debug'])) ||
                ($level === 'debug' && $logLevel === 'debug')) {
                
                // 确保日志目录存在
                $logDir = __DIR__ . '/logs';
                if (!is_dir($logDir)) {
                    mkdir($logDir, 0755, true);
                }
                
                $logFile = $logDir . '/upload.log';
                
                // 格式化日志消息
                $logEntry = date('Y-m-d H:i:s') . " [{$level}] " . $message;
                
                // 添加上下文信息
                if (!empty($context)) {
                    foreach ($context as $key => $value) {
                        $logEntry .= "\n{$key}: {$value}";
                    }
                }
                
                $logEntry .= PHP_EOL;
                
                // 写入日志文件
                file_put_contents($logFile, $logEntry, FILE_APPEND);
                
                // 清理过期日志
                self::_cleanupLogs();
            }
        } catch (\Exception $e) {
            // 如果日志记录本身出错，尝试直接写入错误
            $fallbackLog = __DIR__ . '/logs/error.log';
            $errorMsg = date('Y-m-d H:i:s') . " 日志系统错误: " . $e->getMessage() . PHP_EOL;
            @file_put_contents($fallbackLog, $errorMsg, FILE_APPEND);
        }
    }
    
    /**
     * 清理过期日志
     * 
     * @return void
     */
    private static function _cleanupLogs(): void
    {
        try {
            // 每天只执行一次清理操作
            $cleanupFlagFile = __DIR__ . '/logs/.cleanup_' . date('Y-m-d');
            if (file_exists($cleanupFlagFile)) {
                return;
            }
            
            // 创建标记文件
            touch($cleanupFlagFile);
            
            $options = Options::alloc()->plugin(self::PLUGIN_NAME);
            $logRetention = (int)($options->logRetention ?? 7);
            
            // 如果保留天数小于1，默认为7天
            if ($logRetention < 1) {
                $logRetention = 7;
            }
            
            $logDir = __DIR__ . '/logs';
            $cutoffTime = time() - ($logRetention * 86400); // 86400 = 24小时 * 60分钟 * 60秒
            
            // 获取所有日志文件
            $logFiles = glob($logDir . '/*.log');
            
            foreach ($logFiles as $file) {
                // 跳过当前正在使用的日志文件
                if (basename($file) === 'upload.log') {
                    // 如果文件过大（超过10MB），则进行轮转
                    if (filesize($file) > 10 * 1024 * 1024) {
                        $backupFile = $logDir . '/upload_' . date('Y-m-d_H-i-s') . '.log';
                        rename($file, $backupFile);
                    }
                    continue;
                }
                
                // 检查文件修改时间
                if (filemtime($file) < $cutoffTime) {
                    @unlink($file);
                }
            }
            
            // 清理过期的标记文件
            $flagFiles = glob($logDir . '/.cleanup_*');
            foreach ($flagFiles as $flag) {
                if (filemtime($flag) < $cutoffTime) {
                    @unlink($flag);
                }
            }
        } catch (\Exception $e) {
            // 清理日志出错，记录错误但不中断程序
            $fallbackLog = __DIR__ . '/logs/error.log';
            $errorMsg = date('Y-m-d H:i:s') . " 日志清理错误: " . $e->getMessage() . PHP_EOL;
            @file_put_contents($fallbackLog, $errorMsg, FILE_APPEND);
        }
    }
    
    private static function _curlPost($api, $post, $retries = 3)
    {
        $headers = array(
            "Content-Type: multipart/form-data",
            "Accept: application/json",
            "User-Agent: Typecho-CFImgBedUploader/1.0"
        );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/85.0.4183.102 Safari/537.36');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TCP_KEEPALIVE, 1);
        curl_setopt($ch, CURLOPT_TCP_KEEPIDLE, 120);

        $attempt = 0;
        do {
            $res = curl_exec($ch);
            $attempt++;

            if (!$res && $attempt < $retries) {
                sleep(1); // 等待1秒后重试
                continue;
            }
            break;
        } while (true);

        // 记录curl错误信息
        if (!$res) {
            $error = curl_error($ch);
            $errno = curl_errno($ch);
            self::_log('error', "CURL错误: " . $error . " (" . $errno . ")");
        }

        // 记录HTTP状态码
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        self::_log('debug', "HTTP状态码: " . $httpCode);

        curl_close($ch);
        return $res;
    }
}