<?php
session_start();

if (isset($_SERVER['HTTP_ORIGIN']))
{
	header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
	header("Access-Control-Allow-Credentials: true");
	header("Access-Control-Max-Age: 86400");
}

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS')
{
	if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
		header("Access-Control-Allow-Method: GET, POST, PUT, DELETE, OPTIONS");

	if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
		header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
}

require_once __DIR__.'/src/vendor/autoload.php';
$conf = json_decode(file_get_contents(__DIR__.'/config.json'));

$loader = new Twig_Loader_Filesystem(__DIR__.'/views');
$twig = new Twig_Environment($loader, array(
	'cache' => __DIR__.'/cache',
	'auto_reload' => true,
	'optimizations' => -1
));
$twig->addExtension(new \Salva\JshrinkBundle\Twig\Extension\JshrinkExtension);
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
		case 'logout':
			logout();
			break;

		case 'webcall':
			echo getWebCall($_POST['phone'], $_POST['delay']);
			break;

		case 'step-2':
			wizard($_POST['crm_id'], 2);
			break;

		case 'getSupportCities':
			getSupportCities();
			break;

		case 'getUserData':
			isAuth($cmd);
			getUserData();
			break;

		case 'getUserCache':
			isAuth($cmd);
			getUserCache();
			break;

		case 'getSelection':
			isAuth($cmd);
			getSelection($cmd[1], $_REQUEST['crm_id']);
			break;

		case 'getB24UserData':
			getB24UserData($_POST['apikey']);
			break;

		case 'getDataSearch':
			echo getDataSearch(
				$_REQUEST['searchAPI'], 
				$_REQUEST['searchText'], 
				$_REQUEST['searchCity'],
				$_REQUEST['searchDomain'],
				$_REQUEST['searchPage']);
			break;

		case 'getDataSearchRubric':
			echo getDataSearchRubric(
				$_REQUEST['searchAPI'],
				$_REQUEST['searchRubric'],
				$_REQUEST['searchCity'],
				$_REQUEST['searchDomain'],
				$_REQUEST['searchPage']);
			break;

		case 'getRubricList':
			echo importRubrics(
				$_REQUEST['importAPI'],
				$_REQUEST['importDomain'],
				true);
			break;

		case 'importRubrics':
			echo importRubrics(
				$_REQUEST['importAPI'],
				$_REQUEST['importDomain']);
			break;

		case 'importCompany':
			echo importCompany(
				$_REQUEST['importAPI'],
				$_REQUEST['importDomain'],
				$_REQUEST['importCompanyID'],
				$_REQUEST['importCompanyHash'],
				$_REQUEST['assignedUserId'],
				getRealIpAddr(),
				$_REQUEST['getFrom2GIS']);
			break;

		case 'newAPIKey':
			isAuth($cmd);
			newAPIKey();
			break;

		case 'getInvoice':
			isAuth($cmd);
			generateInvoice($_POST['invoicesum'], $_POST['companyname']);
			break;

		case 'payment':
			yandexPayments($cmd[1]);
			break;

		case 'setTariff':
			isAuth($cmd);
			setTariff($cmd[1]);
			break;

		case 'cabinet':
			isAuth($cmd);
			$top_rubrics = importRubrics($_SESSION['apikey'], 'www.lead4crm.ru');
			$top_rubrics = json_decode($top_rubrics, true);
			$cOptions = array(
				'apikey' => $_SESSION['apikey'],
				'company' => $_SESSION['company'],
				'provider' => $_SESSION['provider'],
				'userid' => $_SESSION['userid'],
				'crm_list' => getCRM(),
				'countries' => getCountries(getUserCityByIP()),
				'top_rubrics' => $top_rubrics,
				'links' => arrayOAuthLoginURL(),
				'yaShopId' => $conf->payments->ShopID,
				'yaSCId' => $conf->payments->SCID,
				'tariffs' => getUserTariffList());

		case 'b24-install':
		case 'b24-index':
			if ($auth = $_REQUEST['AUTH_ID']) {
				$domain = ($_REQUEST['PROTOCOL'] == 0 ? 'http' : 'https') . '://'. $_REQUEST['DOMAIN'];
				$isAdmin = json_decode(file_get_contents($domain.'/rest/user.admin.json?auth='.$auth));
				$res = file_get_contents($domain.'/rest/user.current.json?auth='.$auth);
				$arRes = json_decode($res, true);
				$cOptions = array(
					'isBX24User' => true,
					'request' => $_REQUEST,
					'isAdmin' => $isAdmin->result,
					'installURL' => '/b24-install/' . $_SERVER['QUERY_STRING'],
					'res' => $arRes,
					'apikey' => $_SESSION['apikey'],
					'countries' => getCountries($arRes['result']['PERSONAL_CITY']),
					'userData' => getUserData('array'));
			}

		case 'b24-install-dev':
		case 'b24-index-dev':
			if ($auth = $_REQUEST['AUTH_ID']) {
				$domain = ($_REQUEST['PROTOCOL'] == 0 ? 'http' : 'https') . '://'. $_REQUEST['DOMAIN'];
				$isAdmin = json_decode(file_get_contents($domain.'/rest/user.admin.json?auth='.$auth));
				$res = file_get_contents($domain.'/rest/user.current.json?auth='.$auth);
				$arRes = json_decode($res, true);
				$cOptions = array(
					'isBX24User' => true,
					'request' => $_REQUEST,
					'isAdmin' => $isAdmin->result,
					'installURL' => '/b24-install-dev/' . $_SERVER['QUERY_STRING'],
					'res' => $arRes,
					'apikey' => $_SESSION['apikey'],
					'countries' => getCountries($arRes['result']['PERSONAL_CITY']),
					'userData' => getUserData('array'));
			}

		case $cmd[0]:
			switch ($cmd[0]) {
				case 'about-project': $title = 'О проекте'; break;
				case 'about-us': $title = 'О нас'; break;
				case 'price': $title = 'Цены'; break;
				case 'support': $title = 'Поддержка'; break;
				case 'subscribe-confirm': $title = 'Подтверждение подписки'; break;
				case 'subscribe': $title = 'Спасибо!'; break;
				case 'cabinet': $title = 'Личный кабинет'; break;
				case 'login': getDataLogin($cmd[1]); exit(2);
				default: $title = '404 - Страница не найдена'; break;
			}
			$options = array(
				'title' => $title,
				'userid' => $_SESSION['userid'],
				'currentUrl' => 'https://' . $_SERVER['SERVER_NAME'] . '/' . $cmd[0] . '/');
			$options = array_merge($options, arrayOAuthLoginURL(), arrayMenuUrl());
			
			if (count($cOptions) > 0)
				$options = array_merge($options, $cOptions);

			if (file_exists(__DIR__.'/views/'.$cmd[0].'.twig') && $cmd[0] != '403') {
				echo $twig->render($cmd[0].'.twig', $options);
			} else {
				header("HTTP/1.0 404 Not Found");
				echo $twig->render('404.twig', $options);
			}
			break;
	}
} else {
	$options = array(
		'title' => 'Генератор лидов для CRM: Битрикс24, Мегаплан',
		'userid' => $_SESSION['userid'],
		'currentUrl' => 'http://' . $_SERVER['SERVER_NAME']);
	$options = array_merge($options, arrayOAuthLoginURL(), arrayMenuUrl());
	echo $twig->render('index.twig', $options);
}

