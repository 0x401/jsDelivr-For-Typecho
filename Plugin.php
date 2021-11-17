<?php
/**
 * jsDelivr CDN For Typecho
 *
 * @package JsDelivr
 * @author 0x401
 * @link https://github.com/0x401/jsDelivr-For-Typecho
 * @version 1.1.0
 *
 */

class JsDelivr_Plugin implements Typecho_Plugin_Interface
{
  //上传文件目录
  const UPLOAD_DIR = '/usr/uploads';

  /**
   * 插件激活接口
   */
  public static function activate()
  {
    Typecho_Plugin::factory('Widget_Upload')->uploadHandle = array('JsDelivr_Plugin', 'uploadHandle');
    Typecho_Plugin::factory('Widget_Upload')->modifyHandle = array('JsDelivr_Plugin', 'modifyHandle');
    Typecho_Plugin::factory('Widget_Upload')->deleteHandle = array('JsDelivr_Plugin', 'deleteHandle');
    Typecho_Plugin::factory('Widget_Upload')->attachmentHandle = array('JsDelivr_Plugin', 'attachmentHandle');
    Typecho_Plugin::factory('Widget_Upload')->attachmentDataHandle = array('JsDelivr_Plugin', 'attachmentDataHandle');
    return _t('插件已激活，请前往设置');
  }

  /**
   * 个人用户的配置面板
   */
  public static function personalConfig(Typecho_Widget_Helper_Form $form)
  {
  }

  /**
   * 插件禁用接口
   */
  public static function deactivate()
  {
    return _t('插件已禁用');
  }

  /**
   * 插件配置面板
   */
  public static function config(Typecho_Widget_Helper_Form $form)
  {
    $github_user = new Typecho_Widget_Helper_Form_Element_Text('githubUser', NULL, '', 'Github用户名');
    $github_repo = new Typecho_Widget_Helper_Form_Element_Text('githubRepo', NULL, '', 'Github仓库名');
    $github_branch = new Typecho_Widget_Helper_Form_Element_Text('githubBranch', NULL, 'main', '仓库分支名');
    $github_token = new Typecho_Widget_Helper_Form_Element_Text('githubToken', NULL, '', 'Github账号token');
    $github_directory = new Typecho_Widget_Helper_Form_Element_Text('githubDirectory', NULL, '/te', 'Github仓库内的上传目录', '比如/te，最后一位不需要斜杠');
    $github_version = new Typecho_Widget_Helper_Form_Element_Text('githubVersion', NULL, '', '引用版本', '为空引用仓库文件，如果修改文件，jsDelivr 不会及时更新；latest 引用最新release版本；版本号引用指定版本文件，需要在 github 上手动 releases。');
    $if_save = new Typecho_Widget_Helper_Form_Element_Select('ifSave', array('save' => '保存到本地', 'notsave' => '不保存到本地'), 'save', '是否保存在本地：', '是否将上传的文件保存在本地。');
    $commit_name = new Typecho_Widget_Helper_Form_Element_Text('commitName', NULL, 'JsDelivr', '提交文件者名称', '提交Commit的提交者名称，留空则为仓库所属者。');
    $commit_email = new Typecho_Widget_Helper_Form_Element_Text('commitEmail', NULL, 'JsDelivr@typecho.com', '提交文件者邮箱', '提交Commit的提交者邮箱，留空则为仓库所属者。');

    $form->addInput($github_user->addRule('required', '请输入Github用户名'));
    $form->addInput($github_repo->addRule('required', '请输入Github仓库名'));
    $form->addInput($github_token->addRule('required', '请输入Github账号token'));
    $form->addInput($github_branch->addRule('required', '请输入您的仓库分支名'));
    $form->addInput($github_directory->addRule('required', '请输入Github上传目录'));
    $form->addInput($github_version);
    $form->addInput($if_save);
    $form->addInput($commit_name);
    $form->addInput($commit_email);
  }

