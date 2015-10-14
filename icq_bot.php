<?php
error_reporting(E_ERROR);

define('ADMINUIN', '881129');
define('STARTXSTATUS', 'business');
define('STARTSTATUS', 'STATUS_FREE4CHAT');

file_put_contents('icq_bot.pid', posix_getpid());

require_once __DIR__.'/src/vendor/autoload.php';

$conf = json_decode(file_get_contents(__DIR__.'/config.json'));

$icq = new WebIcqPro();
$icq->debug = true;
$icq->setOption('UserAgent', 'japp');

pcntl_signal(SIGTERM, 'sig_handler');
pcntl_signal(SIGKILL, 'sig_handler');
pcntl_signal(SIGUSR1, 'sig_handler');
register_shutdown_function('shutdown');

$help = "Команды информатора:\r
\t'!about' - об информаторе\r
\t'!help' - справка по всем командам информатора\r
\t'!help [!command]' - справка по конкретной комманде\r
\t'!connect [apikey]' - подключение к информатору\r
\t'!notify [balans|renewal|company] [on|off]' - настройка уведомлений информатора\r
\t'!diconnect' - отключение от информатора\r
";

$admcmd = "\nАдминские команды:\r
\t'!exit' - выключить бота\r
\t'!contact' - показать список контактов бота (выведет только тех кто подключился)\r
\t'!info [uin]' - показать информацию о пользователе\r
\t'!to [uin] [message]' - отправить сообщение кому-либо от имени бота\r
\t'!uptime' - показать время работы бота\r
";

$about = "Lead4CRM Bot v1.0.0\r
\tЯ маленький, но очень шустрый информатор сайта www.lead4crm.ru.\r
\tЯ смогу сообщить вам о том что произошло с вашим балансом,\r
\tо том какая компания была только что импортирована, а также\r
\tпредупредить о грядущем продлении вашего тарифного плана.\r
";

$ignore_list = array();
$message_response = array();