function getWebCall($phone, $delay = 0) {
	global $conf;
	$sipnet_url = "https://api.sipnet.ru/cgi-bin/Exchange.dll/sip_balance".
				  "?operation=genCall".
				  "&sipuid=".$conf->sipnet->id.
				  "&password=".$conf->sipnet->password.
				  "&SrcPhone=".$conf->sipnet->phone.
				  "&DstPhone=".$phone.
				  "&Delay=".$delay.
				  "&format=2".
				  "&lang=ru";
	return file_get_contents($sipnet_url);
}

function getCRM() {
	global $conf;
	$crm_list = array();
	if ($conf->db->type == 'postgres') {
		$db = pg_connect('host='.$conf->db->host.' dbname='.$conf->db->database.' user='.$conf->db->username.' password='.$conf->db->password) or die('Невозможно подключиться к БД: '.pg_last_error());
		$query = "select id, name from crm_systems where enabled = true order by name asc";
		$result = pg_query($query);
		while ($row = pg_fetch_assoc($result)) {
			$crm_list[$row['id']]['id'] = $row['id'];
			$crm_list[$row['id']]['name'] = $row['name'];
			$query2 = "select id, version from crm_versions where crmid = {$row['id']} order by version asc";
			$result2 = pg_query($query2);
			$crm = array();
			while ($row2 = pg_fetch_assoc($result2)) {
				$crm[$row2['id']]['id'] = $row2['id'];
				$crm[$row2['id']]['version'] = $row2['version'];
			}
			pg_free_result($result2);
			$crm_list[$row['id']]['versions'] = $crm;
		}
		pg_free_result($result);
		pg_close($db);
	}
	return $crm_list;
}

function wizard($crm_id, $step) {
	global $conf;
	header("Content-Type: text/json");
	$return_array = array();
	if ($crm_id && $step) {
		if ($step == 2) {
			if ($conf->db->type == 'postgres') {
				$db = pg_connect('host='.$conf->db->host.' dbname='.$conf->db->database.' user='.$conf->db->username.' password='.$conf->db->password) or die('Невозможно подключиться к БД: '.pg_last_error());
				$query = "select crm_versions.module, crm_systems.name from crm_versions left join crm_systems on crm_systems.id = crm_versions.crmid where crm_versions.id = {$crm_id}";
				$result = pg_query($query);
				if ($module = pg_fetch_result($result, 0, 'module')) {
					$return_array['error'] = '0';
					$return_array['module'] = $module;
					$return_array['name'] = pg_fetch_result($result, 0, 'name');
				} else {
					$return_array['error'] = '0';
					$return_array['module'] = '';
				}
			}
		}
	} else {
		$return_array['error'] = '500';
		$return_array['message'] = 'Отсутствует обязательный параметр.';
	}
	
	echo json_encode($return_array, JSON_UNESCAPED_UNICODE);
}

function getUserCityByIP() {
	$ipaddress = getRealIpAddr();
	$geoDataJSON = file_get_contents('http://api.sypexgeo.net/json/'.$ipaddress);
	$geoData = json_decode($geoDataJSON);
	return $geoData->city->name_ru;
}

function getCountries($userCity) {
	global $conf;
	$countries = array();
	if ($conf->db->type == 'postgres') {
		$db = pg_connect('host='.$conf->db->host.' dbname='.$conf->db->database.' user='.$conf->db->username.' password='.$conf->db->password) or die('Невозможно подключиться к БД: '.pg_last_error());
		$query = 'select id, name from country order by sort asc, name asc';
		$result = pg_query($query);
		while ($row = pg_fetch_assoc($result)) {
			$countries[$row['id']]['id'] = $row['id'];
			$countries[$row['id']]['name'] = $row['name'];

			$query2 = "select id, name, parent_id from cities where country_id = {$row['id']} order by name asc";
			$result2 = pg_query($query2);
			$cities = array();
			while ($row2 = pg_fetch_assoc($result2)) {
				if ($row2['parent_id']) {
					$cities[$row2['parent_id']]['children'] = $cities[$row2['parent_id']]['children'] ? $cities[$row2['parent_id']]['children'] . ', ' . $row2['name'] : $row2['name'];
					if ($userCity == $row2['name'])
						$cities[$row2['parent_id']]['selected'] = 1;
				} else {
					$cities[$row2['id']]['code'] = $row2['id'];
					$cities[$row2['id']]['name'] = $row2['name'];
					if ($userCity == $row2['name'])
						$cities[$row2['id']]['selected'] = 1;
				}
			}
			pg_free_result($result2);
			$countries[$row['id']]['cities'] = $cities;
		}
		pg_free_result($result);
		pg_close($db);
	}
	return $countries;
}

function getCities($userCity) {
	global $conf;
	$cities = array();
	if ($conf->db->type == 'postgres') {
		$db = pg_connect('host='.$conf->db->host.' dbname='.$conf->db->database.' user='.$conf->db->username.' password='.$conf->db->password) or die('Невозможно подключиться к БД: '.pg_last_error());
		$query = 'select * from cities order by name asc';
		$result = pg_query($query);
		while ($row = pg_fetch_assoc($result)) {
			$cities[$row['id']]['code'] = $row['id'];
			$cities[$row['id']]['name'] = $row['name'];
			if ($userCity == $row['name'])
				$cities[$row['id']]['selected'] = 1;
			else
				$cities[$row['id']]['selected'] = 0;
		}
	}
	return $cities;
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
		'redirect_uri' => 'https://' . $_SERVER['SERVER_NAME'] . '/login/vkontakte/',
		'response_type' => 'code',
		'v' => '5.29',
		'state' => $state,
		'display' => 'page'));
	$oklogin = http_build_query(array(
		'client_id' => $conf->provider->odnoklassniki->CLIENT_ID,
		'scope' => 'GET_EMAIL',
		'response_type' => 'code',
		'redirect_uri' => 'https://' . $_SERVER['SERVER_NAME'] . '/login/odnoklassniki/',
		'state' => $state));
	$fblogin = http_build_query(array(
		'client_id' => $conf->provider->facebook->CLIENT_ID,
		'scope' => 'email',
		'redirect_uri' => 'https://' . $_SERVER['SERVER_NAME'] . '/login/facebook/',
		'response_type' => 'code'));
	$gplogin = http_build_query(array(
		'client_id' => $conf->provider->{"google-plus"}->CLIENT_ID,
		'scope' => 'https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/userinfo.profile',
		'redirect_uri' => 'https://' . $_SERVER['SERVER_NAME'] . '/login/google-plus/',
		'response_type' => 'code',
		'state' => $state,
		'access_type' => 'online',
		'approval_prompt' => 'auto',
		'login_hint' => 'email',
		'include_granted_scopes' => 'true'));
	$mrlogin = http_build_query(array(
		'client_id' => $conf->provider->mailru->CLIENT_ID,
		'response_type' => 'code',
		'redirect_uri' => 'https://' . $_SERVER['SERVER_NAME'] . '/login/mailru/'));
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
		'yalogin' => 'https://oauth.yandex.ru/authorize?' . $yalogin);
}

