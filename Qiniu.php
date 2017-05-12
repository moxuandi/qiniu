<?php
namespace moxuandi\kindeditor;

use yii\helpers\Json;

/**
 * Class Qiniu 上传文件到七牛云
 *
 * @author zhangmoxuan <1104984259@qq.com>
 * @link http://www.zhangmoxuan.cn
 * @QQ 1104984259
 * @date 2017-4-1
 * eg:
 * =====
 * $ak = 'key';
 * $sk = 'key';
 * $bucket = 'bucket';
 * $upHost = 'http://upload-z1.qiniu.com';
 * $domain = 'http://optlcu7ad.bkt.clouddn.com';
 * $qiniu = new Qiniu($ak, $sk, $bucket, $upHost, $domain);
 * $key = time();
 * $qiniu->uploadFile($this->file->tempName, $key);
 * $url = $qiniu->getLink($key);
 * =====
 */
class Qiniu
{
    protected $accessKey;  //秘钥: https://portal.qiniu.com/user/key
    protected $secretKey;  //秘钥: https://portal.qiniu.com/user/key
    protected $bucket;     //存储空间名称: https://portal.qiniu.com/bucket
    protected $upHost;     //上传文件的url(根据服务器所在的位置选择): https://developer.qiniu.com/kodo/manual/1671/region-endpoint
    protected $domain;     //外链域名, 上传成功后文件访问的域名, eg:http://optlcu7ad.bkt.clouddn.com
    public $status;        //错误状态码
    public $statusInfo;   //错误信息

    function __construct($accessKey, $secretKey, $bucket, $upHost, $domain)
    {
        $this->accessKey = $accessKey;
        $this->secretKey = $secretKey;
        $this->bucket = $bucket;
        $this->upHost = $upHost;
        $this->domain = $domain;
    }

    /**
     * 上传文件
     * @param $filePath
     * @param string $saveName
     * @return bool|mixed
     */
    public function uploadFile($filePath, $saveName='')
    {
        if(!file_exists($filePath)){
            $this->status = 400;
            $this->statusInfo = '上传文件不存在';
            return false;
        }

        $data = [
            'file' => class_exists('\CURLFile') ? new \CURLFile($filePath) : '@' . $filePath,
            'token' => self::uploadToken(['scope'=>$this->bucket]),
            'key' => $saveName
        ];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->upHost,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data
        ]);
        $result = Json::decode(curl_exec($curl), true);
        $this->status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if($this->status == 200){
            return $result;
        }else{
            $this->statusInfo = $result['error'];
            return false;
        }
    }

    /**
     * 文件上传成功后, 获取下载链接
     * @param string $saveName
     * @return string
     */
    public function getLink($saveName='')
    {
        return rtrim($this->domain,'/') . '/' . $saveName;
    }

    /**
     * 生成上传 Token
     * @param $flags
     * @return string
     */
    public function uploadToken($flags)
    {
        if(!isset($flags['deadline'])){
            $flags['deadline'] = time() + 3600;
        }
        $encodedFlags = self::urlBase64Encode(Json::encode($flags));
        $sign = hash_hmac('sha1', $encodedFlags, $this->secretKey, true);
        $encodedSign = self::urlBase64Encode($sign);
        return $this->accessKey . ':' . $encodedSign . ':' . $encodedFlags;
    }

    /**
     * 可以传出的 base6 4编码
     * @param $str
     * @return mixed
     */
    public function urlBase64Encode($str)
    {
        $search = ['+', '/'];
        $replace = ['-', '_'];
        return str_replace($search, $replace, base64_encode($str));
    }

}