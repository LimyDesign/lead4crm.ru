<?php

namespace Longman\TelegramBot\Commands;

use Longman\TelegramBot\Request;
use Longman\TelegramBot\Command;
use Longman\TelegramBot\Entities\Update;

class StartCommand extends Command
{
	protected $name = 'start';
	protected $description = 'Подключиться к системе моментального уведомления Lead4CRM.';
	protected $usage = '/start <ключ доступа>';
	protected $version = '1.0.0';
	protected $enabled = true;
	protected $public = true;

	public function execute()
	{
		$update = $this->getUpdate();
		$message = $this->getMessage();

		$chat_id = $message->getChat()->getId();
		$text = $message->getText(true);

		if (empty($text)) {
			$reply = 'Для того, чтобы связать ваш аккаунт в Telegram с аккаунтом в Lead4CRM, отправьте:'."\n\n".$this->usage."\n\n".'Ключ доступа можно найти в личном кабинете на сайте www.lead4crm.ru';
		} else {
			$reply = 'Указан не верный ключ доступа.';
		}

		$data = array();
		$data['chat_id'] = $chat_id;
		$data['text'] = $reply;

		$result = Request::sendMessage($data);
		return $result;
	}
}