function arrayMenuUrl() {
	return array(
		'mainpage_url' => 'https://' . $_SERVER['SERVER_NAME'],
		'aboutproject_url' => 'https://' . $_SERVER['SERVER_NAME'] . '/about-project/',
		'aboutours_url' => 'https://' . $_SERVER['SERVER_NAME'] . '/about-us/',
		'prices_url' => 'https://' . $_SERVER['SERVER_NAME'] . '/price/',
		'support_url' => 'https://' . $_SERVER['SERVER_NAME'] . '/support/',
		'blog_url' => 'http://blog.lead4crm.ru/',
		'cabinet_url' => 'https://' . $_SERVER['SERVER_NAME'] . '/cabinet/'
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
	$redirect_uri = 'https://'.$_SERVER['SERVER_NAME'].'/login/facebook/';
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
	$redirect_uri = 'https://'.$_SERVER['SERVER_NAME'].'/login/vkontakte/';
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
	$redirect_uri = 'https://'.$_SERVER['SERVER_NAME'].'/login/google-plus/';
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	$data =  http_build_query(array(
		'client_id' => $conf->provider->{"google-plus"}->CLIENT_ID,
		'client_secret' => $conf->provider->{"google-plus"}->CLIENT_SECRET,
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
	$redirect_uri = 'https://'.$_SERVER['SERVER_NAME'].'/login/odnoklassniki/';
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
	$redirect_uri = 'https://'.$_SERVER['SERVER_NAME'].'/login/mailru/';
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
		$db = pg_connect('host='.$conf->db->host.' dbname='.$conf->db->database.' user='.$conf->db->username.' password='.$conf->db->password) or die('Невозможно подключиться к БД: '.pg_last_error());
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
				$query = "INSERT INTO users (email, {$provider}, apikey) VALUES ('{$userEmail}', '{$userId}', '{$state}') RETURNING id, vk, ok, fb, gp, mr, ya, contract";
				$result = pg_query($query);
				$userid = pg_fetch_result($result, 0, 'id');
				$vk = pg_fetch_result($result, 0, 'vk');
				$ok = pg_fetch_result($result, 0, 'ok');
				$fb = pg_fetch_result($result, 0, 'fb');
				$gp = pg_fetch_result($result, 0, 'gp');
				$mr = pg_fetch_result($result, 0, 'mr');
				$ya = pg_fetch_result($result, 0, 'ya');
				$contract = pg_fetch_result($result, 0, 'contract');
				$apikey = $state;
			} else {
				$userid = pg_fetch_result($result, 0, 'id');
				$contract = pg_fetch_result($result, 0, 'contract');
				$company = pg_fetch_result($result, 0, 'company');
				$is_admin = pg_fetch_result($result, 0, 'is_admin');
				$apikey = pg_fetch_result($result, 0, 'apikey');
				$vk = pg_fetch_result($result, 0, 'vk');
				$ok = pg_fetch_result($result, 0, 'ok');
				$fb = pg_fetch_result($result, 0, 'fb');
				$gp = pg_fetch_result($result, 0, 'gp');
				$mr = pg_fetch_result($result, 0, 'mr');
				$ya = pg_fetch_result($result, 0, 'ya');
			}
			$_SESSION['userid'] = $userid;
			$_SESSION['contract'] = $contract;
			$_SESSION['company'] = $company;
			$_SESSION['is_admin'] = $is_admin;
			$_SESSION['apikey'] = $apikey;
			$_SESSION['provider'] = array('vk' => $vk, 'ok' => $ok, 'fb' => $fb, 'gp' => $gp, 'mr' => $mr, 'ya' => $ya);
			$_SESSION['auth'] = true;
			pg_free_result($result);
			pg_close($db);
			header("Location: /cabinet/");
		}
	}
}

function getB24UserData($apikey) {
	global $conf;
	if ($conf->db->type == 'postgres') {
		$db = pg_connect('host='.$conf->db->host.' dbname='.$conf->db->database.' user='.$conf->db->username.' password='.$conf->db->password) or die('Невозможно подключиться к БД: '.pg_last_error());
		$query = "select name, price from tariff where id = (select tariffid2 from users where apikey = '{$apikey}')";
		$result = pg_query($query);
		$tariff = pg_fetch_result($result, 0, 'name');
		$price = pg_fetch_result($result, 0, 'price');
		$tariff = $tariff ? $tariff : 'Демо';
		$query = "select qty + trunc((select sum(debet) - sum(credit) from log where uid = (select id from users where apikey = '{$apikey}')) / {$price}) as qty from users where apikey = '{$apikey}'";
		$result = pg_query($query);
		$qty = pg_fetch_result($result, 0, 'qty');
		pg_free_result($result);
		pg_query($db);
	}
	$fullData = array('tariff' => $tariff, 'qty' => $qty);
	header("Content-Type: text/json");
	echo json_encode($fullData);
	exit();
}

function getDataSearch($apikey, $text, $city, $domain, $page = 1) {
	$url = "http://api.cnamrf.ru/getCompanyList/{$page}/?";
	$uri = http_build_query(array(
		'apikey' => $apikey,
		'text' => $text,
		'city' => $city,
		'domain' => $domain));
	return file_get_contents($url.$uri);
}

function getDataSearchRubric($apikey, $rubric, $city, $domain, $page = 1) {
	$url = "http://api.cnamrf.ru/getCompanyListByRubric/{$page}/?";
	$uri = http_build_query(array(
		'apikey' => $apikey,
		'rubric' => $rubric,
		'city' => $city,
		'domain' => $domain));
	return file_get_contents($url.$uri);
}

function importRubrics($apikey, $domain, $full = false) {
	$url = "http://api.cnamrf.ru/getRubricList/?";
	$uri = http_build_query(array(
		'apikey' => $apikey,
		'domain' => $domain,
		'full'	 => $full));
	return file_get_contents($url.$uri);
}

function importCompany($apikey, $domain, $id, $hash, $auid, $ip, $getFrom2GIS) {
	$url = "http://api.cnamrf.ru/getCompanyProfile/?";
	$uri = http_build_query(array(
		'apikey' => $apikey,
		'domain' => $domain,
		'id' => $id,
		'hash' => $hash,
		'auid' => $auid,
		'uip' => $ip,
		'2gis' => $getFrom2GIS));
	return file_get_contents($url.$uri);
}

function getSupportCities() {
	global $conf;
	header("Content-Type: text/json");
	if (file_exists(__DIR__.'/cities.json')) {
		echo file_get_contents(__DIR__.'/cities.json');
	} else {
		if ($conf->db->type == 'postgres') {
			$db = pg_connect('host='.$conf->db->host.' dbname='.$conf->db->database.' user='.$conf->db->username.' password='.$conf->db->password) or die('Невозможно подключиться к БД: '.pg_last_error());
			$query = "select name from cities order by name asc";
			$result = pg_query($query);
			$i = 0;
			while ($row = pg_fetch_assoc($result)) {
				$city[$i]['name'] = $row['name'];
				$i++;
			}
			pg_free_result($result);
			pg_close($db);
			$json_array = array('city' => $city);
			$json = json_encode($json_array);
			file_put_contents(__DIR__.'/cities.json', $json);
			echo $json;
		}
	}
	exit();
}

