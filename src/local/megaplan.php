<?php

require_once __DIR__.'/Request.php';

function crmTestConnect($id) {
	return;
}

function crmConnect() {
	$host = $_REQUEST['host'];
	$login = urlencode($_REQUEST['login']);
	$password = md5($_REQUEST['password']);

	$result = file_get_contents('https://'.$host.'/BumsCommonApiV01/User/authorize.api?login='.$login.'&password='.$password);
	var_dump($result);
}