$dsn = 'pgsql:host='.$conf->db->host.';dbname='.$conf->db->database;
$pdo = new PDO($dsn, $conf->db->username, $conf->db->password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);

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
				$icq->sendMessage(ADMINUIN, $msg['from'].'>'.trim($msg['message']));
				switch (strtolower(trim($msg['message']))) {
					case '!about':
						sleep(1);
						$icq->sendMessage($msg['from'], mb_convert_encoding($about, 'cp1251'));
						break;
					case '!help':
						if ($msg['from'] == ADMINUIN) {
							$message = mb_convert_encoding($help.$admcmd, 'cp1251');
						}
						else {
							$message = mb_convert_encoding($help, 'cp1251');
						}
						sleep(1);
						$icq->sendMessage($msg['from'], $message);
						break;
					case '!connect':
						$message = mb_convert_encoding("Для того, чтобы привязать ваш UIN к сайту www.lead4crm.ru, необходимо ввести полную команду:\r\t!connect [apikey]\rгде [apikey] — ваш персональный ключ доступа, который можно найти на сайте www.lead4crm.ru", 'cp1251');
						sleep(1);
						$icq->sendMessage($msg['from'], $message);
						break;
					case '!notify':
						$message = mb_convert_encoding("Для включения, отключения или проверки статуса требуется ввести соответствующие команды:\r\t!notify [company|renewal|balans] [on|off|status]\rгде [company|renewal|balans] — тип уведомления (импорт компаний, продление тарифа, изменение баланса), а [on|off|status] — включение, выключение или проверка статуса.", 'cp1251');
						sleep(1);
						$icq->sendMessage($msg['from'], $message);
						break;
					case '!uptime':
						if ($msg['from'] == ADMINUIN) {
							$seconds = time() - $uptime;
							$time = '';
							$days = (int)floor($seconds/86400);
							if ($days)
								$time .= $days.morph($days, ' день ', ' дня ', ' дней ');
							$hours = (int)floor(($seconds-$days*86400)/3600);
							if ($hours)
								$time .= $hours.morph($hours, ' час ', ' часа ', ' часов ');
							$minutes = (int)floor(($seconds-$days*86400-$hours*3600)/60);
							if ($minutes)
								$time .= $minutes.morph($minutes, ' минута ', ' минуты ', ' минут ');
							$seconds = (int)fmod($seconds, 60);
							if ($seconds)
								$time .= $seconds.morph($seconds, ' секунда ', ' секунды ', ' секунд ');
							$message = mb_convert_encoding($time.'онлайн. Последний вход: '.date('d.m.Y H:i:s', $uptime), 'cp1251');
							sleep(1);
							$icq->sendMessage($msg['from'], $message);
						}
						break;
					case '!contact':
						if ($msg['from'] == ADMINUIN) {
							$c = getContactList($icq->getContactList());
							foreach ($c as $m) {
								$m = str_replace("\x00", '', $m);
								$icq->sendMessage($msg['from'], mb_convert_encoding($m, 'cp1251'));
							}
						}
						break;
					case '!disconnect':
						$query = 'UPDATE "public"."users" SET "icq_uin" = null, "icq_company" = false, "icq_renewal" = false, "icq_balans" = false WHERE "icq_uin" = :uuin';
						$sth = $pdo->prepare($query);
						$sth->bindParam(':uuin', $msg['from'], PDO::PARAM_STR, 255);
						$sth->execute();
						$message = mb_convert_encoding("Ваш UIN ({$msg['from']}) был откреплен от ICQ информатора.\rСпасибо за использование нашего сервиса.", 'cp1251');
						sleep(1);
						$icq->sendMessage($msg['from'], $message);
						break;
					case '!stop':
					case '!exit':
						if ($msg['from'] == ADMINUIN) {
							exit;
						}
						else {
							sleep(1);
							$icq->sendMessage($msg['from'], 'The system is going down for reboot NOW!');
						}
						break;
					default:
						$command = explode(' ', $msg['message']);
						if (count($command) > 1) {
							switch($command[0]) {
								case '!connect':
									$query = 'UPDATE "public"."users" SET "icq_uin" = :uuin WHERE "apikey" = :apikey';
									$sth = $pdo->prepare($query);
									$sth->bindParam(':uuin', $msg['from'], PDO::PARAM_INT);
									$sth->bindParam(':apikey', $command[1], PDO::PARAM_STR, 255);
									$sth->execute();
									$list = $icq->getContactList();
									if (!isset($list[$msg['from']])) {
										$group = 'auto';
										if (!in_array($group, $icq->getContactListGroups())) {
											$icq->addContactGroup($group);
										}
										$icq->addContact($group, array('uin' => $msg['from']));
										$message = mb_convert_encoding('Я хочу увидеть твой статус!', 'cp1251');
										$icq->getAuthorization($msg['from'], $message);
									}
									$message = mb_convert_encoding("Ваш UIN (".$msg['from'].") записан! Теперь можно перейти к настройке информатора!", 'cp1251');
									sleep(1);
									$icq->sendMessage($msg['from'], $message);
									break;
								case '!info':
									if ($msg['from'] == ADMINUIN) {
										$id = $icq->getShortInfo($command[1]);
										if ($id) {
											$message_response[$id] = $msg['from'];
										}
										else {
											$message = mb_convert_encoding("Ошибка получения информации для UIN: ".$command[1], 'cp1251');
											sleep(1);
											$icq->sendMessage($msg['from'], $message);
										}
									}
									break;
								case '!to':
									if ($msg['from'] == ADMINUIN) {
										$to = $command[1];
										if ($to != $conf->icq->uin) {
											unset($command[0]);
											unset($command[1]);
											$command = implode(' ', $command);
											$id = $icq->sendMessage($to, $command);
											if ($id !== false) {
												$message_response[$id] = array('from' => $msg['from'], 'to' => $to);
												$message = mb_convert_encoding("Принято на доставку. Идентификатор сообщения: ".$id, 'cp1251');
												$icq->sendMessage($msg['from'], $message);
											}
											else {
												$icq->sendMessage($msg['from'], $icq->error);
											}
										}
										else {
											$message = mb_convert_encoding("Нельзя отправлять сообшения данному UIN", 'cp1251');
											$icq->sendMessage($msg['from'], $message);
										}
									}
									break;
								default:
									var_dump($msg);
									$message = mb_convert_encoding("Введите '!help' для получния справки по командам.", 'cp1251');
									sleep(1);
									$icq->sendMessage($msg['from'], $message);
									break;
							}
						}
						else {
							var_dump($msg);
							$message = mb_convert_encoding("Введите '!help' для получния справки по командам.", 'cp1251');
							sleep(1);
							$icq->sendMessage($msg['from'], $message);
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

		if (($last_db_query+60) < time()) {

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

function getContactList($list) {
	$n = 0;
	$message[$n] = "Список контактов:\r\n";
	$i = 0;
	foreach ($list as $uin => $data) {
		$i++;
		if ($i > 60) { $i = 0; $n++; $message[$n] = ''; }
		$message[$n] .= (isset($data['name']) ? trim($data['name'])." ($uin)" : $uin)
					 .' : '.(isset($data['status']) ? $data['status'] : 'STATUS_OFFLINE')
					 .' : '.(isset($data['xstatus']) ? $data['xstatus'] : '')."\r\n";
	}
	return $message;
}

function morph($n, $f1, $f2, $f5) {
    $n = abs(intval($n)) % 100;
    if ($n>10 && $n<20) return $f5;
    $n = $n % 10;
    if ($n>1 && $n<5) return $f2;
    if ($n==1) return $f1;
    return $f5;
}

function shutdown() {
	global $icq;
	while ($icq->isConnected()) {
		$icq->sendMessage(ADMINUIN, 'Service Lead4CRM Bot stoped...');
		$icq->setStatus('STATUS_OFFLINE', 'STATUS_WEBAWARE', 'Goodbay!');
		sleep(1);
		$icq->disconnect();
	}
	exit;
}

function sig_handler($signo) {
	switch ($signo) {
		case SIGKILL:
		case SIGTERM:
			shutdown();
			break;
		case SIGUSR1:
			if (file_exists('icq_bot.tmp')) {
				$cmd = file_get_contents('icq_bot.tmp');
				switch ($cmd) {
					case 'stop':
						unlink('icq_bot.tmp');
						exit;
						break;
				}
			}
			break;
	}
}