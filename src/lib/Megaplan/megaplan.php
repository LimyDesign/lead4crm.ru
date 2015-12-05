<?php

require_once __DIR__ . '/Request.php';

class megaplan extends SdfApi_Request
{
	protected $sdf;
	protected $crmid;
	protected $UserId;
	protected $Responsibles;

	public function __construct($crmid)
	{
		global $conf;
		if ($conf->db->type == 'postgres') {
			$db = pg_connect('host='.$conf->db->host.' dbname='.$conf->db->database.' user='.$conf->db->username.' password='.$conf->db->password) or die('Невозможно подключиться к БД: '.pg_last_error());
		}
		$query = "SELECT \"AccessId\", \"SecretKey\", \"Domain\", \"EmployeeId\", \"Responsibles\" FROM \"public\".\"crm_megaplan\" WHERE \"Id\" = '{$crmid}'";
		$result = pg_query($query);
		$AccessId = pg_fetch_result($result, 0, 0);
		$SecretKey = pg_fetch_result($result, 0, 1);
		$Domain = pg_fetch_result($result, 0, 2);
		$UserId = pg_fetch_result($result, 0, 3);
		$Responsibles = pg_fetch_result($result, 0, 4);
		pg_free_result($result);
		pg_close($db);
		$this->sdf = new SdfApi_Request($AccessId, $SecretKey, $Domain, true);
		$this->crmid = $crmid;
		$this->UserId = $UserId;
		$this->Responsibles = $Responsibles;
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

	public function getLeadUser()
	{
		return $this->getUserInfo($this->UserId);
	}

	public function getUserInfo($id)
	{
		$opt = array("Id" => $id);
		$response = $this->sdf->get('/BumsStaffApiV01/Employee/card.api', $opt);
		$response = json_decode($response, true);
		return $response['data']['employee'];
	}

	public function getResponsibles()
	{
		global $conf;
		if ($conf->db->type == 'postgres') {
			$db = pg_connect('host='.$conf->db->host.' dbname='.$conf->db->database.' user='.$conf->db->username.' password='.$conf->db->password) or die('Невозможно подключиться к БД: '.pg_last_error());
		}
		$query = "SELECT \"Responsibles\" FROM \"public\".\"crm_megaplan\" WHERE \"Id\" = '{$this->crmid}'";
		$result = pg_query($query);
		$Responsibles = pg_fetch_result($result, 0, 0);

		pg_free_result($result);
		pg_close($db);
		return explode(',', $Responsibles);
	}

	public function putCompany($coFields)
	{
		$dubl = $this->getClient($coFields['name']);
		if (count($dubl['data']['clients']) == 0) {
			$opt = array(
				"Model[TypePerson]" => "company",
				"Model[CompanyName]" => $coFields['name'],
				"Model[Email]" => $coFields['email'],
				"Model[Phones]" => array_merge(explode(',', $coFields['phone']), explode(',', $coFields['fax'])),
				"Model[Responsibles]" => $this->Responsibles,
				// "Model[ActivityType]" => "1000002",
				"Model[Icq]" => $coFields['icq'],
				"Model[Jabber]" => $coFields['jabber'],
				"Model[Skype]" => $coFields['skype'],
				"Model[Facebook]" => $coFields['facebook'],
				"Model[Twitter]" => $coFields['twitter'],
				"Model[Site]" => $coFields['website'],
				// "Model[AdvertisingWay]" => "9",
				"Model[Description]" => $coFields['comment']
			);
			$result = $this->sdf->post('/BumsCrmApiV01/Contractor/save.api', $opt);
			$result = json_decode($result, true);
			$opt2 = array(
				"ContractorId" => $result['data']['contractor']['Id'],
				"PayerId" => $result['data']['contractor']['PayerId'],
				"PayerType" => "Legal",
				"Model[Address]" => $coFields['address']
			);
			$result2 = $this->sdf->post('/BumsCrmApiV01/Payer/save.api', $opt2);
			$result2 = json_decode($result2, true);
			return json_encode($result, JSON_UNESCAPED_UNICODE);
		} else {
			return json_encode(array('status' => array('code' => 'warning', 'message' => 'Дубликат')), JSON_UNESCAPED_UNICODE);
		}
	}

	public function putSetting()
	{
		global $conf;
		if ($conf->db->type == 'postgres') {
			$db = pg_connect('host='.$conf->db->host.' dbname='.$conf->db->database.' user='.$conf->db->username.' password='.$conf->db->password) or die('Невозможно подключиться к БД: '.pg_last_error());
		}
		$responsibles = implode(',', $_REQUEST['Responsibles']);
		$this->Responsibles = $responsibles;
		$query = "UPDATE \"public\".\"crm_megaplan\" SET \"Responsibles\" = '{$responsibles}' WHERE \"Id\" = '{$this->crmid}'";
		pg_query($query);
		pg_close($db);
		return true;
	}

	public static function convertPhone($number)
	{
		if (preg_match('/(\d)(\d{3})(\d{7})/', $number, $matches))
		{
			return $matches[1].'-'.$matches[2].'-'.$matches[3];
		}
	}

	public static function Authorize() 
	{
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

			$query = 'INSERT INTO "public"."crm_megaplan" ("Domain", "AccessId", "SecretKey", "UserId", "EmployeeId", "Responsibles") VALUES ('."'{$host}', '{$response['data']['AccessId']}', '{$response['data']['SecretKey']}', '{$response['data']['UserId']}', '{$response['data']['EmployeeId']}', '{$response['data']['EmployeeId']}') ".' RETURNING "Id"';
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

	public function Disconnect()
	{
		global $conf;
		if ($conf->db->type == 'postgres') {
			$db = pg_connect('host='.$conf->db->host.' dbname='.$conf->db->database.' user='.$conf->db->username.' password='.$conf->db->password) or die('Невозможно подключиться к БД: '.pg_last_error());
		}
		$query = "UPDATE \"public\".\"users\" SET \"megaplan\" = DEFAULT WHERE \"id\" = '{$_SESSION['userid']}'";
		pg_query($query);
		$query = "DELETE FROM \"public\".\"crm_megaplan\" WHERE \"Id\" = '{$this->crmid}'";
		pg_query($query);
		pg_close($db);
		return true;
	}

}