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

global $my_config , $getIpList , $getIpv6List;

// 定义保存解析的文件路径 以及域名 以及阿里云AccessKEY信息
$my_config['config']='./config.json';
$my_config['errlog']='./AliyunDnsErrLog.log';
$my_config['AccessKeyID']='';
$my_config['AccessKeySecret']='';
$my_config['ym'][]=[
	'domain' => 'xxx.xxx.com',
	'type' => 'A',//IPV4:A IPV6:AAAA
];
$my_config['ym'][]=[
	'domain' => 'xxx.xxx.com',
	'type' => 'AAAA',//IPV4:A IPV6:AAAA
	'interface' => 'eth0', //需要获取IPV6的网卡名称
];


// 定义获取本机公网IP的URL列表以及匹配IP的正则
$getIpList=array(
	array('http://2018.ip138.com/ic.asp','(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})'),
	array('https://www.ip.cn','(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})'),
	array('http://checkip.dyndns.com','(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})'),
);

// 定义获取本机公网IP的URL列表以及匹配IP的正则
$getIpv6List=array(
	array('http://checkipv6.dyndns.com','(((([0-9A-Fa-f]{1,4}:){7}([0-9A-Fa-f]{1,4}|:))|(([0-9A-Fa-f]{1,4}:){6}(:[0-9A-Fa-f]{1,4}|((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})|:))|(([0-9A-Fa-f]{1,4}:){5}(((:[0-9A-Fa-f]{1,4}){1,2})|:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})|:))|(([0-9A-Fa-f]{1,4}:){4}(((:[0-9A-Fa-f]{1,4}){1,3})|((:[0-9A-Fa-f]{1,4})?:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){3}(((:[0-9A-Fa-f]{1,4}){1,4})|((:[0-9A-Fa-f]{1,4}){0,2}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){2}(((:[0-9A-Fa-f]{1,4}){1,5})|((:[0-9A-Fa-f]{1,4}){0,3}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){1}(((:[0-9A-Fa-f]{1,4}){1,6})|((:[0-9A-Fa-f]{1,4}){0,4}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(:(((:[0-9A-Fa-f]{1,4}){1,7})|((:[0-9A-Fa-f]{1,4}){0,5}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:)))(%.+)?)'),
);

check_update();

function check_update(){
	global $my_config , $getIpList , $getIpv6List;
	foreach ($my_config['ym'] as  $ym) {
		$key=$ym['domain'].'_'.$ym['type'];
		$localip = false;
		$iplist = $ym['type']=='A'?$getIpList:$getIpv6List;

		// 获取IPV6地址改用本地获取非临时地址
		if($ym['type']=='AAAA'){
			$localip = getLocalGlobalIpv6Addr($ym['interface']);
		}else{
			//获取IP 如果获取失败 重试五次
			for($i=0 ; $i != 5 ;$i++){
				foreach($iplist as $getIp){
					$text=curl_get($getIp[0]);
					if(preg_match ($getIp[1], $text, $regs)){
						$localip=$regs[0];
						break;
					}
				}
				if($localip !== false)
					break;
			}
		}

		if($localip == false){
			file_put_contents($my_config['errlog'],date('Y-m-d H:i:s')." Get Ip err\r\n",FILE_APPEND );
			continue;
		}

		//从本地获取数据
		$info = @json_decode(file_get_contents($my_config['config']),true);
		
		//如果本地数据不存在 则从服务器获取信息
		if(!isset($info[$key]['RecordId']) || !isset($info[$key]['RR'])){
			$data=false;
			$a=new aliyun($my_config);
			for($i=0 ; $i != 5 ;$i++){
				$data=$a->getJL($ym);
				if($data !== false){
					break;
				}
			}
			unset($a);
			if($data == false){
				file_put_contents($my_config['errlog'],date('Y-m-d H:i:s')." Get IpInof err\r\n",FILE_APPEND);
				continue;
			}
		
			$info[$key]['RecordId']=$data->RecordId;
			$info[$key]['RR']=$data->RR;
			$info[$key]['Type']=$data->Type;
			$info[$key]['DomainName']=$data->DomainName;
			$info[$key]['TTL']=$data->TTL;
			$info[$key]['Value']=$data->Value;
			
			//保存数据到本地
			file_put_contents($my_config['config'],json_encode($info));
		}
		
		if($info[$key]['Value'] != $localip){
			$info[$key]['Value'] = $localip;
			$data=false;
			$a=new aliyun($my_config);
			for($i=0 ; $i != 5 ;$i++){
				$data=$a->editJL($info[$key]);
				if($data)
					break;
			}
			unset($a);
			if($data){
				file_put_contents($my_config['config'],json_encode($info));
				file_put_contents($my_config['errlog'],date('Y-m-d H:i:s').' Edit IpInof ok New IP:'.$info[$key]['Value']."\r\n",FILE_APPEND);
				continue;
			}else{
				file_put_contents($my_config['errlog'],date('Y-m-d H:i:s')." Edit IpInof err\r\n",FILE_APPEND);
				continue;
			}
		}
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
		$request->setSubDomain($ym['domain']);
		$request->setMethod("POST");
		$request->setType($ym['type']);
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

function getOS() {
	$os = php_uname('s');
	if( strpos($os, 'Linux') !== false ) {
		return 'Linux';
	} else if( strpos($os, 'Darwin') !== false ) {
		return 'Darwin';
	} else if( strpos($os, 'Windows') !== false ) {
		return 'Windows';
	}
}

function getLocalGlobalIpv6Addr($interface) {
	$ip_info = '';
	$pattern = '';
	switch (getOS()) {
		case 'Linux':
			//inet6 2001:xxxx:xxxx:xxxx::xxxx/128 scope global [temporary] dynamic [mngtmpaddr] [noprefixroute] 
			$pattern = '/inet6 ([0-9a-f:]+)\/\\d+ scope global/';
			$ip_info = shell_exec('ip -6 addr show '.$interface);
			break;

		case 'Darwin':
			// inet6 2001:xxxx:xxxx::xxxx prefixlen 64 autoconf [secured|temporary] 
			$pattern = '/inet6 ([0-9a-f:]+) prefixlen \\d+ autoconf secured/';
			$ip_info = shell_exec('ifconfig '.$interface);
			break;

		case 'Windows':
			// 2001:xxxx:xxxx:xxxx::xxxx%xx
			$pattern = '/([0-9a-f:]+)%'.$interface.'/';
			$ip_info = shell_exec('ipconfig');
			break;

		default:

			break;
	}
	preg_match_all($pattern, $ip_info, $matches);
	if( isset($matches[1]) && count($matches[1]) > 0 ) {
		foreach ($matches[1] as $ip) {
			if( strpos($ip, 'fe80') === false ) {
				return $ip;
			}
		}
	}
	return false;
}
