<?php
ini_set('display_errors',1);  
ini_set('display_startup_errors',1);    //php启动错误信息  
error_reporting(-1);  

//切换工作路径
$path = dirname(__FILE__);    
chdir($path);


//引入阿里云SDK
include_once './aliyun-sdk/aliyun-php-sdk-core/Config.php';
//定义 某个SDK命名空间
use Alidns\Request\V20150109 as Alidns;

// 定义保存解析的文件路径 以及域名 以及阿里云AccessKEY信息
$my_config['config']='./config.json';
$my_config['errlog']='./AliyunDnsErrLog.log';
$my_config['AccessKeyID']='';
$my_config['AccessKeySecret']='';
$my_config['ym']='xxx.xxx.com';


// 定义获取本机公网IP的URL列表以及匹配IP的正则
$getIpList=array(
	array('http://2018.ip138.com/ic.asp','(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})'),
	array('https://www.ip.cn','(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})'),
);


$localip = false;
//获取IP 如果获取失败 重试五次
for($i=0 ; $i != 5 ;$i++){
	foreach($getIpList as $getIp){
		$text=curl_get($getIp[0]);
		if(preg_match ($getIp[1], $text, $regs)){
			$localip=$regs[0];
			break;
		}
	}
	if($localip !== false)
		break;
}
if($localip == false){
	file_put_contents($my_config['errlog'],date('Y-m-d H:i:s')." Get Ip err\r\n",FILE_APPEND );
	exit;
}



//从本地获取数据
$info = @json_decode(file_get_contents($my_config['config']),true);

//如果本地数据不存在 则从服务器获取信息
if(!isset($info['RecordId']) || !isset($info['RR'])){
	$data=false;
	$a=new aliyun($my_config);
	for($i=0 ; $i != 5 ;$i++){
		$data=$a->getJL($my_config['ym']);
		if($data !== false){
			break;
		}
	}
	unset($a);
	if($data == false){
		file_put_contents($my_config['errlog'],date('Y-m-d H:i:s')." Get IpInof err\r\n",FILE_APPEND);
		exit;
	}

	$info['RecordId']=$data->RecordId;
	$info['RR']=$data->RR;
	$info['Type']=$data->Type;
	$info['DomainName']=$data->DomainName;
	$info['TTL']=$data->TTL;
	$info['Value']=$data->Value;
	
	//保存数据到本地
	file_put_contents($my_config['config'],json_encode($info));
}

if($info['Value'] != $localip){
	$info['Value'] = $localip;
	$data=false;
	$a=new aliyun($my_config);
	for($i=0 ; $i != 5 ;$i++){
		$data=$a->editJL($info);
		if($data)
			break;
	}
	unset($a);
	if($data){
		file_put_contents($my_config['config'],json_encode($info));
		file_put_contents($my_config['errlog'],date('Y-m-d H:i:s').' Edit IpInof ok New IP:'.$info['Value']."\r\n",FILE_APPEND);
		exit;
	}else{
		file_put_contents($my_config['errlog'],date('Y-m-d H:i:s')." Edit IpInof err\r\n",FILE_APPEND);
		exit;
	}
}

class aliyun {
	public $client;
	function __construct($my_config){
		//实例化证书
		$iClientProfile = DefaultProfile::getProfile("cn-hangzhou",$my_config['AccessKeyID'], $my_config['AccessKeySecret']);
		$this->client = new DefaultAcsClient($iClientProfile);
	}
	//获取域名解析记录
	public function getJL($ym){
		$request = new Alidns\DescribeSubDomainRecordsRequest();
		$request->setSubDomain($ym);
		$request->setMethod("POST");
		$response = $this->client->getAcsResponse($request);
		if(isset($response->DomainRecords->Record[0]))
			return $response->DomainRecords->Record[0];
		else
			return false;
	}
	//修改域名解析记录
	public function editJL($info){
		$request = new Alidns\UpdateDomainRecordRequest();
		$request->setRecordId($info['RecordId']);
		$request->setRR($info['RR']);
		$request->setType($info['Type']);
		$request->setValue($info['Value']);
		$request->setTTL($info['TTL']);
		$request->setMethod("POST");
		$response = $this->client->getAcsResponse($request);
		if(isset($response->RecordId) && $response->RecordId == $info['RecordId'])
			return true;
		else
			return false;
	}
}

function curl_get($url){
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HEADER, 1);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
    $data = curl_exec($curl);
    curl_close($curl);
    return $data;	
}