function getUserCache() {
	global $conf;
	header("Content-Type: text/json");
	$cache = array();
	if ($conf->db->type == 'postgres') {
		$db = pg_connect('host='.$conf->db->host.' dbname='.$conf->db->database.' user='.$conf->db->username.' password='.$conf->db->password) or die('Невозможно подключиться к БД: '.pg_last_error());
		$query = "select t1.cp_id, t1.cp_hash, t2.modtime from cnam_cache as t1 left join log as t2 on t1.logid = t2.id where t2.uid = ".$_SESSION['userid']." order by t2.modtime desc";
		$result = pg_query($query);
		$i = 0;
		while ($row = pg_fetch_assoc($result)) {
			$cache[$i]['id'] = $row['cp_id'];
			$cache[$i]['hash'] = $row['cp_hash'];
			$cache[$i]['addtime'] = $row['modtime'];
			$i++;
		}
		pg_free_result($result);
		pg_close($db);
	}
	echo json_encode($cache);
	exit();
}

function getSelection($date, $crm_id) {
	global $conf;
	if ($conf->db->type == 'postgres') {
		$db = pg_connect('host='.$conf->db->host.' dbname='.$conf->db->database.' user='.$conf->db->username.' password='.$conf->db->password) or die('Невозможно подключиться к БД: '.pg_last_error());
		$query = "select t1.type, t1.name, t1.template from crm_templates as t1 left join crm_versions as t2 on t1.id = t2.templateid where t2.id = ".$crm_id;
		$result = pg_query($query);
		$type = pg_fetch_result($result, 0, 'type');
		$affix = pg_fetch_result($result, 0, 'name');
		$template = json_decode(pg_fetch_result($result, 0, 'template'), true);
		$filename = __DIR__.'/ucf/'.$_SESSION['userid'].'/2GIS_Base_'.$affix.'_'.$date.'.'.$type;
		if ($type == 'csv') {
			$csv_title = array();
			foreach ($template as $key => $value) {
				$csv_title[] = iconv('UTF-8', 'Windows-1251', $template[$key]['title']);
			}
			$csv = array($csv_title);
			$start_date = $date.'-01';
			$em = str_split($date);
			if ($em[5] == 1 && $em[6] == 2) {
				$_year = $em[0].$em[1].$em[2].$em[3];
				$_year += 1;
				$end_date = $_year.'-01-01';
			} elseif ($em[5] == 0 && $em[6] == 9) {
				$_year = $em[0].$em[1].$em[2].$em[3];
				$end_date = $_year.'-10-01';
			} elseif ($em[5] == 1 && ($em[6] == 0 || $em[6] == 1)) {
				$_year = $em[0].$em[1].$em[2].$em[3];
				$_month = $em[6]+1;
				$end_date = $_year.'-1'.$_month.'-01';
			} else {
				$_year = $em[0].$em[1].$em[2].$em[3];
				$_month = $em[6]+1;
				$end_date = $_year.'-0'.$_month.'-01';
			}
			$query = "select t1.cp_id, t1.cp_hash, t1.lon, t1.lat, t2.modtime from cnam_cache as t1 left join log as t2 on t1.logid = t2.id where t2.uid = {$_SESSION['userid']} and t2.modtime >= DATE '{$start_date}' and t2.modtime < DATE '{$end_date}' order by t2.modtime desc";
			$result = pg_query($query);
			while ($row = pg_fetch_array($result)) {
				$query2 = "select json from cnam_cp where id = ".$row['cp_id']." and hash = '".$row['cp_hash']."'";
				$result2 = pg_query($query2);
				$cp = json_decode(pg_fetch_result($result2, 0, 'json'), true);
				$query2 = "select json from geodata where lon = '".$row['lon']."' and lat = '".$row['lat']."'";
				$result2 = pg_query($query2);
				$gd = json_decode(pg_fetch_result($result2, 0, 'json'), true);
				$csv_line = array();
				foreach ($template as $key => $value) {
					if ($template[$key]['cp']) {
						if (preg_match('/^%(.*)%$/', $template[$key]['cp'], $cp_match)) {
							$_vals = explode('$', $cp_match[1]);
							if (count($_vals) > 1) {
								$tmp_arr = array();
								foreach($_vals as $key) {
									$tmp_arr = empty($tmp_arr) ? $cp[$key] : $tmp_arr[$key];
								}
								$csv_line[] = iconv('UTF-8', 'Windows-1251', $tmp_arr);
							} else {
								$csv_line[] = iconv('UTF-8', 'Windows-1251', $cp[$cp_match[1]]);
							}
						} else {
							if ($template[$key]['argv']) {
								if (preg_match('/^%(.*)%$/', $template[$key]['argv'], $argv_match)) {
									$csv_line[] = iconv('UTF-8', 'Windows-1251', call_user_func($template[$key]['cp'], $cp[$argv_match[1]], $cp));
								} else {
									$csv_line[] = iconv('UTF-8', 'Windows-1251', call_user_func($template[$key]['cp'], $template[$key]['argv'], $cp));
								}	
							} else {
								$csv_line[] = iconv('UTF-8', 'Windows-1251', call_user_func($template[$key]['cp'], $cp));
							}
						}
					} else if ($template[$key]['gd']) {
						if (preg_match('/^%(.*)%$/', $template[$key]['gd'], $gd_match)) {
							$csv_line[] = iconv('UTF-8', 'Windows-1251', $gd['result'][0]['attributes'][$gd_match[1]]);
						}
					} else {
						$csv_line[] = iconv('UTF-8', 'Windows-1251', $template[$key]['default']);
					}
				}
				$csv[] = $csv_line;
			}
			if (!file_exists(dirname($filename)))
				mkdir(dirname($filename), 0777, true);
			$fp = fopen($filename, 'w');
			foreach ($csv as $line) {
				fputcsv($fp, $line, ';', '"', '"');
			}
			fclose($fp);
		} elseif ($type == 'xls') {
			$xls = new PHPExcel();
			$xls->getProperties()->setCreator("www.lead4crm.ru");
			$xls->getProperties()->setLastModifiedBy('www.lead4crm.ru');
			$xls->getProperties()->setTitle('2GIS Base at '.$date);
			$xls->getProperties()->setSubject('2GIS Base');
			$xls->setActiveSheetIndex(0);

			$col = 0; $rows = 1; $cellType = PHPExcel_Cell_DataType::TYPE_STRING;
			foreach ($template as $key => $value) {
				$xls->getActiveSheet()->getCellByColumnAndRow($col, $rows)->setValueExplicit($template[$key]['title'], $cellType);
				$col++;
			}
			$rows++;
			$start_date = $date.'-01';
			$em = str_split($date);
			if ($em[5] == 1 && $em[6] == 2) {
				$_year = $em[0].$em[1].$em[2].$em[3];
				$_year += 1;
				$end_date = $_year.'-01-01';
			} elseif ($em[5] == 0 && $em[6] == 9) {
				$_year = $em[0].$em[1].$em[2].$em[3];
				$end_date = $_year.'-10-01';
			} elseif ($em[5] == 1 && ($em[6] == 0 || $em[6] == 1)) {
				$_year = $em[0].$em[1].$em[2].$em[3];
				$_month = $em[6]+1;
				$end_date = $_year.'-1'.$_month.'-01';
			} else {
				$_year = $em[0].$em[1].$em[2].$em[3];
				$_month = $em[6]+1;
				$end_date = $_year.'-0'.$_month.'-01';
			}
			$query = "select t1.cp_id, t1.cp_hash, t1.lon, t1.lat, t2.modtime from cnam_cache as t1 left join log as t2 on t1.logid = t2.id where t2.uid = {$_SESSION['userid']} and t2.modtime >= DATE '{$start_date}' and t2.modtime < DATE '{$end_date}' order by t2.modtime desc";
			$result = pg_query($query);
			while ($row = pg_fetch_array($result)) {
				$query2 = "select json from cnam_cp where id = ".$row['cp_id']." and hash = '".$row['cp_hash']."'";
				$result2 = pg_query($query2);
				$cp = json_decode(pg_fetch_result($result2, 0, 'json'), true);
				$query2 = "select json from geodata where lon = '".$row['lon']."' and lat = '".$row['lat']."'";
				$result2 = pg_query($query2);
				$gd = json_decode(pg_fetch_result($result2, 0, 'json'), true);
				$col = 0;
				foreach ($template as $key => $value) {
					if ($template[$key]['cp']) {
						if (preg_match('/^%(.*)%$/', $template[$key]['cp'], $cp_match)) {
							$_vals = explode('$', $cp_match[1]);
							if (count($_vals) > 1) {
								$tmp_arr = array();
								foreach($_vals as $key) {
									$tmp_arr = empty($tmp_arr) ? $cp[$key] : $tmp_arr[$key];
								}
								$xls->getActiveSheet()->getCellByColumnAndRow($col, $rows)->setValueExplicit($tmp_arr, $cellType);
							} else {
								$xls->getActiveSheet()->getCellByColumnAndRow($col, $rows)->setValueExplicit($cp[$cp_match[1]], $cellType);
							}
						} else {
							if ($template[$key]['argv']) {
								if (preg_match('/^%(.*)%$/', $template[$key]['argv'], $argv_match)) {
									$xls->getActiveSheet()->getCellByColumnAndRow($col, $rows)->setValueExplicit(call_user_func($template[$key]['cp'], $cp[$argv_match[1]], $cp), $cellType);
								} else {
									$xls->getActiveSheet()->getCellByColumnAndRow($col, $rows)->setValueExplicit(call_user_func($template[$key]['cp'], $template[$key]['argv'], $cp), $cellType);
								}	
							} else {
								$xls->getActiveSheet()->getCellByColumnAndRow($col, $rows)->setValueExplicit(call_user_func($template[$key]['cp'], $cp), $cellType);
							}
						}
					} else if ($template[$key]['gd']) {
						if (preg_match('/^%(.*)%$/', $template[$key]['gd'], $gd_match)) {
							$xls->getActiveSheet()->getCellByColumnAndRow($col, $rows)->setValueExplicit($gd['result'][0]['attributes'][$gd_match[1]], $cellType);
						}
					} else {
						$xls->getActiveSheet()->getCellByColumnAndRow($col, $rows)->setValueExplicit($template[$key]['default'], $cellType);
					}
					$col++;
				}
				$rows++;
			}
			foreach (range('A', $xls->getActiveSheet()->getHighestDataColumn()) as $col) {
				$xls->getActiveSheet()->getColumnDimension($col)->setAutoSize(true);
				$xls->getActiveSheet()->getStyle($col.'1')->getFont()->setBold(true);
			}
			$xls->getActiveSheet()->setTitle('Выборка из 2ГИС');

			if (!file_exists(dirname($filename)))
				mkdir(dirname($filename), 0777, true);
			$xlsw = new PHPExcel_Writer_Excel5($xls);
			$xlsw->save($filename);
		}
		fileForceDownload($date, $type, $affix);
	}
}

