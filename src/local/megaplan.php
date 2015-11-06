<?php

require_once __DIR__.'/Request.php';

class megaplan extends SdfApi_Request 
{
	protected $sdf;

	public function __construct($crmid)
	{
		global $conf;
		if ($conf->db->type == 'postgres') {
			$db = pg_connect('host='.$conf->db->host.' dbname='.$conf->db->database.' user='.$conf->db->username.' password='.$conf->db->password) or die('Невозможно подключиться к БД: '.pg_last_error());
		}
		$query = "SELECT \"AccessId\", \"SecretKey\", \"Domain\" FROM \"public\".\"crm_megaplan\" WHERE \"Id\" = '{$crmid}'";
		$result = pg_query($query);
		$AccessId = pg_fetch_result($result, 0, 0);
		$SecretKey = pg_fetch_result($result, 0, 1);
		$Domain = pg_fetch_result($result, 0, 2);
		pg_free_result($result);
		pg_close($db);
		$this->sdf = new SdfApi_Request($AccessId, $SecretKey, $Domain, true);
	}

	public function getEmployee()
	{
		$employee = $this->sdf->get('/BumsStaffApiV01/Employee/list.api');
		$employee = json_decode($employee, true);
		return $employee['data']['employees'];
	}

	public function getFields()
	{
		$fields = $this->sdf->get('/BumsCrmApiV01/Contractor/listFields.api');
		$fields = json_decode($fields, true);
		return $fields['data']['Fields'];
	}

	public function getPhoneTypes()
	{
		$phTypes = $this->sdf->get('/BumsStaffApiV01/Employee/phoneTypes.api');
		$phTypes = json_decode($phTypes, true);
		return $phTypes['data']['PhoneTypes'];
	}

	public function getAdvertisingWays()
	{
		$opt = array('Id' => 1, 'RequestedFields' => array('AdvertisingWay'));
		$AdvertisingWays = $this->sdf->get('/BumsCrmApiV01/Contractor/card.api', $opt);
		// $AdvertisingWays = json_decode($AdvertisingWays, true)
		return $AdvertisingWays;
	}

	public function putCompany()
	{
		$opt = array(
			"Model[TypePerson]" => "company",
			"Model[CompanyName]" => "Хуй!!!",
			"Model[Email]" => "mega@huy.org",
			"Model[Phones]" => array("ph_w-7-3952-781089\t", "ph_w-7-3952-401079\t"),
			"Model[Responsibles]" => "1000000",
			"Model[ActivityType]" => "1000002",
			"Model[Icq]" => "881129",
			"Model[Skype]" => "arsen_loren",
			"Model[Facebook]" => "https://www.fb.com/arsen.bespalov",
			"Model[Twitter]" => "https://www.twitter.com/arsenbespalov",
			"Model[Site]" => "https://www.arsen.pw",
			"Model[AdvertisingWay]" => "9",
			"Model[Description]" => "sjkdgfjhsdgfjh<br><strong>dsfsdf</strong><br><a href=sdfsdf>ssdfsdf</a>"
		);
		$result = $this->sdf->post('/BumsCrmApiV01/Contractor/save.api', $opt);
		return $result;
	}

}

function megaplanAuthorize() {
	global $conf;

	$host = $_REQUEST['host'];
	$login = $_REQUEST['login'];
	$password = md5($_REQUEST['password']);

	if ($ch = curl_init()) {
		curl_setopt($ch, CURLOPT_URL, 'https://'.$host.'/BumsCommonApiV01/User/authorize.api');
		curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, array('Login' => $login, 'Password' => $password));
		$response = curl_exec($ch);
		curl_close($ch);
	}

	if ($response)
		$response = json_decode($response, true);
	else
		return false;
	
	if ($response['status']['code'] == 'ok') {
		if ($conf->db->type == 'postgres') {
			$db = pg_connect('host='.$conf->db->host.' dbname='.$conf->db->database.' user='.$conf->db->username.' password='.$conf->db->password) or die('Невозможно подключиться к БД: '.pg_last_error());
		}

		$query = 'INSERT INTO "public"."crm_megaplan" ("Domain", "AccessId", "SecretKey", "UserId", "EmployeeId") VALUES ('."'{$host}', '{$response['data']['AccessId']}', '{$response['data']['SecretKey']}', '{$response['data']['UserId']}', '{$response['data']['EmployeeId']}') ".' RETURNING "Id"';
		$result = pg_query($query);
		$megaplanid = pg_fetch_result($result, 0, 0);
		$query = "UPDATE \"public\".\"users\" SET \"megaplan\" = '{$megaplanid}' WHERE \"id\" = '{$_SESSION['userid']}'";
		pg_query($query);
		pg_free_result($result);
		pg_close($db);
		$return = true;
	} else {
		$return = false;
	}
	return $return;
}