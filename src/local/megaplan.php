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

	public function getAdvertisingWays($Id)
	{
		$opt = array('Id' => $Id, 'RequestedFields' => array('AdvertisingWay', 'ActivityType'));
		$response = $this->sdf->get('/BumsCrmApiV01/Contractor/card.api', $opt);
		$response = json_decode($response, true);
		return $response;
	}

	public function getClient($companyName)
	{
		$opt = array("Model[PersonType]" => "company", "Model[CompanyName]" => $companyName);
		$response = $this->sdf->get('/BumsCrmApiV01/Contractor/list.api', $opt);
		$response = json_decode($response, true);
		return $response;
	}

	public function putCompany($coFields)
	{
		$opt = array(
			"Model[TypePerson]" => "company",
			"Model[CompanyName]" => $coFields['CompanyName'],
			"Model[Email]" => $coFields['Email'],
			"Model[Phones]" => $coFields['Phones'],
			// "Model[Phones]" => array("ph_w-7-3952-781089\t", "ph_w-7-3952-401079\t"),
			"Model[Responsibles]" => $coFields['Responsibles'],
			// "Model[ActivityType]" => "1000002",
			"Model[Icq]" => $coFields['Icq'],
			"Model[Jabber]" => $coFields['Jabber'],
			"Model[Skype]" => $coFields['Skype'],
			"Model[Facebook]" => $coFields['Facebook'],
			"Model[Twitter]" => $coFields['Twitter'],
			"Model[Site]" => $coFields['Site'],
			// "Model[AdvertisingWay]" => "9",
			"Model[Description]" => $coFields['Description']
		);
		$result = $this->sdf->post('/BumsCrmApiV01/Contractor/save.api', $opt);
		return $result;
	}

	public static function convertPhone($number)
	{
		if (preg_match('/(\d)(\d{3})(\d{7})/', $number, $matches))
		{
			return $matches[1].'-'.$matches[2].'-'.$matches[3];
		}
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