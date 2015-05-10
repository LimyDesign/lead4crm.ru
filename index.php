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

switch ($cmd[0]) {
	case 'about-project':
	case 'about-us':
	case 'price':
	case 'support':
		$options = array(
			'title' => 'Поддержка',
			'currentUrl' => 'http://' . $_SERVER['HTTP_HOST'] . '/' . $cmd[0] . '/');
		$options = array_merge($options, arrayOAuthLoginURL(), arrayMenuUrl());
		echo $twig->render($cmd[0].'.twig', $options);
		break;

	case 'cabinet':
		break;
	
	default:
		$options = array(
			'title' => 'Генератор лидов для Битрикс 24', 
			'currentUrl' => 'http://' . $_SERVER['HTTP_HOST']);
		$options = array_merge($options, arrayOAuthLoginURL(), arrayMenuUrl());
		echo $twig->render('index.twig', $options);
		break;
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
?>