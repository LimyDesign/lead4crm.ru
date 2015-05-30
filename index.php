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

		case 'getUserData':
			isAuth();
			getUserData();
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

		case 'importCompany':
			echo importCompany(
				$_REQUEST['importAPI'],
				$_REQUEST['importDomain'],
				$_REQUEST['importCompanyID'],
				$_REQUEST['importCompanyHash']);
			break;

		case 'newAPIKey':
			isAuth();
			newAPIKey();
			break;

		case 'getInvoice':
			isAuth();
			generateInvoice($_POST['invoicesum'], $_POST['companyname']);
			break;

		case 'payment':
			yandexPayments($cmd[1]);
			break;

		case 'setTariff':
			isAuth();
			setTariff($cmd[1]);
			break;

		case 'cabinet':
			isAuth();
			$cOptions = array(
				'apikey' => $_SESSION['apikey'],
				'company' => $_SESSION['company'],
				'provider' => $_SESSION['provider'],
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
					'cities' => getCities($arRes['result']['PERSONAL_CITY']),
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
				'currentUrl' => 'http://' . $_SERVER['SERVER_NAME'] . '/' . $cmd[0] . '/');
			$options = array_merge($options, arrayOAuthLoginURL(), arrayMenuUrl());
			
			if (count($cOptions) > 0)
				$options = array_merge($options, $cOptions);

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
		'currentUrl' => 'http://' . $_SERVER['SERVER_NAME']);
	$options = array_merge($options, arrayOAuthLoginURL(), arrayMenuUrl());
	echo $twig->render('index.twig', $options);
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
		'redirect_uri' => 'http://' . $_SERVER['SERVER_NAME'] . '/login/vkontakte/',
		'response_type' => 'code',
		'v' => '5.29',
		'state' => $state,
		'display' => 'page'));
	$oklogin = http_build_query(array(
		'client_id' => $conf->provider->odnoklassniki->CLIENT_ID,
		'scope' => 'GET_EMAIL',
		'response_type' => 'code',
		'redirect_uri' => 'http://' . $_SERVER['SERVER_NAME'] . '/login/odnoklassniki/',
		'state' => $state));
	$fblogin = http_build_query(array(
		'client_id' => $conf->provider->facebook->CLIENT_ID,
		'scope' => 'email',
		'redirect_uri' => 'http://' . $_SERVER['SERVER_NAME'] . '/login/facebook/',
		'response_type' => 'code'));
	$gplogin = http_build_query(array(
		'client_id' => $conf->provider->{google-plus}->CLIENT_ID,
		'scope' => 'https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/userinfo.profile',
		'redirect_uri' => 'http://' . $_SERVER['SERVER_NAME'] . '/login/google-plus/',
		'response_type' => 'code',
		'state' => $state,
		'access_type' => 'online',
		'approval_prompt' => 'auto',
		'login_hint' => 'email',
		'include_granted_scopes' => 'true'));
	$mrlogin = http_build_query(array(
		'client_id' => $conf->provider->mailru->CLIENT_ID,
		'response_type' => 'code',
		'redirect_uri' => 'http://' . $_SERVER['SERVER_NAME'] . '/login/mailru/'));
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
		'mainpage_url' => 'http://' . $_SERVER['SERVER_NAME'],
		'aboutproject_url' => 'http://' . $_SERVER['SERVER_NAME'] . '/about-project/',
		'aboutours_url' => 'http://' . $_SERVER['SERVER_NAME'] . '/about-us/',
		'prices_url' => 'http://' . $_SERVER['SERVER_NAME'] . '/price/',
		'support_url' => 'http://' . $_SERVER['SERVER_NAME'] . '/support/',
		'cabinet_url' => 'http://' . $_SERVER['SERVER_NAME'] . '/cabinet/'
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
	$redirect_uri = 'http://'.$_SERVER['SERVER_NAME'].'/login/facebook/';
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
	$redirect_uri = 'http://'.$_SERVER['SERVER_NAME'].'/login/vkontakte/';
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
	$redirect_uri = 'http://'.$_SERVER['SERVER_NAME'].'/login/google-plus/';
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
	$redirect_uri = 'http://'.$_SERVER['SERVER_NAME'].'/login/odnoklassniki/';
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
	$redirect_uri = 'http://'.$_SERVER['SERVER_NAME'].'/login/mailru/';
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
	return 'fuuuu';
	// return file_get_contents($url.$uri);
}

function importCompany($apikey, $domain, $id, $hash) {
	$url = "http://api.cnamrf.ru/getCompanyProfile/?";
	$uri = http_build_query(array(
		'apikey' => $apikey,
		'domain' => $domain,
		'id' => $id,
		'hash' => $hash));
	return file_get_contents($url.$uri);
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
		$query = "select * from tariff where domain = 'lead4crm.ru' and sum <= (select (sum(debet) - sum(credit)) from log where uid = {$_SESSION['userid']})";
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

function isAuth() {
	if (!$_SESSION['userid'])
		header("location:javascript://history.go(-1)");
		// header("Location: {$_SERVER['HTTP_REFERER']}/");
}

function logout() {
	session_destroy();
	header("Location: /");
}
?>