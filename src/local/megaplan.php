<?php

require_once __DIR__.'/Request.php';

function crmTestConnect($id) {
	return;
}

function crmAuthorize() {
	$host = $_REQUEST['host'];
	$login = urldecode($_REQUEST['login']);
	$password = md5($_REQUEST['password']);

	if ($ch = curl_init()) {
		curl_setopt($ch, CURLOPT_URL, 'https://'.$host.'/BumsCommonApiV01/User/authorize.api');
		curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, array('Login' => $login, 'Password' => $password));
		$result = curl_exec($ch);
		var_dump($result);
		curl_close($ch);
	}
}