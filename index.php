<?php
error_reporting(E_ALL & ~E_NOTICE);
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

if (isset($_REQUEST['clear_cache']))
    $auto_reload = true;
else
    $auto_reload = false;

$api = new Lead4CRM\API($conf);
$loader = new Twig_Loader_Filesystem(__DIR__.'/views');
$twig = new Twig_Environment($loader, array(
	'cache' => __DIR__.'/cache',
	'auto_reload' => $auto_reload,
	'optimizations' => -1
));
// $twig->addExtension(new \Salva\JshrinkBundle\Twig\Extension\JshrinkExtension);
$telegram = new Longman\TelegramBot\Telegram($conf->telegram->api, $conf->telegram->name);
$wa = new WhatsProt($conf->wa->login, 'Lead4CRM', true);
// $wa->connect();
// $wa->loginWithPassword($conf->wa->password);
// $wa->sendGetPrivacyBlockedList();

$requestURI = explode('/',$_SERVER['REQUEST_URI']);
$scriptName = explode('/',$_SERVER['SCRIPT_NAME']);
for ($i = 0; $i < count($scriptName); $i++) {
	if ($requestURI[$i] == $scriptName[$i])
		unset($requestURI[$i]);
}
foreach ($requestURI as $key => $uri) {
    if ($uri == '' || preg_match('@^\?@i', $uri))
        unset($requestURI[$key]);
}
$cmd = array_values($requestURI);

