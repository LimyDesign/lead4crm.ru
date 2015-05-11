<?php
session_start();
require_once __DIR__.'/src/vendor/autoload.php';
$conf = json_decode(file_get_contents(__DIR__.'/config.json'));

$loader = new Twig_Loader_Filesystem(__DIR__.'/views');
$twig = new Twig_Environment($loader, array(
	'cache' => __DIR__.'/cache',
	'auto_reload' => true,
	'optimizations' => -1
));
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false, true);

$requestURI = explode('/',$_SERVER['REQUEST_URI']);
$scriptName = explode('/',$_SERVER['SCRIPT_NAME']);
for ($i=0;$i<sizeof($scriptName);$i++)
{
	if ($requestURI[$i] == $scriptName[$i])
		unset($requestURI[$i]);
}
$cmd = array_values($requestURI);

if ($cmd[0]) {
	switch ($cmd[0]) {
		case 'cabinet':
			isAuth();
		case $cmd[0]:
			switch ($cmd[0]) {
				case 'about-project': $title = 'О проекте'; break;
				case 'about-us': $title = 'О нас'; break;
				case 'price': $title = 'Цены'; break;
				case 'support': $title = 'Поддержка'; break;
				case 'cabinet': $title = 'Личный кабинет'; break;
				case 'login': getDataLogin($cmd[1]); exit(2);
				default: $title = '404 - Страница не найдена'; break;
			}
			$options = array(
				'title' => $title,
				'userid' => $_SESSION['userid'],
				'currentUrl' => 'http://' . $_SERVER['HTTP_HOST'] . '/' . $cmd[0] . '/');
			$options = array_merge($options, arrayOAuthLoginURL(), arrayMenuUrl());
			if (file_exists(__DIR__.'/views/'.$cmd[0].'.twig')) {
				echo $twig->render($cmd[0].'.twig', $options);
			} else {
				header("HTTP/1.0 404 Not Found");
				echo $twig->render('404.twig', $options);
			}
			break;
	}
} else {
	$options = array(
		'title' => 'Генератор лидов для Битрикс 24',
		'userid' => $_SESSION['userid'],
		'currentUrl' => 'http://' . $_SERVER['HTTP_HOST']);
	$options = array_merge($options, arrayOAuthLoginURL(), arrayMenuUrl());
	echo $twig->render('index.twig', $options);
}

function arrayOAuthLoginURL() {
	global $conf;

	if ($_SESSION['state']) {
		$state = $_SESSION['state'];
	} else {
		$state = sha1($_SERVER['HTTP_USER_AGENT'].time());
		$_SESSION['state'] = $state;
	}

	$vklogin = http_build_query(array(
		'client_id' => $conf->provider->vkontakte->CLIENT_ID,
		'scope' => 'email',
		'redirect_uri' => 'http://' . $_SERVER['HTTP_HOST'] . '/login/vkontakte/',
		'response_type' => 'code',
		'v' => '5.29',
		'state' => $state,
		'display' => 'page'));
	$oklogin = http_build_query(array(
		'client_id' => $conf->provider->odnoklassniki->CLIENT_ID,
		'scope' => 'GET_EMAIL',
		'response_type' => 'code',
		'redirect_uri' => 'http://' . $_SERVER['HTTP_HOST'] . '/login/odnoklassniki/',
		'state' => $state));
	$fblogin = http_build_query(array(
		'client_id' => $conf->provider->facebook->CLIENT_ID,
		'scope' => 'email',
		'redirect_uri' => 'http://' . $_SERVER['HTTP_HOST'] . '/login/facebook/',
		'response_type' => 'code'));
	$gplogin = http_build_query(array(
		'client_id' => $conf->provider->{google-plus}->CLIENT_ID,
		'scope' => 'https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/userinfo.profile',
		'redirect_uri' => 'http://' . $_SERVER['HTTP_HOST'] . '/login/google-plus/',
		'response_type' => 'code',
		'state' => $state,
		'access_type' => 'online',
		'approval_prompt' => 'auto',
		'login_hint' => 'email',
		'include_granted_scopes' => 'true'));
	$mrlogin = http_build_query(array(
		'client_id' => $conf->provider->mailru->CLIENT_ID,
		'response_type' => 'code',
		'redirect_uri' => 'http://' . $_SERVER['HTTP_HOST'] . '/login/mailru/'));
	$yalogin = http_build_query(array(
		'client_id' => $conf->provider->yandex->CLIENT_ID,
		'response_type' => 'code',
		'state' => $state));
	return array(
		'vklogin' => 'https://oauth.vk.com/authorize?' . $vklogin,
		'oklogin' => 'http://www.odnoklassniki.ru/oauth/authorize?' . $oklogin,
		'fblogin' => 'https://www.facebook.com/dialog/oauth?' . $fblogin,
		'gplogin' => 'https://accounts.google.com/o/oauth2/auth?' . $gplogin,
		'mrlogin' => 'https://connect.mail.ru/oauth/authorize?' . $mrlogin,
		'yalogin' => 'https://oauth.yandex.ru/authorize?' . $yandex);
}