function fileForceDownload($date, $type, $affix) {
	$filename = __DIR__.'/ucf/'.$_SESSION['userid'].'/2GIS_Base_'.$affix.'_'.$date.'.'.$type;
	if (ob_get_level())
		ob_end_clean();
	switch ($type) {
		case 'csv':
			$ct = 'text/csv';
			break;

		case 'xls':
			$ct = 'application/vnd.ms-excel';
			break;

		case 'xlsx':
			$ct = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
			break;
	}
	header('Content-Description: File Transfer');
	header('Content-Type: '.$ct);
	header('Content-Disposition: attachment; filename='.basename($filename));
	header('Content-Transfer-Encoding: binary');
	header('Expires: 0');
	header('Cache-Control: must-revalidate');
	header('Pragma: public');
	header('Content-Length: ' . filesize($filename));
	if ($fd = fopen($filename, 'rb')) {
		while (!feof($fd)) {
			print fread($fd, 1024);
		}
		fclose($fd);
	}
	exit();
}

function getFullAddress($json) {
	if ($json['additional_info']['office'])
		$fullAddr = array('г.'.$json['city_name'], $json['address'], $json['additional_info']['office']);
	else
		$fullAddr = array('г. '.$json['city_name'], $json['address']);
	return implode(', ', $fullAddr);
}

function get2GISContact($type, $json, $asString = true) {
	$_return = array();
	for ($i = 0; $i < count($json['contacts']); $i++) {
		foreach ($json['contacts'][$i]['contacts'] as $contact) {
			if ($contact['type'] == $type) {
				$_return[] = ($type != 'website' ? $contact['value'] : 'http://'.$contact['alias']);
			}
		}
	}
	return ($asString ? implode(', ', $_return) : $_return);
}

function bx24Comment($cp) {
	$comment = '';
	if (count($cp['rubrics'])) {
		$comment .= '<p><b>Виды деятельности:</b></p><ul>';
		foreach ($cp['rubrics'] as $rubric)
			$comment .= '<li>'.$rubric.'</li>';
		$comment .= '</ul>';
	}
	$url_name = rawurlencode($cp['name']);
	$comment .= "<p><b>Дополнительная информация:</b></p><ul>"
		. "<li><a href='http://2gis.ru/city/{$cp['project_id']}/center/{$cp['lon']}%2C{$cp['lat']}/zoom/17/routeTab/to/{$cp['lon']}%2C{$cp['lat']}%E2%95%8E{$url_name}?utm_source=profile&utm_medium=route_to&utm_campaign=partnerapi' target='_blank'>Проложить маршрут до {$cp['name']}</a></li>"
	    . "<li><a href='http://2gis.ru/city/{$cp['project_id']}/center/{$cp['lon']}%2C{$cp['lat']}/zoom/17/routeTab/from/{$cp['lon']}%2C{$cp['lat']}%E2%95%8E{$url_name}?utm_source=profile&utm_medium=route_from&utm_campaign=partnerapi' target='_blank'>Проложить маршрут от {$cp['name']}</a></li>"
	    . "<li><a href='http://2gis.ru/city/{$cp['project_id']}/firm/{$cp['id']}/entrance/center/{$cp['lon']}%2C{$cp['lat']}/zoom/17?utm_source=profile&utm_medium=entrance&utm_campaign=partnerapi' target='_blank'>Показать вход</a></li>"
	    . "<li><a href='http://2gis.ru/city/{$cp['project_id']}/firm/{$cp['id']}/photos/{$cp['id']}/center/{$cp['lon']}%2C{$cp['lat']}/zoom/17?utm_source=profile&utm_medium=photo&utm_campaign=partnerapi' target='_blank'>Фотографии {$cp['name']}</a></li>"
	    . "<li><a href='http://2gis.ru/city/{$cp['project_id']}/firm/{$cp['id']}/flamp/{$cp['id']}/callout/firms-{$cp['id']}/center/{$cp['lon']}%2C{$cp['lat']}/zoom/17?utm_source=profile&utm_medium=review&utm_campaign=partnerapi' target='_blank'>Отзывы о {$cp['name']}</a></li>";
	$additional_info = "<li><a href='{$cp['bookle_url']}?utm_source=profile&utm_medium=booklet&utm_campaign=partnerapi' target='_blank'>Услуги и цены {$cp['name']}</a></li>";
	if ($cp['bookle_url'])
		$comment .= $additional_info;
	return $comment;
}

