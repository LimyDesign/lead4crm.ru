<?php

namespace Longman\TelegramBot\Commands;

use Longman\TelegramBot\Request;
use Longman\TelegramBot\DB;
use Longman\TelegramBot\Command;
use Longman\TelegramBot\Entities\Update;
use Longman\TelegramBot\Exception\TelegramException;

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
            $reply = "Для того чтобы включить, отключить или проверить статус уведомления, для каждого действия введите следующие команды:\n\n/notify company [on|off|status] — Уведомление об импорте компаний\n/notify renewal [on|off|status] — Уведомление о продлении тарифного плана\n/notify balans [on|off|status] - Уведомление об изменении баланса";
        }
        else
        {
            list($type, $mode) = explode(' ', $text);
            if (empty($mode)) {
                $reply = "Команда введена не полностью.";
            }
            else {
                switch ($mode) {
                    case 'on':
                    case 'off':
                        $return = DB::setNotificationByChatId($type, $mode, $chat_id);
                        $return = $return == $chat_id ? true : false;
                        break;
                    case 'status':
                    default:
                        $return = DB::getNotificationByChatId($type, $chat_id);
                }
                switch ($type) {
                    case 'company':
                        $type_rus = 'об импорте компаний';
                        break;
                    case 'renewal':
                        $type_rus = 'о продлении тарифного плана';
                        break;
                    case 'balans':
                        $type_rus = 'об изменении баланса';
                        break;
                }
                $status = $return ? ' включено.' : ' выключено.';
                $reply = 'Уведомление ' . $type_rus . $status;
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