function arrayMenuUrl() {
	return array(
		'mainpage_url' => 'http://' . $_SERVER['HTTP_HOST'],
		'aboutproject_url' => 'http://' . $_SERVER['HTTP_HOST'] . '/about-project/',
		'aboutours_url' => 'http://' . $_SERVER['HTTP_HOST'] . '/about-us/',
		'prices_url' => 'http://' . $_SERVER['HTTP_HOST'] . '/price/',
		'support_url' => 'http://' . $_SERVER['HTTP_HOST'] . '/support/',
		'cabinet_url' => 'http://' . $_SERVER['HTTP_HOST'] . '/cabinet/'
		);
}

function getDataLogin($provider) {
	switch ($provider) {
		case 'facebook':
			fbLogin();
			break;

		case 'vkontakte':
			vklogin();
			break;

		case 'odnoklassniki':
			oklogin();
			break;

		case 'google-plus':
			gplogin();
			break;

		case 'yandex':
			yalogin();
			break;

		case 'mailru':
			mrlogin();
			break;
		
		default:
			header("HTTP/1.1 412 Precondition Failed");
			header("Content-Type: text/plain");
			echo "Необходимо указать корректный тип авторизации.";
			break;
	}
}

function fblogin() {
	global $conf;
	$redirect_uri = 'http://'.$_SERVER['HTTP_HOST'].'/login/facebook/';
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	$data = http_build_query(array(
		'client_id' => $conf->provider->facebook->CLIENT_ID,
		'client_secret' => $conf->provider->facebook->CLIENT_SECRET,
		'code' => $_GET['code'],
		'redirect_uri' => $redirect_uri));
	curl_setopt($curl, CURLOPT_URL, 'https://graph.facebook.com/oauth/access_token?'.$data);
	parse_str($response = curl_exec($curl));
	curl_setopt($curl, CURLOPT_URL, 'https://graph.facebook.com/me?access_token='.$access_token);
	$res = json_decode(curl_exec($curl));
	dbLogin($res->id, $res->email, 'fb');
}

function vklogin() {
	global $conf;
	$redirect_uri = 'http://'.$_SERVER['HTTP_HOST'].'/login/vkontakte/';
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	$data = http_build_query(array(
		'client_id' => $conf->provider->vkontakte->CLIENT_ID,
		'client_secret' => $conf->provider->vkontakte->CLIENT_SECRET,
		'code' => $_GET['code'],
		'redirect_uri' => $redirect_uri));
	curl_setopt($curl, CURLOPT_URL, 'https://oauth.vk.com/access_token?'.$data);
	$res = json_decode(curl_exec($curl));
	dbLogin($res->user_id, $res->email, 'vk');
}

function gplogin() {
	global $conf;
	$redirect_uri = 'http://'.$_SERVER['HTTP_HOST'].'/login/google-plus/';
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	$data =  http_build_query(array(
		'client_id' => $conf->provider->{google-plus}->CLIENT_ID,
		'client_secret' => $conf->provider->{google-plus}->CLIENT_SECRET,
		'code' => $_GET['code'],
		'redirect_uri' => $redirect_uri,
		'grant_type' => 'authorization_code'));
	curl_setopt($curl, CURLOPT_URL, 'https://accounts.google.com/o/oauth2/token');
	curl_setopt($curl, CURLOPT_POST, true);
	curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
	$res = json_decode(curl_exec($curl));
	curl_setopt($curl, CURLOPT_URL, 'https://www.googleapis.com/oauth2/v1/userinfo?access_token='.$res->access_token);
	curl_setopt($curl, CURLOPT_POST, false);
	$res = json_decode(curl_exec($curl));
	dbLogin($res->id, $res->email, 'gp');
}

function oklogin() {
	global $conf;
	$redirect_uri = 'http://'.$_SERVER['HTTP_HOST'].'/login/odnoklassniki/';
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	$data = http_build_query(array(
		'client_id' => $conf->provider->odnoklassniki->CLIENT_ID,
		'client_secret' => $conf->provider->odnoklassniki->SECRET_KEY,
		'code' => $_GET['code'],
		'redirect_uri' => $redirect_uri,
		'grant_type' => 'authorization_code'));
	curl_setopt($curl, CURLOPT_URL, 'https://api.odnoklassniki.ru/oauth/token.do');
	curl_setopt($curl, CURLOPT_POST, true);
	curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
	$res = json_decode(curl_exec($curl));
	$con_param = 'application_key='.$conf->provider->odnoklassniki->PUBLIC_KEY.'fields=uid,emailmethod=users.getCurrentUser';
	$ac_ask = $res->access_token.$conf->provider->odnoklassniki->SECRET_KEY;
	$md5_ac_ask = md5($ac_ask);
	$sig = $con_param . $md5_ac_ask;
	$md5_sig = md5($sig);
	$data = http_build_query(array(
		'application_key' => $conf->provider->odnoklassniki->PUBLIC_KEY,
		'method' => 'users.getCurrentUser',
		'access_token' => $res->access_token,
		'fields' => 'uid,email',
		'sig' => $md5_sig));
	curl_setopt($curl, CURLOPT_URL, 'http://api.ok.ru/fb.do?'.$data);
	curl_setopt($curl, CURLOPT_POST, false);
	$res = json_decode(curl_exec($curl));
	dbLogin($res->uid, $res->email, 'ok');
}