function getMainIndustry($rubrics) {
	global $conf;
	if ($conf->db->type == 'postgres') {
		if (count($rubrics)) {
			$db = pg_connect('host='.$conf->db->host.' dbname='.$conf->db->database.' user='.$conf->db->username.' password='.$conf->db->password) or die('Невозможно подключиться к БД: '.pg_last_error());
			$parents = array();
			foreach ($rubrics as $rubric) {
				$rubric = pg_escape_string($rubric);
				$query = "select parent from rubrics where name = '{$rubric}'";
				$result = pg_query($query);
				$parent_id1 = pg_fetch_result($result, 0, 0);
				$query = "select parent from rubrics where id = {$parent_id1}";
				$result = pg_query($query);
				$parent_id2 = pg_fetch_result($result, 0, 0);
				if ($parent_id2) 
					$parents[] = $parent_id2;
				else
					$parents[] = $parent_id1;
			}
			$main_parent = $main_parent2 = array_count_values($parents);
			arsort($main_parent2);
			foreach ($main_parent2 as $parent_id => $count) {
				if ($count > 1)
					$query = "select name from rubrics where id = {$parent_id}";
				else {
					$parent_id = key($main_parent);
					$query = "select name from rubrics where id = {$parent_id}";
				}
				break;
			}
			$result = pg_query($query);
			$name = pg_fetch_result($result, 0, 'name');
		} else {
			$name = "Другое";
		}
	}
	return $name;
}

function getUserData($return = 'json') {
	global $conf;
	if ($conf->db->type == 'postgres') {
		$db = pg_connect('host='.$conf->db->host.' dbname='.$conf->db->database.' user='.$conf->db->username.' password='.$conf->db->password) or die('Невозможно подключиться к БД: '.pg_last_error());
		$query = "select (sum(debet) - sum(credit)) as balans from log where uid = {$_SESSION['userid']}";
		$result = pg_query($query);
		$balans = pg_fetch_result($result, 0, 'balans');
		$balans = $balans ? $balans : '0';
		$query = "select name, price from tariff where id = (select tariffid2 from users where id = {$_SESSION['userid']})";
		$result = pg_query($query);
		$tariff = pg_fetch_result($result, 0, 'name');
		$price = pg_fetch_result($result, 0, 'price');
		$tariff = $tariff ? $tariff : 'Демо';
		$query = "select qty + trunc((select sum(debet) - sum(credit) from log where uid = {$_SESSION['userid']}) / {$price}) as qty from users where id = {$_SESSION['userid']}";
		$result = pg_query($query);
		$qty = pg_fetch_result($result, 0, 'qty');
		pg_free_result($result);
		pg_close($db);
	}
	$fullData = array('balans' => $balans, 'tariff' => $tariff, 'qty' => $qty);
	if ($return == 'json') {
		header("Content-Type: text/json");
		echo json_encode($fullData);
		exit();
	} elseif ($return == 'array') {
		return $fullData;
	}
}

function newAPIKey() {
	global $conf;
	if ($conf->db->type == 'postgres')
	{
		$db = pg_connect('host='.$conf->db->host.' dbname='.$conf->db->database.' user='.$conf->db->username.' password='.$conf->db->password) or die('Невозможно подключиться к БД: '.pg_last_error());
		$userid = $_SESSION['userid'];
		$apikey = sha1($_SERVER['HTTP_USER_AGENT'].time());
		$query = "update users set apikey = '{$apikey}' where id = {$userid}";
		pg_query($query);
		pg_close($db);
		$_SESSION['apikey'] = $apikey;
	}
	die($apikey);
}

function generateInvoice($userSumm, $userCompany) {
	global $pdf, $twig;

	$pdf->SetCreator('Lead4CRM');
	$pdf->SetAuthor('Arsen Bespalov');
	$pdf->SetTitle('Lead4CRM Invoice');
	$pdf->SetSubject('Invoice');
	$pdf->SetKeywords('lead4crm, invoice');

	$pdf->setPrintHeader(false);
	$pdf->setPrintFooter(false);

	$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
	$pdf->SetMargins(18, 10, 32, true);
	$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
	$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

	$pdf->SetFont('arial', '', 9);
	$pdf->AddPage();

	$user_sum = str_replace(',', '.', $userSumm);
	$user_sum = str_replace(' ', '', $user_sum);
	$sum = number_format($user_sum, 2, '-', ' ');
	$sum_alt = number_format($user_sum, 2, '.', '\'');
	$invoice_num = date('ymdHis').rand(0,9);
	$html = $twig->render('invoice.twig', array(
		'invoice_number' => 'L4CRM-'.writeInvoice($invoice_num, $user_sum),
		'invoice_date' => russian_date().' г.',
		'client_company' => setUserCompany($userCompany),
		'userid' => $_SESSION['userid'],
		'price' => $sum,
		'summ' => $sum,
		'summ_alt' => $sum_alt,
		'total' => $sum,
		'summ_text' => mb_ucfirst(num2str($user_sum))
		));
	$pdf->writeHTML($html, true, 0, true, 0);
	$pdf->Image(K_PATH_IMAGES . 'print_trans.png', 21, 140, 40, '', '', '', '', false);
	$pdf->Image(K_PATH_IMAGES . 'sign_trans.png', 50, 124, 60, '', '', '', '', false);
	
	$pdf->SetLineStyle(array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 4, 'color' => array(255,0,0)));
	$pdf->SetFillColor(255,255,128);
	$pdf->SetTextColor(0,0,128);
	$text = 'ВНИМАНИЕ! После оплаты отправьте платежное поручение по адресу: support@lead4crm.ru';
	$pdf->Ln(30);
	$pdf->Cell(0, 10, $text, 1, 1, 'L', 1, 0);

	$pdf->lastPage();
	// $pdf->Output(__DIR__."/public/invoices/Invoice_L4CRM-{$invoice_num}.pdf", 'FD');
	$pdf->Output("Invoice_L4CRM-{$invoice_num}.pdf", 'D');
}

