<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/*
1. reserve status
/api/status?id=&pw=&loc=&date=&time=
=> 'result': true/false
2. name
/api/name?id=&pw=
=> 'name': name
3. login
/api/login?id=&pw=
=> 'result': true/false
4. reserve
/api/reserve?id=&pw=&loc=&date=&time=
=> no output
5. remaining
/api/remaining?date=&loc=&day=
=> remaining[int,int,int,...], day[int]
6. reserve status all
/api/status_all?id=&pw=
=> 'result':
7. cancel
/api/r_cancel?id=&pw=&loc=&date=&time=
*/
class Api extends CI_Controller {
	protected $context;
	protected $JSESSIONID;
	protected $__smVisitorID;
	protected $id;
	public function __construct()
	{
		parent::__construct();
		header('Content-Type: application/json');
		if(isset($_GET["id"])&&isset($_GET["pw"])){
			$this->id=$_GET["id"];
			$password=$_GET["pw"];
		}else{
			$this->id="2014198024";
			$password="1852512";
		}
		$result = file_get_contents('http://ysweb.yonsei.ac.kr/ysbus.jsp');
		$this->JSESSIONID= substr($http_response_header[8],23,91);
		$this->__smVisitorID= substr($http_response_header[7],26,11);
		$postdata = http_build_query(
		array(
			'userid' => $this->id,
			'password' => $password
		)
	);
	$opts = array(
		"http"=>array(
			"method"=>"POST",
			"header"=>"Content-type: application/x-www-form-urlencoded\r\n".
			"Cookie: JSESSIONID=$this->JSESSIONID; __smVisitorID=$this->__smVisitorID",
			"content" => $postdata
		)
	);
	$this->context = stream_context_create($opts);
	$file = file_get_contents('http://ysweb.yonsei.ac.kr/ysbus_main.jsp', false, $this->context);
	$opts = array(
		"http"=>array(
			"method"=>"GET",
			"header"=>"Content-type: application/x-www-form-urlencoded\r\n".
			"Cookie: JSESSIONID=$this->JSESSIONID; __smVisitorID=$this->__smVisitorID"
		)
	);
	$this->context = stream_context_create($opts);
}
public function remaining(){
	$date=$_GET['date'];
	$loc=$_GET['loc'];
	$day;
	if(isset($_GET['day'])){
		$day=$_GET['day'];
	}
	$postdata = http_build_query(
	array(
		'code' => 'S',
		'MYFORM_LOCATION' => $loc,
		'MYFORM_DATE' => $date
		)
	);
	$opts = array(
	"http"=>array(
		"method"=>"POST",
		"header"=>"Content-type: application/x-www-form-urlencoded\r\n".
		"Cookie: JSESSIONID=$this->JSESSIONID; __smVisitorID=$this->__smVisitorID",
		"content" => $postdata
		)
	);
	$context = stream_context_create($opts);
	$file=file_get_contents("http://ysweb.yonsei.ac.kr/busTest/index2.jsp", false, $context);
	$DOM = new DOMDocument;
	$DOM->loadHTML($file);
	$tbody = $DOM->getElementsByTagName('tbody');
	$tr=$tbody->item(0)->getElementsByTagName('tr');
	$result=array();
	for ($i=0; $i < $tr->length; $i++) {
		$td=$tr->item($i)->getElementsByTagName('td');
		$remain=$td->item(3)->nodeValue;
		$result['remaining'][$i]=trim($remain);
	}
	$result['day']=$day;
	echo json_encode($result);
}
public function status(){
	$file = file_get_contents('http://ysweb.yonsei.ac.kr/busTest/reserveinfo2.jsp', false, $this->context);
	// echo $file;
	$DOM = new DOMDocument;
	$DOM->loadHTML($file);
	$tbody = $DOM->getElementsByTagName('tbody');
	$tr=$tbody->item(0)->getElementsByTagName('tr');
	$result=array();
	$loc =$_GET['loc']; //출발 위치  S, I
	$bdt =$_GET['date']; //예약 날짜 20160303
	$shm =$_GET['time']; //출발시간 1330

	for ($i=0; $i < $tr->length; $i++) {
		$td=$tr->item($i)->getElementsByTagName('td');
		//TODO 결과값이 아예 없을 때에 대한 error 핸들링 해줘야함.
		$departure= $td->item(0)->nodeValue;
		$time=$td->item(1)->nodeValue;
		$seatNum=$td->item(2)->nodeValue;
		//출발위치
		$departure=explode('(',$departure);
		//시간
		$time=explode(' ',$time);
		$temp_date=explode('/',$time[0]);
		$temp_time=explode(':',$time[1]);
		$time=$temp_time[0].$temp_time[1];
		// $date=array("year"=>$temp_date[0],"month"=>$temp_date[1],"date"=>$temp_date[2]);

		//자리
		$pos=strrpos($seatNum,"Seat No:");
		$seatNum= substr($seatNum,$pos+9,strlen($seatNum)-$pos-10);
		// $result["$i"]["departure"]=($departure[0]=="신촌캠퍼스 "?"S":"I");
		// $result["$i"]["time"]["date"]=$date;
		// $result["$i"]["time"]["time"]=$time;
		// $result["$i"]["seatNum"]=$seatNum;
		$loc_result=(substr($departure[1],0,1)=="S"?"S":"I");
		$date_result=$temp_date[0].$temp_date[1].$temp_date[2];
		$time_result=$time;
		// echo $loc_result." : ".$date_result." : ".$time_result;
		if($loc==$loc_result&&$bdt==$date_result&&$shm==$time_result){
			$result['result']=true;
			echo json_encode($result);
			return;
		}
	}
	$result['result']=false;
	echo json_encode($result);
}
public function name(){
	$file = file_get_contents('http://ysweb.yonsei.ac.kr/busTest/notice2.jsp', false, $this->context);
	$DOM = new DOMDocument;
	$DOM->loadHTML($file);
	$classname = 'idname';
	$finder = new DomXPath($DOM);
	$nodes = $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]");
	$tmp_dom = new DOMDocument();
	foreach ($nodes as $node)
	{
		$tmp_dom->appendChild($tmp_dom->importNode($node,true));
	}
	$innerHTML=trim($tmp_dom->saveHTML());
	$result=array();
	$result["name"]=substr($innerHTML,21,stripos($innerHTML,"<br>")-21);
	echo json_encode($result);
}
public function login(){
	$file = file_get_contents('http://ysweb.yonsei.ac.kr/busTest/notice2.jsp', false, $this->context);
	$DOM = new DOMDocument;
	$DOM->loadHTML($file);
	$classname = 'idname';
	$finder = new DomXPath($DOM);
	$nodes = $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]");
	$tmp_dom = new DOMDocument();
	foreach ($nodes as $node)
	{
		$tmp_dom->appendChild($tmp_dom->importNode($node,true));
	}
	$innerHTML=trim($tmp_dom->saveHTML());
	$result=array();
	$result["result"]=(substr($innerHTML,21,stripos($innerHTML,"<br>")-21)?true:false);
	echo json_encode($result);
}
public function reserve(){
	$loc =$_GET['loc']; //출발 위치  S, I
	$bdt =$_GET['date']; //예약 날짜 20160303
	$shm =$_GET['time']; //출발시간 1330
	$bcd =$loc."1"; //버스 일련 번호
	$chk ="Y";

	$postdata = http_build_query(
	array(
		'loc' => $loc,//
		'bdt' => $bdt, //
		'shm' => $shm, //
		'bcd' => $bcd, //
		'chk' => $chk,
		'gbn' => 1, //
		'code' => 'I', //
		'MYFORM_LOCATION' => $loc,
		'MYFORM_DATE' => $bdt, //
		'MYFORM_REASON' => 1
	)
);
$opts = array(
	"http"=>array(
		"method"=>"POST",
		"header"=>"Content-type: application/x-www-form-urlencoded\r\n".
		"Cookie: JSESSIONID=$this->JSESSIONID; __smVisitorID=$this->__smVisitorID; gbn=1; yisid=$this->id; lang=0;",
		"content" => $postdata
	)
);
$context = stream_context_create($opts);
$file = file_get_contents('http://ysweb.yonsei.ac.kr/busTest/index2.jsp', false, $context);
}

