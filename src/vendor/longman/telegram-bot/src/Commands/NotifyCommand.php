<?php

namespace Longman\TelegramBot\Commands;

use Longman\TelegramBot\Request;
use Longman\TelegramBot\Command;
use Longman\TelegramBot\Entities\Update;

use Longman\TelegramBot\Entities\ReplyKeyboardMarkup;
use Longman\TelegramBot\Entities\ReplyKeyboardHide;
use Longman\TelegramBot\Entities\ForceReply;

class NotifyCommand extends Command
{
	protected $name = 'notify';
	protected $description = 'Настройка уведомлений';
	protected $usage = '/notify';
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

        if (empty($text))
        {
                $reply = "Для того чтобы включить, отключить или проверить статус уведомления, для каждого действия введите следующие команды:\n\n/notify company [on|off|status] — Уведомление об импорте компании\n/notify renewal [on|off|status] — Уведомление о продлении тарифного плана\n/notify balans [on|off|status] - Уведомление об изменении баланса";
        }

        $data = array();
        $data['chat_id'] = $chat_id;
        $data['text'] = $reply;


        $result = Request::sendMessage($data);
        return $result;
	}
}