function writeInvoice($num, $sum, $system = 'bank') {
	global $conf;
	if ($conf->db->type == 'postgres') {
		$db = pg_connect('host='.$conf->db->host.' dbname='.$conf->db->database.' user='.$conf->db->username.' password='.$conf->db->password) or die('Невозможно подключиться к БД: '.pg_last_error());
		$query = "insert into invoices (invoice, uid, sum, system) values ({$num}, {$_SESSION['userid']}, '{$sum}', '{$system}')";
		pg_query($query);
		pg_close($db);
	}
	return $num;
}

function setUserCompany($company) {
	global $conf;
	if ($company != $_SESSION['company']) {
		if ($conf->db->type == 'postgres') {
			$db = pg_connect('host='.$conf->db->host.' dbname='.$conf->db->database.' user='.$conf->db->username.' password='.$conf->db->password) or die('Невозможно подключиться к БД: '.pg_last_error());
			$company = pg_escape_string($company);
			$query = "update users set company = '{$company}' where id = {$_SESSION['userid']}";
			pg_query($query);
			pg_close($db);
			$_SESSION['company'] = $company;
		}
	}
	return $company;
}

function yandexPayments($cmd) {
	global $conf;
	
	$performedDatetime = date(DATE_W3C);

	$message = 'Что-то пошло не так!';
	$techMessage = 'Вернитесь назад и попробуйте снова. Возможно на этапе проведения платежа потерялось часть данных.';
	
	$shopId = $conf->payments->ShopID;
	$shopPassword = $conf->payments->ShopPassword;

	$yaAction = $_POST['action'];
	$yaOrderSumAmount = $_POST['orderSumAmount'];
	$yaOrderSumCurrencyPaycash = $_POST['orderSumCurrencyPaycash'];
	$yaOrderSumBankPaycash = $_POST['orderSumBankPaycash'];
	$yaShopId = $_POST['shopId'];
	$yaInvoiceId = $_POST['invoiceId'];
	$yaCustomerNumber = $_POST['customerNumber'];
	$yaMD5 = $_POST['md5'];
	$yaPaymentType = $_POST['paymentType'];

	switch ($yaPaymentType) {
		case 'PC':
			$client = 'Яндекс.Деньги: Счет № ';
			break;
		case 'AC':
			$client = 'Банковская карта: Счет № ';
			break;
		case 'MC':
			$client = 'Мобильный телефон: Счет № ';
			break;
		case 'GP':
			$client = 'Наличные: Счет № ';
			break;
		case 'WM':
			$client = 'WebMoney: Счет № ';
			break;
		case 'SB':
			$client = 'Сбербанк: Счет № ';
			break;
		case 'AB':
			$client = 'Альфа-Клик: Счет № ';
			break;
		case 'МА':
			$client = 'MasterPass: Счет № ';
			break;
		case 'PB':
			$client = 'Промсвязьбанк: Счет № ';
			break;
	}

	header('Content-Type: application/xml');
	$response = '<?xml version="1.0" encoding="UTF-8"?>'."\n";

	if ($cmd == 'check')
	{
		$checkOrderStr = array(
			$yaAction,
			$yaOrderSumAmount,
			$yaOrderSumCurrencyPaycash,
			$yaOrderSumBankPaycash,
			$shopId,
			$yaInvoiceId,
			$yaCustomerNumber,
			$shopPassword);
		$md5 = strtoupper(md5(implode(';', $checkOrderStr)));

		if ($md5 != $yaMD5) {
			$code = '100';
		} else {
			$code = '0';
			if ($conf->db->type == 'postgres') {
				$db = pg_connect('host='.$conf->db->host.' dbname='.$conf->db->database.' user='.$conf->db->username.' password='.$conf->db->password) or die('Невозможно подключиться к БД: '.pg_last_error());
				$system = 'yamoney:'.$_POST['paymentType'];
				$query = "insert into invoices (invoice, uid, sum, system) values ({$yaInvoiceId}, {$yaCustomerNumber}, {$yaOrderSumAmount}, '{$system}')";
				$result = pg_query($query);
				$iid = pg_fetch_result($result, 0, 'id');
				pg_free_result($result);
				$query = "insert into log (uid, debet, client, invoice) values ({$yaInvoiceId}, {$yaOrderSumAmount}, '{$client}', {$iid})";
				pg_query($query);
				pg_close($db);
			}
		}

		if ($code) {
			$error_msg = "message=\"{$message}\" techMessage=\"{$techMessage}\"";
		}

		$response .= "<checkOrderResponse performedDatetime=\"{$performedDatetime}\" code=\"{$code}\" invoiceId=\"{$yaInvoiceId}\" shopId=\"{$yaShopId}\" {$error_msg} />";
	} 
	elseif ($cmd == 'aviso') 
	{
		if ($conf->db->type == 'postgres') {
			$db = pg_connect('host='.$conf->db->host.' dbname='.$conf->db->database.' user='.$conf->db->username.' password='.$conf->db->password) or die('Невозможно подключиться к БД: '.pg_last_error());
			$query = "select id, uid, invoice, sum from invoices where uid = {$yaCustomerNumber} and invoice = {$yaInvoiceId} and sum = {$yaOrderSumAmount}";
			$result = pg_query($query);
			$iid = pg_fetch_result($result, 0, 'id');
			$uid = pg_fetch_result($result, 0, 'uid');
			$invoice = pg_fetch_result($result, 0, 'invoice');
			$sum = pg_fetch_result($result, 0, 'sum');
			pg_free_result($result);
			if ($iid) {
				$checkOrderStr = array(
					$yaAction,
					number_format($sum, 2, '.', ''),
					$yaOrderSumCurrencyPaycash,
					$yaOrderSumBankPaycash,
					$shopId,
					$invoice,
					$uid,
					$shopPassword);
				$md5 = strtoupper(md5(implode(';', $checkOrderStr)));
				if ($md5 != $yaMD5) {
					$code = '1';
				} else {
					$code = '0';
					$query = "insert into log (uid, debet, client, invoice) values ({$uid}, {$sum}, '{$client}', {$iid})";
					pg_query($query);
				}
			} else {
				$code = '200';
			}
			pg_close($db);
		}
		$response .= "<paymentAvisoResponse performedDatetime=\"{$performedDatetime}\" code=\"{$code}\" invoiceId=\"{$yaInvoiceId}\" shopId=\"{$yaShopId}\"/>";
	}
	echo $response;
	exit();
}

function getUserTariffList() {
	global $conf;
	if ($conf->db->type == 'postgres') {
		$db = pg_connect('host='.$conf->db->host.' dbname='.$conf->db->database.' user='.$conf->db->username.' password='.$conf->db->password) or die('Невозможно подключиться к БД: '.pg_last_error());
		$query = "select * from tariff where domain = 'lead4crm.ru' and sum <= (select (sum(debet) - sum(credit)) from log where uid = {$_SESSION['userid']}) order by sum asc";
		$result = pg_query($query);
		while ($row = pg_fetch_assoc($result)) {
			$tariffs[$row['id']]['name'] = $row['name'];
			$tariffs[$row['id']]['code'] = $row['code'];
			$tariffs[$row['id']]['price'] = $row['price'];
			$tariffs[$row['id']]['sum'] = number_format($row['sum'], 2, '.', ' ');
		}
		pg_free_result($result);
		pg_close($db);
	}
	return $tariffs;
}

