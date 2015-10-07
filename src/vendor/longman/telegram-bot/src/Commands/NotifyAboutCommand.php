<?php

namespace Longman\TelegramBot\Commands;

use Longman\TelegramBot\Request;
use Longman\TelegramBot\Command;
use Longman\TelegramBot\Entities\Update;

class NotifyAboutCommand extends Command
{
	protected $name = 'notifyabout';
	protected $description = 'Настройка уведомлений';
	protected $usage = '/notifyabout';
	protected $version = '1.0.0';
	protected $enabled = true;
	protected $public = true;

	public function execute()
	{
		$update = $this->getUpdate();
        $message = $this->getMessage();

        $chat_id = $message->getChat()->getId();
        $message_id = $message->getMessageId();
        $text = $message->getText(true);

        $data = array();
        $data['chat_id'] = $chat_id;
        $data['text'] = "Выберите раздел уведомлений:";

        $keyboards = array();

        // 0
        $keyboard[] = ['Импорт компаний'];
        $keyboard[] = ['Предупреждение о продлении тарифа'];
        $keyboard[] = ['Уведомление о снижении баланса'];

        $keyboards[] = $keyboard;
        unset($keyboard);

        $reply_keyboard_markup = new ReplyKeyboardMarkup(
        	[
        		'keyboard' => $keyboards[0];
        		'resize_keyboard' => true,
        		'one_time_keyboard' => false,
        		'selective' => false
        	]
        );
        $data['reply_markup'] = $reply_keyboard_markup;

        $result = Request::sendMessage($data);
        return $result;
	}
}