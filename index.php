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

$api = new Lead4CRM\API($conf);
$loader = new Twig_Loader_Filesystem(__DIR__.'/views');
$twig = new Twig_Environment($loader, array(
	'cache' => __DIR__.'/cache',
	'auto_reload' => true,
	'optimizations' => -1
));
// $twig->addExtension(new \Salva\JshrinkBundle\Twig\Extension\JshrinkExtension);
$telegram = new Longman\TelegramBot\Telegram($conf->telegram->api, $conf->telegram->name);
$wa = new WhatsProt($conf->wa->login, 0, 'Lead4CRM', true);
// $wa->connect();
// $wa->loginWithPassword($conf->wa->password);
// $wa->sendGetPrivacyBlockedList();

$requestURI = explode('/',$_SERVER['REQUEST_URI']);
$scriptName = explode('/',$_SERVER['SCRIPT_NAME']);
for ($i=0;$i<sizeof($scriptName);$i++) {
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
            header("Content-Type: application/json");
			echo $api->getWebCall($_POST['phone'], $_POST['delay']);
			break;

		case 'contract':
			isAuth($cmd);
			echo $api->getContract($_POST['decision'], $_SESSION['userid']);
			break;

		case 'step-2':
            isAuth($cmd);
            header("Content-Type: application/json");
            echo json_encode($api->wizard($_POST['crm_id'], 2), JSON_UNESCAPED_UNICODE);
            break;

        case 'getIntegrated':
			isAuth($cmd);
            header('Content-Type: application/json');
            $opt = $api->getIntegrated($_REQUEST['ii'], $_SESSION['userid']);
            if ($opt['error'] == 0) {
                $html = $twig->render('crm/'.$_REQUEST['ii'].'.twig', $opt);
                echo json_encode(array('html' => $html, 'connected' => $opt['connected']), JSON_UNESCAPED_UNICODE);
            }
			break;

		case 'crmConnect':
			isAuth($cmd);
			echo $api->crmConnect($cmd[1]);
			break;

		case 'crmPostCompany':
			isAuth($cmd);
            header('Content-Type: application/json');
			echo $api->crmPostCompany($cmd[1], $_SESSION['userid'], $_REQUEST['opt']);
			break;

		case 'crmSaveSettings':
			isAuth($cmd);
			$api->crmSaveSettings($cmd[1], $_SESSION['userid']);
			break;

		case 'crmDisconnect':
			isAuth($cmd);
			$api->crmDisconnect($cmd[1], $_SESSION['userid']);
			break;

		/*case 'initTelegram':
			isAdmin($cmd);
			try {
				$link = 'https://'.$_SERVER['SERVER_NAME'].'/telegram/';
				$result = $telegram->setWebHook($link);
				if ($result->isOk()) {
			        echo $result->getDescription();
			    }
			} catch (Longman\TelegramBot\Exception\TelegramException $e) {
				echo $e;
			}
			break;*/

		case 'telegram':
			try {
				$credentials = array(
					'schema' => 'pgsql',
					'host' => $conf->db->host,
					'user' => $conf->db->username,
					'password' => $conf->db->password,
					'database' => $conf->db->database
				);
				$telegram->enablePDO($credentials);
				$telegram->handle();
			} catch (Longman\TelegramBot\Exception\TelegramException $e) {
				echo $e;
			}
			break;

		case 'icq':
			header('Content-Type: text/plain');
			$api->sendICQ($cmd[1], $_REQUEST['uin']);
			break;

		case 'wafirst':
			$wa->sendGetClientConfig();
			$wa->sendGetServerProperties();
			$wa->sendGetGroups();
			$wa->sendGetBroadcastLists();
			$wa->sendSync('79041326000');
			$wa->sendPresenceSubscription('79041326000');
			$wa->sendGetStatuses('79041326000');
			$wa->sendGetProfilePicture('79041326000');
			$wa->sendPing();
            break;

		case 'wa':
			$wa->sendMessageComposing('79041326000');
			$wa->sendMessagePaused('79041326000');
			$wa->sendMessage('79041326000', 'Hi! :) this is a test message');
			break;

		case 'sms':
			header('Content-Type: text/json');
            echo json_encode($api->sendSMS($cmd[1], $_REQUEST['phone'], $_SESSION['uin']), JSON_UNESCAPED_UNICODE);
			break;

		case 'email':
			isAuth($cmd);
			header('Content-Type: text/plain');
            echo $api->sendEmail($cmd[1], $_REQUEST['email'], $_SESSION['userid']);
            if ($cmd[1] == 'confirm') header("Location: /cabinet/");
			break;

		case 'vcard':
			header('Content-Type: text/x-vcard');
			header('Content-Disposition: attachment; filename=lead4crm.vcf');
            echo $api::getVCard();
			break;

		case 'getSupportCities':
            header('Content-Type: application/json');
			echo $api->getSupportCities();
			break;

		case 'getUserData':
			isAuth($cmd);
            $api->getUserDataByUID($_SESSION['userid'], true);
			break;

		case 'getUserCache':
			isAuth($cmd);
            header("Content-Type: application/json");
			echo json_encode($api->getUserCache($_SESSION['userid']), JSON_UNESCAPED_UNICODE);
			break;

		case 'getSelection':
			isAuth($cmd);
            $api->getSelection($cmd[1], $_REQUEST['crm_id'], $_SESSION['userid']);
			break;

		case 'getSelectionArray':
			isAuth($cmd);
			$api->getSelectionArray($cmd[1], $_REQUEST['crm_id'], $_SESSION['userid'], $_REQUEST['json'], $_REQUEST['addon']);
			break;

		case 'checkAPIKey':
            header("Content-Type: application/json");
            echo json_encode($api->checkAPIKey($_REQUEST['apikey']), JSON_UNESCAPED_UNICODE);
			break;

		case 'getAmoUserData':
		case 'getB24UserData':
            header("Content-Type: application/json");
            echo json_encode($api->getUserDataByAPI($_REQUEST['apikey']), JSON_UNESCAPED_UNICODE);
			break;

		case 'getDataSearch':
            header("Content-Type: application/json");
			echo $api->getDataSearchText(
				$_REQUEST['searchAPI'], 
				$_REQUEST['searchText'], 
				$_REQUEST['searchCity'],
				$_REQUEST['searchDomain'],
				$_REQUEST['searchPage']);
			break;

		case 'getDataSearchRubric':
            header("Content-Type: application/json");
			echo $api->getDataSearchRubric(
				$_REQUEST['searchAPI'],
				$_REQUEST['searchRubric'],
				$_REQUEST['searchCity'],
				$_REQUEST['searchDomain'],
				$_REQUEST['searchPage']);
			break;

		case 'getRubricList':
            header("Content-Type: application/json");
			echo $api->getRubricList(
				$_REQUEST['importAPI'],
				$_REQUEST['importDomain'],
				true);
			break;

		case 'importRubrics':
            header("Content-Type: application/json");
			echo $api->getRubricList(
				$_REQUEST['importAPI'],
				$_REQUEST['importDomain']);
			break;

		case 'importCompany':
            header("Content-Type: application/json");
			echo $api->getCompanyProfile(
				$_REQUEST['importAPI'],
				$_REQUEST['importDomain'],
				$_REQUEST['importCompanyID'],
				$_REQUEST['importCompanyHash'],
				$_REQUEST['assignedUserId'],
				$api::getRealIpAddr(),
				$_REQUEST['getFrom2GIS']);
			break;

		case 'newAPIKey':
			isAuth($cmd);
			$api->getNewAPIKey($_SESSION['userid']);
			break;

		case 'getInvoice':
			isAuth($cmd);
			$api->getInvoice($_POST['invoicesum'], $_POST['companyname'], $_SESSION['userid']);
			break;

		case 'payment':
			header('Content-Type: application/xml');
            echo $api->getPayment($cmd[1]);
			break;

		case 'setTariff':
			isAuth($cmd);
            $tariff = $_POST['tariff'] ? $_POST['tariff'] : $cmd[1];
            $api->setUserTariff($tariff, $_SESSION['userid']);
            if ($cmd[1])
			    header("Location: /cabinet/");
            else
                $api->getUserDataByUID($_SESSION['userid'], true);
			break;

		case $cmd[0]:
			switch ($cmd[0]) {
				case 'about-project':
                    $title = 'О проекте';
                    break;

				case 'about-us':
                    $title = 'О нас';
                    break;

				case 'price':
                    $title = 'Цены';
                    break;

				case 'support':
                    $title = 'Поддержка';
                    break;

				case 'terms':
                    $title = 'Публичный договор-оферта';
                    break;

				case 'subscribe-confirm':
                    $title = 'Подтверждение подписки';
                    break;

				case 'subscribe':
                    $title = 'Спасибо!';
                    break;

				case 'cabinet':
                    isAuth($cmd);
                    $title = 'Личный кабинет';
                    $top_rubrics = $api->getRubricList($_SESSION['apikey'], 'www.lead4crm.ru');
                    $top_rubrics = json_decode($top_rubrics, true);
                    $cOptions = array(
                        'apikey' => $_SESSION['apikey'],
                        'company' => $_SESSION['company'],
                        'provider' => $_SESSION['provider'],
                        'userid' => $_SESSION['userid'],
                        'contract' => $_SESSION['contract'],
                        'admin' => $_SESSION['is_admin'],
                        'telegram' => $_SESSION['telegram'],
                        'icq' => $_SESSION['icq'],
                        'sms' => $_SESSION['sms'],
                        'email' => $_SESSION['email'],
                        'notify_danger' => (!isset($_SESSION['telegram']) || !isset($_SESSION['icq']) || !isset($_SESSION['sms']) || !isset($_SESSION['email'])) ? true : false,
                        'crm_list' => $api->getCRM(),
                        'countries' => $api->getCountries($api::getUserCityByIP()),
                        'top_rubrics' => $top_rubrics,
                        'links' => $api->getOAuthLoginURL(),
                        'yaShopId' => $conf->payments->ShopID,
                        'yaSCId' => $conf->payments->SCID,
                        'tariffs' => $api->getUserTariffList($_SESSION['userid']));
                    break;

                case 'amo-index':
                    if ($apikey = $_REQUEST['apikey'] &&
                        $login = $_REQUEST['login'] &&
                            $hash = $_REQUEST['hash'] &&
                                $subdomain = $_REQUEST['subdomain']
                    ) {
                        $cOptions = array(
                            'isAMOUser' => true,
                            'request' => $_REQUEST,
                        );
                    }
                    break;

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
                            'countries' => $api->getCountries($arRes['result']['PERSONAL_CITY']),
                            'userData' => $api->getUserDataByUID($_SESSION['userid']),
                        );
                    }
                    break;

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
                            'countries' => $api->getCountries($arRes['result']['PERSONAL_CITY']),
                            'userData' => $api->getUserDataByUID($_SESSION['userid']),
                        );
                    }
                    break;

				case 'login':
                    $api->getDataLogin($cmd[1]);
                    break;

				default:
                    $title = '404 - Страница не найдена';
			}
			$options = array(
				'title' => $title,
				'userid' => $_SESSION['userid'],
				'currentUrl' => 'https://' . $_SERVER['SERVER_NAME'] . '/' . $cmd[0] . '/',
            );
			$options = array_merge($options, $api->getOAuthLoginURL(), $api->getMenuUrl());

            print_r($options);
			
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
		'title' => 'Базы 2ГИС для CRM: Битрикс24, Мегаплан',
		'userid' => $_SESSION['userid'],
		'currentUrl' => 'https://' . $_SERVER['SERVER_NAME']);
	$options = array_merge($options, $api->getOAuthLoginURL(), $api->getMenuUrl());
	echo $twig->render('index.twig', $options);
}