if ($cmd[0]) {
    if ($cmd[0] == 'logout') {
        logout();
        exit;
    } elseif ($cmd[0] == 'login') {
        $api->getDataLogin($cmd[1]);
        exit;
    } elseif ($cmd[0] == 'getcountry') {
        isAdmin($cmd);
        header("Content-Type: text/plain");
        print_r($api->getCountries('Иркутск'));
        exit;
    } elseif ($cmd[0] == 'webcall') {
        header("Content-Type: application/json");
        echo $api->getWebCall($_POST['phone'], $_POST['delay']);
        exit;
    } elseif ($cmd[0] == 'contract') {
        isAuth($cmd);
        echo $api->getContract($_POST['decision'], $_SESSION['userid']);
        exit;
    } elseif ($cmd[0] == 'step-2') {
        isAuth($cmd);
        header("Content-Type: application/json");
        echo json_encode($api->wizard($_POST['crm_id'], 2), JSON_UNESCAPED_UNICODE);
        exit;
    } elseif ($cmd[0] == 'getIntegrated') {
        isAuth($cmd);
        header('Content-Type: application/json');
        $opt = $api->getIntegrated($_REQUEST['ii'], $_SESSION['userid']);
        if ($opt['error'] == 0) {
            $html = $twig->render('crm/'.$_REQUEST['ii'].'.twig', $opt);
            echo json_encode(array('html' => $html, 'connected' => $opt['connected']), JSON_UNESCAPED_UNICODE);
        }
        exit;
    } elseif ($cmd[0] == 'crmConnect') {
        isAuth($cmd);
        echo $api->crmConnect($cmd[1]);
        exit;
    } elseif ($cmd[0] == 'crmPostCompany') {
        isAuth($cmd);
        header('Content-Type: application/json');
        echo $api->crmPostCompany($cmd[1], $_SESSION['userid'], $_REQUEST['opt']);
        exit;
    } elseif ($cmd[0] == 'crmSaveSettings') {
        isAuth($cmd);
        $api->crmSaveSettings($cmd[1], $_SESSION['userid']);
        exit;
    } elseif ($cmd[0] == 'crmDisconnect') {
        isAuth($cmd);
        $api->crmDisconnect($cmd[1], $_SESSION['userid']);
        exit;
    } elseif ($cmd[0] == 'initTelegram') {
        isAdmin($cmd);
        try {
            $link = 'https://'.$_SERVER['SERVER_NAME'].'/telegram/';
            echo $telegram->setWebHook($link);
        } catch (Longman\TelegramBot\Exception\TelegramException $e) {
            echo $e;
        }
        exit;
    } elseif ($cmd[0] == 'telegram') {
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
        exit;
    } elseif ($cmd[0] == 'icq') {
        header('Content-Type: text/plain');
        $api->sendICQ($cmd[1], $_REQUEST['uin']);
        exit;
    } elseif ($cmd[0] == 'wafirst') {
        $wa->sendGetClientConfig();
        $wa->sendGetServerProperties();
        $wa->sendGetGroups();
        $wa->sendGetBroadcastLists();
        $wa->sendSync('79041326000');
        $wa->sendPresenceSubscription('79041326000');
        $wa->sendGetStatuses('79041326000');
        $wa->sendGetProfilePicture('79041326000');
        $wa->sendPing();
        exit;
    } elseif ($cmd[0] == 'wa') {
        $wa->sendMessageComposing('79041326000');
        $wa->sendMessagePaused('79041326000');
        $wa->sendMessage('79041326000', 'Hi! :) this is a test message');
        exit;
    } elseif ($cmd[0] == 'sms') {
        header('Content-Type: text/json');
        echo json_encode($api->sendSMS($cmd[1], $_REQUEST['phone'], $_SESSION['uin']), JSON_UNESCAPED_UNICODE);
        exit;
    } elseif ($cmd[0] == 'email') {
        isAuth($cmd);
        header('Content-Type: text/plain');
        echo $api->sendEmail($cmd[1], $_REQUEST['email'], $_SESSION['userid']);
        if ($cmd[1] == 'confirm') header("Location: /cabinet/");
        exit;
    } elseif ($cmd[0] == 'vcard') {
        header('Content-Type: text/x-vcard');
        header('Content-Disposition: attachment; filename=lead4crm.vcf');
        echo $api::getVCard();
        exit;
    } elseif ($cmd[0] == 'getSupportCities') {
        header('Content-Type: application/json');
        echo $api->getSupportCities();
        exit;
    } elseif ($cmd[0] == 'getUserData') {
        isAuth($cmd);
        $api->getUserDataByUID($_SESSION['userid'], true);
        exit;
    } elseif ($cmd[0] == 'getUserCache') {
        isAuth($cmd);
        header("Content-Type: application/json");
        echo json_encode($api->getUserCache($_SESSION['userid']), JSON_UNESCAPED_UNICODE);
        exit;
    } elseif ($cmd[0] == 'getSelection') {
        isAuth($cmd);
        $api->getSelection($cmd[1], $_REQUEST['crm_id'], $_SESSION['userid']);
        exit;
    } elseif ($cmd[0] == 'getSelectionArray') {
        isAuth($cmd);
        $api->getSelectionArray($cmd[1], $_REQUEST['crm_id'], $_SESSION['userid'], $_REQUEST['json'], $_REQUEST['addon']);
        exit;
    } elseif ($cmd[0] == 'checkAPIKey') {
        header("Content-Type: application/json");
        echo json_encode($api->checkAPIKey($_REQUEST['apikey']), JSON_UNESCAPED_UNICODE);
        exit;
    } elseif ($cmd[0] == 'getAmoUserData' || $cmd[0] == 'getB24UserData') {
        header("Content-Type: application/json");
        echo json_encode($api->getUserDataByAPI($_REQUEST['apikey']), JSON_UNESCAPED_UNICODE);
        exit;
    } elseif ($cmd[0] == 'getDataSearch') {
        header("Content-Type: application/json");
        echo $api->getDataSearchText($_REQUEST['searchAPI'], $_REQUEST['searchText'], $_REQUEST['searchCity'], $_REQUEST['searchDomain'], $_REQUEST['searchPage']);
        exit;
    } elseif ($cmd[0] == 'getDataSearchRubric') {
        header("Content-Type: application/json");
        echo $api->getDataSearchRubric($_REQUEST['searchAPI'], $_REQUEST['searchRubric'], $_REQUEST['searchCity'], $_REQUEST['searchDomain'], $_REQUEST['searchPage']);
        exit;
    } elseif ($cmd[0] == 'getRubricList') {
        header("Content-Type: application/json");
        echo $api->getRubricList($_REQUEST['importAPI'], $_REQUEST['importDomain'], true);
        exit;
    } elseif ($cmd[0] == 'importRubrics') {
        header("Content-Type: application/json");
        echo $api->getRubricList($_REQUEST['importAPI'], $_REQUEST['importDomain']);
        exit;
    } elseif ($cmd[0] == 'importCompany') {
        header("Content-Type: application/json");
        echo $api->getCompanyProfile($_REQUEST['importAPI'], $_REQUEST['importDomain'], $_REQUEST['importCompanyID'], $_REQUEST['importCompanyHash'], $_REQUEST['assignedUserId'], $api::getRealIpAddr(), $_REQUEST['getFrom2GIS']);
        exit;
    } elseif ($cmd[0] == 'newAPIKey') {
        isAuth($cmd);
        $api->getNewAPIKey($_SESSION['userid']);
        exit;
    } elseif ($cmd[0] == 'getInvoice') {
        isAuth($cmd);
        $api->getInvoice($_POST['invoicesum'], $_POST['companyname'], $_SESSION['userid']);
        exit;
    } elseif ($cmd[0] == 'payment') {
        header('Content-Type: application/xml');
        echo $api->getPayment($cmd[1]);
        exit;
    } elseif ($cmd[0] == 'setTariff') {
        isAuth($cmd);
        $tariff = $_POST['tariff'] ? $_POST['tariff'] : $cmd[1];
        $api->setUserTariff($tariff, $_SESSION['userid']);
        if ($cmd[1])
            header("Location: /cabinet/");
        else
            $api->getUserDataByUID($_SESSION['userid'], true);
        exit;
    } elseif ($cmd[0] == 'about-project') {
        $title = 'О проекте';
    } elseif ($cmd[0] == 'about-us') {
        $title = 'О нас';
    } elseif ($cmd[0] == 'price') {
        $title = 'Цены';
    } elseif ($cmd[0] == 'support') {
        $title = 'Поддержка';
    } elseif ($cmd[0] == 'terms') {
        $title = 'Публичный договор-оферта';
    } elseif ($cmd[0] == 'subscribe-confirm') {
        $title = 'Подтверждение подписки';
    } elseif ($cmd[0] == 'subscribe') {
        $title = 'Спасибо!';
    } elseif ($cmd[0] == 'cabinet') {
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
            'tariffs' => $api->getUserTariffList($_SESSION['userid']),
        );
    } elseif ($cmd[0] == 'amo-index') {
        if ($apikey = $_REQUEST['apikey'] && $login = $_REQUEST['login'] && $hash = $_REQUEST['hash'] && $subdomain = $_REQUEST['subdomain']) {
            $cOptions = array(
                'isAMOUser' => true,
                'request' => $_REQUEST,
                'countries' => $api->getCountries($api::getUserCityByIP()),
            );
        }
    } elseif ($cmd[0] == 'b24-install' || $cmd[0] == 'b24-index') {
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
    } elseif ($cmd[0] == 'b24-install-dev' || $cmd[0] == 'b24-index-dev') {
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
    } else {
        $title = '404 - Страница не найдена';
    }
    $options = array(
        'title' => $title,
        'userid' => $_SESSION['userid'],
        'currentUrl' => 'https://' . $_SERVER['SERVER_NAME'] . '/' . $cmd[0] . '/',
    );
    $options = array_merge($options, $api->getOAuthLoginURL(), $api->getMenuUrl());

    if (count($cOptions) > 0)
        $options = array_merge($options, $cOptions);

    if (file_exists(__DIR__.'/views/'.$cmd[0].'.twig') && $cmd[0] != '403') {
        $render = $twig->render($cmd[0].'.twig', $options);
    } else {
        header("HTTP/1.0 404 Not Found");
        $render = $twig->render('404.twig', $options);
    }
} else {
	$options = array(
		'title' => 'Базы 2ГИС для CRM: Битрикс24, Мегаплан',
		'userid' => $_SESSION['userid'],
		'currentUrl' => 'https://' . $_SERVER['SERVER_NAME']);
	$options = array_merge($options, $api->getOAuthLoginURL(), $api->getMenuUrl());
	$render = $twig->render('index.twig', $options);
}

echo $render;

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