  /**
   * 上传文件处理函数
   */
  public static function uploadHandle($file)
  {
    if (empty($file['name'])) {
      return false;
    }
    //获取扩展名
    $ext = self::getSafeName($file['name']);
    //判定是否是允许的文件类型
    if (!Widget_Upload::checkFileType($ext) || Typecho_Common::isAppEngine()) {
      return false;
    }
    //获取设置参数
    $options = Typecho_Widget::widget('Widget_Options')->plugin('JsDelivr');
    //获取文件名
    $date = new Typecho_Date($options->gmtTime);
    $folder = '/' . $date->year . '/' . $date->month;
    $fileName = sprintf('%u', crc32(uniqid())) . '.' . $ext;
    $path = self::UPLOAD_DIR . $folder . '/' . $fileName;// /usr/uploads/2021/07/940034872.png
    //获得上传文件
    $uploadFile = self::getUploadFile($file);
    if (!isset($uploadFile)) {
      return false;
    }
    $fileContent = file_get_contents($uploadFile);
    self::remoteHandler($options, $path, 'upload', $fileContent);
    //上传本地
    $fileDir = self::getUploadDir(false) . $folder;//本地文件夹绝对路径 xxxxx/usr/uploads/2021/07/
    $filename = __TYPECHO_ROOT_DIR__ . $path;
    if ($options->ifSave == 'save') {
      if (!is_dir($fileDir)) {
        if (self::makeUploadDir($fileDir)) {
          file_put_contents($path, $fileContent);
        } else {
          //文件写入失败，写入错误日志
          self::writeLog($path, 'local', 'Directory creation failed');
        }
      } else {
        file_put_contents($filename, $fileContent);
      }
    }
    //返回相对存储路径
    return array(
      'name' => $file['name'],
      'path' => $path,
      'size' => $file['size'],
      'type' => $ext,
      'mime' => @Typecho_Common::mimeContentType($path)
    );
  }

  /**
   * 获取安全的文件名
   */
  private static function getSafeName($name)
  {
    $name = str_replace(array('"', '<', '>'), '', $name);
    $name = str_replace('\\', '/', $name);
    $name = false === strpos($name, '/') ? ('a' . $name) : str_replace('/', '/a', $name);
    $info = pathinfo($name);
    $name = substr($info['basename'], 1);
    return isset($info['extension']) ? strtolower($info['extension']) : '';
  }

  /**
   * 获取上传文件
   */
  private static function getUploadFile($file)
  {
    return isset($file['tmp_name']) ? $file['tmp_name'] : (isset($file['bytes']) ? $file['bytes'] : (isset($file['bits']) ? $file['bits'] : ''));
  }

  private static function remoteHandler($options, $path, $type, $fileContent = NULL, $sha = NULL)
  {
    $remotePath = str_replace(self::UPLOAD_DIR, $options->githubDirectory, $path);
    $data = array(
      'message' => "$type file $path",
      "branch" => $options->githubBranch
    );
    if (!empty($fileContent)) {
      $data['content'] = base64_encode($fileContent);
    }
    if (!empty($sha)) {
      $data['sha'] = $sha;
    }
    if ($options->commitName != null && $options->commitEmail != null) {
      $committer = array(
        "name" => $options->commitName,
        "email" => $options->commitEmail
      );
      $data['committer'] = $committer;
    }
    if ($type == 'delete') {
      $func = 'DELETE';
    } else {
      $func = 'PUT';
    }
    $result = self::curl($remotePath, $func, $options, $data);
    self::writeLog($remotePath, $type, $result);
  }

  private static function curl($remotePath, $func, $options, $data = NULL)
  {
    $header = array(
      "Content-Type:application/vnd.github.v3+json",
      "User-Agent:" . $options->githubRepo,
      "Authorization: token " . $options->githubToken
    );
    $ch = curl_init();
    $url = "https://api.github.com/repos/" . $options->githubUser . "/" . $options->githubRepo . "/contents" . $remotePath;
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $func);//PUT/GET/DELETE
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    if ($func == 'GET') {
      $url .= '?ref=' . $options->githubBranch;
    } else {
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    curl_setopt($ch, CURLOPT_URL, $url);
    $output = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['output' => $output, 'code' => $http_code];
  }

  private static function writeLog($remotePath, $type, $result)
  {
    $date = date('[Y-m-d H:i:s]');
    $text = $date . " " . $remotePath . " [" . $type . "][" . $result['code'] . "]" . PHP_EOL;
    $log_file = dirname(__FILE__) . "/log.log";
    if (!file_exists($log_file)) {
      $file = fopen($log_file, 'w');
    } else {
      $file = fopen($log_file, 'ab+');
    }
    fwrite($file, $text);
  }

  /**
   * 获取文件上传目录
   */
  private static function getUploadDir($relatively = true)
  {
    if ($relatively) {
      if (defined('__TYPECHO_UPLOAD_DIR__')) {
        return __TYPECHO_UPLOAD_DIR__;
      } else {
        return self::UPLOAD_DIR;
      }
    } else {
      return Typecho_Common::url(defined('__TYPECHO_UPLOAD_DIR__') ? __TYPECHO_UPLOAD_DIR__ : self::UPLOAD_DIR,
        defined('__TYPECHO_UPLOAD_ROOT_DIR__') ? __TYPECHO_UPLOAD_ROOT_DIR__ : __TYPECHO_ROOT_DIR__);
    }
  }