function isAdmin($cmd) {
	if (!$_SESSION['is_admin']) {
		global $twig, $api;
		$cmd = implode('/', $cmd);
		$options = array(
			'title' => '403 Доступ запрещен',
			'currentUrl' => 'https://' . $_SERVER['SERVER_NAME'] . '/' . $cmd . '/');
		$options = array_merge($options, $api->getOAuthLoginURL(), $api->getMenuUrl());
		header('HTTP/1.0 403 Forbidden');
		echo $twig->render('403.twig', $options);
		exit(5);
	}
}

function isAuth($cmd) {
	if (!$_SESSION['userid']) {
		global $twig, $api;
		ini_set('browscap', __DIR__.'/browscap.ini');
		$cmd = implode('/', $cmd);
		$browser = get_browser(null, true);
		$options = array(
			'title' => '401 Требуется авторизация',
			'currentUrl' => 'https://' . $_SERVER['SERVER_NAME'] . '/' . $cmd . '/',
			'browser' => $browser['browser']);
		$options = array_merge($options, $api->getOAuthLoginURL(), $api->getMenuUrl());
		header('HTTP/1.0 401 Unauthorized');
		echo $twig->render('401.twig', $options);
		exit(3);
	}
}

function logout() {
	session_destroy();
	header("Location: /");
}
