<?php
error_reporting(E_ALL);

define('ADMINUIN', '881129');
define('STARTXSTATUS', 'studying');
define('STARTSTATUS', 'STATUS_FREE4CHAT');

require_once __DIR__.'/src/vendor/autoload.php';

$conf = json_decode(file_get_contents(__DIR__.'/config.json'));

$icq = new WebIcqPro();
$icq->debug = true;

pcntl_signal(SIGTERM, 'sig_handler');
register_shutdown_function('shutdown');

$help = "Комманды информатора:\r
\t'!about' - об информаторе\r
\t'!help' - справка по всем командам информатора\r
\t'!connect' - подключение к информатору\r
\t'!notify' - настройка уведомлений информатора\r
\t'!diconnect' - отключение от информатора\r
";

$about = "Lead4CRM Bot v1.0.0\n
\tЯ маленький, но очень шустрый информатор сайта www.lead4crm.ru.\r
\tЯ смогу сообщить вам о том что произошло с вашим балансом,\r
\tо том какая компания была только что импортирована, а также\r
\tпредупредить о грядущем продлении вашего тарифного плана.\r
";

$ignore_list = array();

$message_response = array();

while (1) {
	if ($icq->connect($conf->icq->uin, $conf->icq->password)) {
		$icq->sendMessage(ADMINUIN, 'Service Lead4CRM Bot started...');
		$uptime = $status_time = $xstatus_time = time();
		$icq->setStatus(STARTSTATUS, 'STATUS_DCAUTH', 'Ask me... I\'m Lead4CRM notification bot');
		$icq->setXStatus(STARTXSTATUS, 'Ask me... I\'m Lead4CRM notification bot');
		$xstatus = STARTXSTATUS;
		$status = STARTSTATUS;
	}
	else {
		echo 'Connect failed! Next try in '.date('d.m.Y h:i:s', strtotime('+20 minutes')).'!'.PHP_EOL;
		if (!empty($icq->error)) {
			echo $icq->error.PHP_EOL;
			$icq->error = '';
		}
		time_sleep_until(strtotime('+20 minutes'));
		continue;
	}

	$msg_old = array();

	while ($icq->isConnected()) {
		if (!empty($icq->error)) {
			echo $icq->error.PHP_EOL;
			$icq->error = '';
		}
		$msg = $icq->readMessage();
		if (is_array($msg) && $msg !== $msg_old) {
			if (isset($msg['encoding']) && is_array($msg['encoding'])) {
				if ($msg['encoding']['numset'] === 'UNICODE') {
					$msg['realmessage'] = $msg['message'];
					$msg['message'] = mb_convert_encoding($msg['message'], 'cp1251', 'UTF-16');
				}
				if ($msg['encoding']['numset'] === 'UTF-8') {
					$msg['realmessage'] = $msg['message'];
					$msg['message'] = mb_convert_encoding($msg['message'], 'cp1251', 'UTF-8');
				}
			}

			$msg_old = $msg;
			if (isset($msg['type']) && 
				$msg['type'] == 'message' && 
				isset($msg['from']) &&
				isset($msg['message']) && 
				!empty($msg['message']) && 
				!in_array($msg['from'], $ignore_list)
			) {
				// $icq->sendMessage(ADMINUIN, $msg['from'].'>'.trim($msg['message']));
				switch (strtolower(trim($msg['message']))) {
					case '!about':
						$icq->sendMessage($msg['from'], mb_convert_encoding($about, 'cp1251'));
						break;
					case '!help':
						$icq->sendMessage($msg['from'], $help);
						break;
					case '!stop':
					case '!exit':
						if ($msg['from'] == ADMINUIN) {
							exit;
						}
						else {
							$icq->sendMessage($msg['from'], 'The system is going down for reboot NOW!');
						}
						break;
					default:
						$command = explode(' ', $msg['message']);
						if (count($command) > 1) {
							switch($command[0]) {
								default:
									var_dump($msg);
									$icq->sendMessage($msg['from'], "Введите '!help' для получния справки по командам.");
									break;
							}
						}
						else {
							var_dump($msg);
							$icq->sendMessage($msg['from'], "Введите '!help' для получния справки по командам.");
						}
						break;
				}
			}
			elseif (isset($msg['id']) && isset($message_response[$msg['id']])) {
				if (isset($msg['type'])) {
					switch ($msg['type']) {
						case 'shortinfo':
							$message = 'Nick: '.$msg['nick'].PHP_EOL;
							$message.= 'First Name: '.$msg['firstname'].PHP_EOL;
							$message.= 'Last Name: '.$msg['lastname'].PHP_EOL;
							$message.= 'Email: '.$msg['email'].PHP_EOL;
							$icq->sendMessage($message_response[$msg['id']], $message);
							break;
						case 'accepted':
							$contact = $message_response[$msg['id']];
							$message = 'Message to '.$contact['to'].' accepted. Id: '.$msg['id'];
							$icq->sendMessage($contact['from'], $message);
							break;
					}
				}
				unset($message_response[$msg['id']]);
			}
			elseif (isset($msg['type'])) {
				switch ($msg['type']) {
					case 'error':
						$icq->sendMessage(ADMINUIN, 'Error: '.$msg['code'].' '.(isset($msg['error']) ? $msg['error'] : ''));
						break;
					case 'authrequest':
						$icq->setAuthorization($msg['from'], true, 'Just for fun!');
						break;
					case 'authresponse':
						var_dump($msg);
						$icq->sendMessage(ADMINUIN, 'Authorization response: '.$msg['from'].' - '.$msg['granted'].' - '.trim($msg['message']));
						break;
					case 'accepted':
						if (!$msg['uin'] == ADMINUIN) 
							var_dump($msg);
						break;
					case 'useronline':
					case 'autoaway':
						break;
					case 'rate':
						echo 'Rate: '.$msg['level'].PHP_EOL;
						break;
					default:
						var_dump($msg);
						break;
				}
			}
			elseif (isset($msg['errors'])) {
				$answer = '';
				foreach ($msg['errors'] as $error) {
					if (!isset($error['error']))
						$error['error'] = '';
					$answer .= 'Error: '.$error['code'].' '.$error['error'].PHP_EOL;
				}
				$icq->sendMessage(ADMINUIN, $answer);
			}
			else {
				var_dump($msg);
			}
		}
		else {
			if (is_array($msg)) 
				var_dump($msg);
		}
		flush();

		if (($status_time + 60) < time() && $status != STARTSTATUS) {
			$icq->setStatus(STARTSTATUS, 'STATUS_WEBAWARE', 'Ask me... I\'m Lead4CRM notification bot');
			$status = STARTSTATUS;
		}

		if (($xstatus_time + 60) < time() && $xstatus != STARTXSTATUS) {
			$icq->setXStatus(STARTXSTATUS);
			$xstatus = STARTXSTATUS;
		}
	}
	echo 'Will restart in 30 seconds...'.PHP_EOL;
	sleep(30);
}

function shutdown() {
	global $icq;
	$icq->sendMessage(ADMINUIN, 'Service Lead4CRM Bot stoped...');
	$icq->disconnect();
	exit;
}

function sig_handler($signo) {
	switch ($signo) {
		case SIGTERM:
			exit;
			break;
	}
}