  /**
   * 创建上传路径
   */
  private static function makeUploadDir($path)
  {
    $path = preg_replace("/\\\+/", '/', $path);
    $current = rtrim($path, '/');
    $last = $current;
    while (!is_dir($current) && false !== strpos($path, '/')) {
      $last = $current;
      $current = dirname($current);
    }
    if ($last == $current) {
      return true;
    }
    if (!@mkdir($last)) {
      return false;
    }
    $stat = @stat($last);
    $perms = $stat['mode'] & 0007777;
    @chmod($last, $perms);
    return self::makeUploadDir($path);
  }

  /**
   * 修改文件处理函数
   */
  public static function modifyHandle($content, $file)
  {
    if (empty($file['name'])) {
      return false;
    }
    //获取扩展名
    $ext = self::getSafeName($file['name']);
    //判定是否是允许的文件类型
    if ($content['attachment']->type != $ext || Typecho_Common::isAppEngine()) {
      return false;
    }
    //获取设置参数
    $options = Typecho_Widget::widget('Widget_Options')->plugin('JsDelivr');
    //获取文件路径
    $path = $content['attachment']->path; // /usr/uploads/2021/07/940034872.png
    //获得上传文件
    $uploadfile = self::getUploadFile($file);
    //如果没有临时文件，则退出
    if (!isset($uploadfile)) {
      return false;
    }
    $fileContent = file_get_contents($uploadfile);
    //上传到github
    $sha = self::getSHA($path, $options);
    //remoteHandler($path,$type,$fileContent=NULL,$sha=NULL)
    self::remoteHandler($options, $path, 'modify', $fileContent, $sha);
    //更新本地文件
    $filename = __TYPECHO_ROOT_DIR__ . $path;
    if ($options->ifSave == 'save') {
      if (file_exists($filename)) {
        unlink($filename);
      }
      file_put_contents($filename, $fileContent);
    }
    if (!isset($file['size'])) {
      $file['size'] = filesize($path);
    }
    //返回相对存储路径
    return array(
      'name' => $content['attachment']->name,
      'path' => $content['attachment']->path,
      'size' => $file['size'],
      'type' => $content['attachment']->type,
      'mime' => $content['attachment']->mime
    );
  }

  /**
   * 获取sha
   */
  private static function getSHA($path, $options)
  {
    $remotePath = str_replace(self::UPLOAD_DIR, $options->githubDirectory, $path);
    $result = self::curl($remotePath, 'GET', $options);
    self::writeLog($remotePath, 'sha', $result);
    $sha = json_decode($result['output'], true)['sha'];
    return $sha;
  }

  /**
   * 删除文件处理函数
   */
  public static function deleteHandle(array $content)
  {

    $options = Typecho_Widget::widget('Widget_Options')->plugin('JsDelivr');
    $path = $content['attachment']->path;
    $sha = self::getSHA($path, $options);
    self::remoteHandler($options, $path, 'delete', NULL, $sha);
    //删除本地文件
    $filename = __TYPECHO_ROOT_DIR__ . $content['attachment']->path;
    if ($options->ifSave == 'save' && file_exists($filename) == true) {
      unlink($filename);
    }
    return true;
  }

  /**
   * 获取实际文件数据
   */
  public static function attachmentDataHandle($content)
  {
    $cdnPath = self::getCdnPath($content);
    return file_get_contents($cdnPath);
  }

  private static function getCdnPath($content)
  {
    $options = Typecho_Widget::widget('Widget_Options')->plugin('JsDelivr');
    $remotePath = str_replace(self::UPLOAD_DIR, '', $content['attachment']->path);
    if ($options->githubVersion == "") {
      $version = "@" . $options->githubBranch;
    } elseif ($options->githubVersion == "latest") {
      $version = "@latest";
    } else {
      $version = "@" . $options->githubVersion;
    }
    return "https://cdn.jsdelivr.net/gh/" . $options->githubUser . "/" . $options->githubRepo . $version . $options->githubDirectory . $remotePath;
  }

  /**
   * 获取实际文件绝对访问路径
   */
  public static function attachmentHandle($content)
  {
    return self::getCdnPath($content);
  }
}