function mrlogin() {
	global $conf;
	$redirect_uri = 'http://'.$_SERVER['HTTP_HOST'].'/login/mailru/';
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	$data = http_build_query(array(
		'client_id' => $conf->provider->mailru->CLIENT_ID,
		'client_secret' => $conf->provider->mailru->SECRET_KEY,
		'code' => $_GET['code'],
		'redirect_uri' => $redirect_uri,
		'grant_type' => 'authorization_code'));
	curl_setopt($curl, CURLOPT_URL, 'https://connect.mail.ru/oauth/token');
	curl_setopt($curl, CURLOPT_POST, true);
	curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
	$res = json_decode(curl_exec($curl));
	$sig = 'app_id='.$conf->provider->mailru->CLIENT_ID.'method=users.getInfosecure=1session_key='.$res->access_token.$conf->provider->mailru->SECRET_KEY;
	$md5_sig = md5($sig);
	$data = http_build_query(array(
		'app_id' => $conf->provider->mailru->CLIENT_ID,
		'method' => 'users.getInfo',
		'secure' => 1,
		'session_key' => $res->access_token,
		'sig' => $md5_sig));
	curl_setopt($curl, CURLOPT_URL, 'http://www.appsmail.ru/platform/api?'.$data);
	curl_setopt($curl, CURLOPT_POST, false);
	$res = json_decode(curl_exec($curl));
	dbLogin($res[0]->uid, $res[0]->email, 'mr');
}

function yalogin() {
	global $conf;
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	$data = http_build_query(array(
		'client_id' => $conf->provider->yandex->CLIENT_ID,
		'client_secret' => $conf->provider->yandex->CLIENT_SECRET,
		'code' => $_GET['code'],
		'grant_type' => 'authorization_code'));
	curl_setopt($curl, CURLOPT_URL, 'https://oauth.yandex.ru/token');
	curl_setopt($curl, CURLOPT_POST, true);
	curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
	$res = json_decode(curl_exec($curl));
	curl_setopt($curl, CURLOPT_URL, 'https://login.yandex.ru/info?oauth_token='.$res->access_token);
	curl_setopt($curl, CURLOPT_POST, false);
	$res = json_decode(curl_exec($curl));
	dbLogin($res->id, $res->default_email, 'ya');
}

function dbLogin($userId, $userEmail, $provider) {
	global $conf;
	if ($conf->db->type == 'postgres') {
		$db = pg_connect('dbname='.$conf->db->database) or die('Невозможно подключиться к БД: '.pg_last_error());
		if ($_SESSION['userid']) {
			$query = "UPDATE users SET {$provider} = {$userId} WHERE id = {$_SESSION['userid']}";
			$result = pg_query($query);
			pg_free_result($result);
			pg_close($db);
			header("Location: /cabinet/");
		} else {
			$query = "SELECT * FROM users WHERE {$provider} = '{$userId}'";
			$result = pg_query($query);
			if (pg_num_rows($result) != 1) {
				$state = sha1($_SERVER['HTTP_USER_AGENT'].time());
				$query = "INSERT INTO users (email, {$provider}, apikey) VALUES ('{$userEmail}', '{$userId}', '$state') RETURNING id, contract";
				$result = pg_query($query);
				$userid = pg_fetch_result($result, 0, 'id');
				$contract = pg_fetch_result($result, 0, 'contract');
			} else {
				$userid = pg_fetch_result($result, 0, 'id');
				$contract = pg_fetch_result($result, 0, 'contract');
				$company = pg_fetch_result($result, 0, 'company');
				$is_admin = pg_fetch_result($result, 0, 'is_admin');
			}
			$_SESSION['userid'] = $userid;
			$_SESSION['contract'] = $contract;
			$_SESSION['company'] = $company;
			$_SESSION['is_admin'] = $is_admin;
			$_SESSION['auth'] = true;
			pg_free_result($result);
			pg_close($db);
			header("Location: /cabinet/");
		}
	}
}

function isAuth() {
	if (!$_SESSION['userid']) {
		if ($_SERVER['HTTP_REFERER'])
			$referer = $_SERVER['HTTP_REFERER'];
		else
			$referer = '';
		header("Location: {$referer}/");
	}
}
?>