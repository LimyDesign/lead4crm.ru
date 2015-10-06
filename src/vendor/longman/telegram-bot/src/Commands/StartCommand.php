<?php

namespace Longman\TelegramBot\Commands;

use Longman\TelegramBot\Request;
use Longman\TelegramBot\Command;
use Longman\TelegramBot\Entities\Update;

class StartCommand extends Command
{
	protected $name = 'start';
	protected $description = 'Подключиться к системе моментального уведомления Lead4CRM.';
	protected $usage = '/start';
	protected $version = '1.0.0';
	protected $enabled = true;
	protected $public = true;

	public function execute()
	{
		$update = $this->getUpdate();
		$message = $this->getMessage();

		$chat_id = $message->getChat()->getId();
		$text = $message->getText(false);

		$data = array();
		$data['chat_id'] = $chat_id;
		$data['text'] = $text;

		$result = Request::sendMessage($data);
		return $result;
	}
}