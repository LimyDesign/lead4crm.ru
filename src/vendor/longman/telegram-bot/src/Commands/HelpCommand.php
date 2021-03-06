<?php

/*
 * This file is part of the TelegramBot package.
 *
 * (c) Avtandil Kikabidze aka LONGMAN <akalongman@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
*/
namespace Longman\TelegramBot\Commands;

use Longman\TelegramBot\Request;
use Longman\TelegramBot\Command;
use Longman\TelegramBot\Entities\Update;

class HelpCommand extends Command
{
    protected $name = 'help';
    protected $description = 'Показывает справочную информацию';
    protected $usage = '/help или /help <команда>';
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

        $commands = $this->telegram->getCommandsList();

        if (empty($text)) {
            $msg = 'Lead4CRM bot v. ' . $this->telegram->getVersion() . "\n\n";
            $msg .= 'Список команд:' . "\n";
            foreach ($commands as $command) {
                if (is_object($command)) {
                    if (!$command->isEnabled()) {
                        continue;
                    }
                    if (!$command->isPublic()) {
                        continue;
                    }

                    $msg .= '/' . $command->getName() . ' - ' . $command->getDescription() . "\n";
                }
            }

            $msg .= "\n" . 'Показать справку по команде: /help <командаы>';
        } else {
            $text = str_replace('/', '', $text);
            if (isset($commands[$text])) {
                $command = $commands[$text];
                if (!$command->isEnabled() || !$command->isPublic()) {
                    $msg = 'Команда ' . $text . ' не найдена';
                } else {
                    $msg = 'Команда: ' . $command->getName() . ' v' . $command->getVersion() . "\n";
                    $msg .= 'Описание: ' . $command->getDescription() . "\n";
                    $msg .= 'Использование: ' . $command->getUsage();
                }
            } else {
                $msg = 'Команда ' . $text . ' не найдена';
            }
        }

        $data = array();
        $data['chat_id'] = $chat_id;
        $data['reply_to_message_id'] = $message_id;
        $data['text'] = $msg;

        $result = Request::sendMessage($data);
        return $result;
    }
}
