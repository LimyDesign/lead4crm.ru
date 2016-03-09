<?php

require_once __DIR__ . '/Request.php';

class megaplan extends SdfApi_Request
{
    protected $api;
	protected $sdf;
	protected $crmid;
	protected $UserId;
	protected $Responsibles;

    /**
     * megaplan constructor.
     * @param int $crmid
     * @param \Lead4CRM\API $api
     */
	public function __construct($crmid, $api)
	{
        $sql = 'SELECT "AccessId", "SecretKey", "Domain", "EmployeeId", "Responsibles" FROM crm_megaplan WHERE "Id" = :crmid';
        $params = array();
        $params[] = array(':crmid', $crmid, \PDO::PARAM_INT);
        $data = $api->getSingleRow($sql, $params);
		$this->sdf = new SdfApi_Request($data['AccessId'], $data['SecretKey'], $data['Domain'], true);
		$this->crmid = $crmid;
		$this->UserId = $data['EmployeeId'];
		$this->Responsibles = $data['Responsibles'];
        $this->api = $api;
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
		return explode(',', $this->Responsibles);
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
		$responsibles = implode(',', $_REQUEST['Responsibles']);
		$this->Responsibles = $responsibles;
        $sql = 'UPDATE crm_megaplan SET "Responsibles" = :resp WHERE "Id" = :crmid';
        $params = array();
        $params[] = array(':resp', $responsibles, \PDO::PARAM_STR);
        $params[] = array(':crmid', $this->crmid, \PDO::PARAM_INT);
        $this->api->postSqlQuery($sql, $params);
		return true;
	}

	public static function convertPhone($number)
	{
		if (preg_match('/(\d)(\d{3})(\d{7})/', $number, $matches))
			return $matches[1].'-'.$matches[2].'-'.$matches[3];
		else
            return 0;
	}

    /**
     * Функция авторизации в CRM Мегаплан и привязки к Lead4CRM.
     *
     * @param \Lead4CRM\API $api
     * @return bool
     */
	public static function Authorize($api)
	{
        $response = null;
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
            $sql = 'INSERT INTO crm_megaplan ("Domain", "AccessId", "SecretKey", "UserId", "EmployeeId", "Responsibles") VALUES (:domain, :accessid, :secretkey, :userid, :employeeid, :responsibles) RETURNING "Id"';
            $params = array();
            $params[] = array(':domain', $host, \PDO::PARAM_STR);
            $params[] = array(':accessid', $response['data']['AccessId'], \PDO::PARAM_STR);
            $params[] = array(':secretkey', $response['data']['SecretKey'], \PDO::PARAM_STR);
            $params[] = array(':userid', $response['data']['UserId'], \PDO::PARAM_INT);
            $params[] = array(':employeeid', $response['data']['EmployeeId'], \PDO::PARAM_INT);
            $params[] = array(':responsibles', $response['data']['EmployeeId'], \PDO::PARAM_INT);
            $data = $api->getSingleRow($sql, $params);
            $sql = 'UPDATE users SET megaplan = :mpid WHERE id = :uid';
            $params = array();
            $params[] = array(':mpid', $data['Id'], \PDO::PARAM_INT);
            $params[] = array(':uid', $_SESSION['userid'], \PDO::PARAM_INT);
            $api->postSqlQuery($sql, $params);
			$return = true;
		} else {
			$return = false;
		}
		return $return;
	}

	public function Disconnect($uid)
	{
        $sql = 'UPDATE users SET megaplan = NULL WHERE id = :uid';
        $params = array();
        $params[] = array(':uid', $uid, \PDO::PARAM_INT);
        $this->api->postSqlQuery($sql, $params);
        $sql = 'DELETE FROM crm_megaplan WHERE "Id" = :crmid';
        $params = array();
        $params[] = array(':crmid', $this->crmid, \PDO::PARAM_INT);
        $this->api->postSqlQuery($sql, $params);
		return true;
	}

}