function getUserTariff() {
	global $conf;
	if ($conf->db->type == 'postgres') {
		$db = pg_connect('host='.$conf->db->host.' dbname='.$conf->db->database.' user='.$conf->db->username.' password='.$conf->db->password) or die('Невозможно подключиться к БД: '.pg_last_error());
		$query = "select name from tariff where id = (select tariffid2 from users where id = {$_SESSION['userid']})";
		$result = pg_query($query);
		$tariff = pg_fetch_result($result, 0, 'name');
		pg_free_result($result);
		pg_close($db);
	}
	return $tariff;
}

function setTariff($getTariff) {
	global $conf;
	if ($conf->db->type == 'postgres')
	{
		$db = pg_connect('host='.$conf->db->host.' dbname='.$conf->db->database.' user='.$conf->db->username.' password='.$conf->db->password) or die('Невозможно подключиться к БД: '.pg_last_error());
		$tariff = $_POST['tariff'] ? $_POST['tariff'] : $getTariff;
		if ($tariff != 'demo') {
			$query = "update users set tariffid2 = (select id from tariff where code = '{$tariff}' and domain = 'lead4crm.ru'), qty = qty + (select queries from tariff where code = '{$tariff}' and domain = 'lead4crm.ru') where id = {$_SESSION['userid']} and (select (sum(debet) - sum(credit)) from log where uid = {$_SESSION['userid']}) >= (select sum from tariff where code = '{$tariff}' and domain = 'lead4crm.ru') and (select tariffid2 from users where id = {$_SESSION['userid']}) != (select id from tariff where code = '{$tariff}' and domain = 'lead4crm.ru') returning id";
			$result = pg_query($query);
			$uid = pg_fetch_result($result, 0, 'id');
			pg_free_result($result);
			if ($uid == $_SESSION['userid']) {
				$query = "insert into log (uid, credit, client) values ({$_SESSION['userid']}, (select sum from tariff where code = '{$tariff}' and domain = 'lead4crm.ru'), 'Активания тарифа ' || (select name from tariff where code = '{$tariff}' and domain = 'lead4crm.ru'))";
				pg_query($query);
			}
			pg_close($db);
		}
	}

	if ($getTariff)
		header("location: /cabinet/");
	else
		getUserData();
}

function getRealIpAddr() {
	if ($_SERVER['HTTP_CLIENT_IP'])
		$ipaddress = $_SERVER['HTTP_CLIENT_IP'];
	else if ($_SERVER['HTTP_X_FORWARDED_FOR'])
		$ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
	else if ($_SERVER['HTTP_X_FORWARDED'])
		$ipaddress = $_SERVER['HTTP_X_FORWARDED'];
	else if ($_SERVER['HTTP_FORWARDED_FOR'])
		$ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
	else if ($_SERVER['HTTP_FORWARDED'])
		$ipaddress = $_SERVER['HTTP_FORWARDED'];
	else if ($_SERVER['REMOTE_ADDR'])
		$ipaddress = $_SERVER['REMOTE_ADDR'];
	else
		$ipaddress = '77.88.8.8';
	return $ipaddress;
}

function russian_date() {
	$date = explode('.',date('d.m.Y'));
	switch ($date[1]) {
		case 1: $m = 'января'; break;
		case 2: $m = 'февраля'; break;
		case 3: $m = 'марта'; break;
		case 4: $m = 'апреля'; break;
		case 5: $m = 'мая'; break;
		case 6: $m = 'июня'; break;
		case 7: $m = 'июля'; break;
		case 8: $m = 'августа'; break;
		case 9: $m = 'сентября'; break;
		case 10: $m = 'октября'; break;
		case 11: $m = 'ноября'; break;
		case 12: $m = 'декабря'; break;
	}
	return $date[0].' '.$m.' '.$date[2];
}

function num2str($num) {
    $nul='ноль';
    $ten=array(
        array('','один','два','три','четыре','пять','шесть','семь', 'восемь','девять'),
        array('','одна','две','три','четыре','пять','шесть','семь', 'восемь','девять'),
    );
    $a20=array('десять','одиннадцать','двенадцать','тринадцать','четырнадцать' ,'пятнадцать','шестнадцать','семнадцать','восемнадцать','девятнадцать');
    $tens=array(2=>'двадцать','тридцать','сорок','пятьдесят','шестьдесят','семьдесят' ,'восемьдесят','девяносто');
    $hundred=array('','сто','двести','триста','четыреста','пятьсот','шестьсот', 'семьсот','восемьсот','девятьсот');
    $unit=array( // Units
        array('копейка' ,'копейки' ,'копеек',	 1),
        array('рубль'   ,'рубля'   ,'рублей'    ,0),
        array('тысяча'  ,'тысячи'  ,'тысяч'     ,1),
        array('миллион' ,'миллиона','миллионов' ,0),
        array('миллиард','милиарда','миллиардов',0),
    );
    //
    list($rub,$kop) = explode('.',sprintf("%015.2f", floatval($num)));
    $out = array();
    if (intval($rub)>0) {
        foreach(str_split($rub,3) as $uk=>$v) { // by 3 symbols
            if (!intval($v)) continue;
            $uk = sizeof($unit)-$uk-1; // unit key
            $gender = $unit[$uk][3];
            list($i1,$i2,$i3) = array_map('intval',str_split($v,1));
            // mega-logic
            $out[] = $hundred[$i1]; # 1xx-9xx
            if ($i2>1) $out[]= $tens[$i2].' '.$ten[$gender][$i3]; # 20-99
            else $out[]= $i2>0 ? $a20[$i3] : $ten[$gender][$i3]; # 10-19 | 1-9
            // units without rub & kop
            if ($uk>1) $out[]= morph($v,$unit[$uk][0],$unit[$uk][1],$unit[$uk][2]);
        } //foreach
    }
    else $out[] = $nul;
    $out[] = morph(intval($rub), $unit[1][0],$unit[1][1],$unit[1][2]); // rub
    $out[] = $kop.' '.morph($kop,$unit[0][0],$unit[0][1],$unit[0][2]); // kop
    return trim(preg_replace('/ {2,}/', ' ', join(' ',$out)));
}

function morph($n, $f1, $f2, $f5) {
    $n = abs(intval($n)) % 100;
    if ($n>10 && $n<20) return $f5;
    $n = $n % 10;
    if ($n>1 && $n<5) return $f2;
    if ($n==1) return $f1;
    return $f5;
}

function mb_ucfirst($str, $encoding='UTF-8') {
   $str = mb_ereg_replace('^[\ ]+', '', $str);
   $str = mb_strtoupper(mb_substr($str, 0, 1, $encoding), $encoding).
          mb_substr($str, 1, mb_strlen($str), $encoding);
   return $str;
}

function isAuth($cmd) {
	if (!$_SESSION['userid']) {
		global $twig;
		$cmd = implode('/', $cmd);
		$options = array(
			'title' => '403 Доступ запрещен',
			'currentUrl' => 'https://' . $_SERVER['SERVER_NAME'] . '/' . $cmd . '/');
		$options = array_merge($options, arrayOAuthLoginURL(), arrayMenuUrl());
		header('HTTP/1.0 403 Forbidden');
		echo $twig->render('403.twig', $options);
		exit(3);
	}
}

function logout() {
	session_destroy();
	header("Location: /");
}
?>