public function status_all(){
	$file = file_get_contents('http://ysweb.yonsei.ac.kr/busTest/reserveinfo2.jsp', false, $this->context);
	// echo $file;
	$DOM = new DOMDocument;
	$DOM->loadHTML($file);
	$tbody = $DOM->getElementsByTagName('tbody');
	$tr=$tbody->item(0)->getElementsByTagName('tr');
	$result=array();
	$loc =$_GET['loc']; //출발 위치  S, I
	$bdt =$_GET['date']; //예약 날짜 20160303
	$shm =$_GET['time']; //출발시간 1330

	for ($i=0; $i < $tr->length; $i++) {
		$td=$tr->item($i)->getElementsByTagName('td');
		//TODO 결과값이 아예 없을 때에 대한 error 핸들링 해줘야함.
		$departure= $td->item(0)->nodeValue;
		$time=$td->item(1)->nodeValue;
		$seatNum=$td->item(2)->nodeValue;
		//출발위치
		$departure=explode('(',$departure);
		//시간
		$time=explode(' ',$time);
		$temp_date=explode('/',$time[0]);
		$temp_time=explode(':',$time[1]);
		$time=$temp_time[0].$temp_time[1];
		// $date=array("year"=>$temp_date[0],"month"=>$temp_date[1],"date"=>$temp_date[2]);

		//자리
		$pos=strrpos($seatNum,"Seat No:");
		$seatNum= substr($seatNum,$pos+9,strlen($seatNum)-$pos-10);
		$loc_result=(substr($departure[1],0,1)=="S"?"S":"I");
		$date_result=$temp_date[0].$temp_date[1].$temp_date[2];
		$time_result=$time;

		$result["$i"]["loc"]=$loc_result;
		$result["$i"]["date"]=$date_result;
		$result["$i"]["time"]=$time_result;
		$result["$i"]["seatNum"]=$seatNum;

	}
	echo json_encode($result);
}
public function r_cancel(){
	$loc =$_GET['loc']; //출발 위치  S, I
	$bdt =$_GET['date']; //예약 날짜 20160303
	$shm =$_GET['time']; //출발시간 1330
	$bcd =$loc."1"; //버스 일련 번호
	$seat=$_GET["seat"];

	$postdata = http_build_query(
	array(
		'loc' => $loc,//
		'bdt' => $bdt, //
		'shm' => $shm, //
		'bcd' => $bcd, //
		'chk' => $chk,
		'seat'=> $seat,
		'code' => 'D', //
		'MYFORM_LOCATION' => $loc,
		'MYFORM_DATE' => $bdt, //
		'MYFORM_REASON' => 1
	)
);
$opts = array(
	"http"=>array(
		"method"=>"POST",
		"header"=>"Content-type: application/x-www-form-urlencoded\r\n".
		"Cookie: JSESSIONID=$this->JSESSIONID; __smVisitorID=$this->__smVisitorID; gbn=1; yisid=$this->id; lang=0;",
		"content" => $postdata
	)
);
$context = stream_context_create($opts);
$file = file_get_contents('http://ysweb.yonsei.ac.kr/busTest/reserveinfo2.jsp', false, $context);
}
}
