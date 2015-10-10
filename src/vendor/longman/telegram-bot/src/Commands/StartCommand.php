<?php

namespace Longman\TelegramBot\Commands;

use Longman\TelegramBot\Request;
use Longman\TelegramBot\Lead4CRM;
use Longman\TelegramBot\Command;
use Longman\TelegramBot\Entities\Update;
use Longman\TelegramBot\Exception\TelegramException;

class StartCommand extends Command
{
	protected $name = 'start';
	protected $description = 'Подключиться к системе моментального уведомления Lead4CRM.';
	protected $usage = '/start <ключ доступа>';
	protected $version = '1.0.0';
	protected $enabled = true;
	protected $public = true;
	protected $need_pgsql = true;

	public function executeNoDB()
	{
		$message = $this->getMessage;
		$chat_id = $message->getChat()->getId();
		$data = array();
		$data['chat_id'] = $chat_id;
		$data['text'] = 'Извините, но в данный момент отсутствует подключение к базе данных, невозможно выполнить команду: '.$this->name;
		return Request::sendMessage($data);
	}

	public function execute()
	{
		$update = $this->getUpdate();
		$message = $this->getMessage();

		$chat_id = $message->getChat()->getId();
		$message_id = $message->getMessageId();
		$text = $message->getText(true);

		if (empty($text)) {
			$reply = 'Для того, чтобы связать ваш аккаунт в Telegram с аккаунтом в Lead4CRM, отправьте:'."\n\n".$this->usage."\n\n".'Ключ доступа можно найти в личном кабинете на сайте www.lead4crm.ru'."\n\n".'Для дополнительной информации отправьте /help';
		} else {
			$result = DB::subscribeUserByAPIKey($text, $chat_id);
			if ($result) {
				$reply = "Ваш аккаунт в Lead4CRM.ru привязан к Telegram.\n\nТеперь можно перейти к настройке уведомлений. Введите /notify для получения справки. По умолчанию все уведомления выключены, включите те, которые вам необходимы.";
			} else {
				$reply = 'Указан не верный ключ доступа.';
			}
		}

		$data = array();
		$data['chat_id'] = $chat_id;
		$data['reply_to_message_id'] = $message_id;
		$data['text'] = $reply;

		$result = Request::sendMessage($data);
		return $result;
	}
}