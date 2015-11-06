<?php

require_once __DIR__.'/Request.php';

function megaplanTestConnect($id) {
	return;
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