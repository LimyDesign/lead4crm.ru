<?php

/*
 * This file is part of the TelegramBot package.
 *
 * (c) Avtandil Kikabidze aka LONGMAN <akalongman@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
*/

namespace Longman\TelegramBot\Entities;

use Longman\TelegramBot\Exception\TelegramException;

class Message extends Entity
{
    protected $message_id;

    protected $from;

    protected $date;

    protected $chat;

    protected $forward_from;

    protected $forward_date;

    protected $reply_to_message;

    protected $text;

    protected $audio;

    protected $document;

    protected $photo;

    protected $sticker;

    protected $video;

    protected $voice;

    protected $caption;

    protected $contact;

    protected $location;

    protected $new_chat_participant;

    protected $left_chat_participant;

    protected $new_chat_title;

    protected $new_chat_photo;

    protected $delete_chat_photo;

    protected $group_chat_created;

    protected $command;

    private $type;

    public function __construct(array $data, $bot_name)
    {
        $this->bot_name = $bot_name;
        $this->type = 'text';

        $this->message_id = isset($data['message_id']) ? $data['message_id'] : null;
        if (empty($this->message_id)) {
            throw new TelegramException('message_id is empty!');
        }

        $this->from = isset($data['from']) ? $data['from'] : null;
        if (empty($this->from)) {
            throw new TelegramException('from is empty!');
        }
        $this->from = new User($this->from);

        $this->date = isset($data['date']) ? $data['date'] : null;
        if (empty($this->date)) {
            throw new TelegramException('date is empty!');
        }

        $this->chat = isset($data['chat']) ? $data['chat'] : null;
        if (empty($this->chat)) {
            throw new TelegramException('chat is empty!');
        }
        $this->chat = new Chat($this->chat);

        $this->forward_from = isset($data['forward_from']) ? $data['forward_from'] : null;
        if (!empty($this->forward_from)) {
            $this->forward_from = new User($this->forward_from);
        }

        $this->forward_date = isset($data['forward_date']) ? $data['forward_date'] : null;

        $this->reply_to_message = isset($data['reply_to_message']) ? $data['reply_to_message'] : null;
        if (!empty($this->reply_to_message)) {
            $this->reply_to_message = new Message($this->reply_to_message, $this->bot_name);
        }

        $this->text = isset($data['text']) ? $data['text'] : null;
        $command = $this->getCommand();
        if (!empty($command)) {
            $this->type = 'command';
        }

        $this->audio = isset($data['audio']) ? $data['audio'] : null;
        if (!empty($this->audio)) {
            $this->audio = new Audio($this->audio);
        }

        $this->document = isset($data['document']) ? $data['document'] : null;
        if (!empty($this->document)) {
            $this->document = new Document($this->document);
        }

        $this->photo = isset($data['photo']) ? $data['photo'] : null; //array of photosize
        if (!empty($this->photo)) {
            foreach ($this->photo as $photo) {
                if (!empty($photo)) {
                    $photos[] = new PhotoSize($photo);
                }
            }
            $this->photo = $photos;
        }

        $this->sticker = isset($data['sticker']) ? $data['sticker'] : null;
        if (!empty($this->sticker)) {
            $this->sticker = new Sticker($this->sticker);
        }

        $this->video = isset($data['video']) ? $data['video'] : null;
        if (!empty($this->video)) {
            $this->video = new Video($this->video);
        }

        $this->voice = isset($data['voice']) ? $data['voice'] : null;
        if (!empty($this->voice)) {
            $this->voice = new Voice($this->voice);
        }

        $this->caption = isset($data['caption']) ? $data['caption'] : null;//string

        $this->contact = isset($data['contact']) ? $data['contact'] : null;
        if (!empty($this->contact)) {
            $this->contact = new Contact($this->contact);
        }

        $this->location = isset($data['location']) ? $data['location'] : null;
        if (!empty($this->location)) {
            $this->location = new Location($this->location);
        }

        $this->new_chat_participant = isset($data['new_chat_participant']) ? $data['new_chat_participant'] : null;
        if (!empty($this->new_chat_participant)) {
            $this->new_chat_participant = new User($this->new_chat_participant);
            $this->type = 'new_chat_participant';
        }

        $this->left_chat_participant = isset($data['left_chat_participant']) ? $data['left_chat_participant'] : null;
        if (!empty($this->left_chat_participant)) {
            $this->left_chat_participant = new User($this->left_chat_participant);
            $this->type = 'left_chat_participant';
        }

        $this->new_chat_title = isset($data['new_chat_title']) ? $data['new_chat_title'] : null;
        if ($this->new_chat_title) {
            $this->type = 'new_chat_title';
        }

        $this->new_chat_photo = isset($data['new_chat_photo']) ? $data['new_chat_photo'] : null; //array of photosize
        if (!empty($this->new_chat_photo)) {
            foreach ($this->new_chat_photo as $photo) {
                if (!empty($photo)) {
                    $photos[] = new PhotoSize($photo);
                }
            }
            $this->new_chat_photo = $photos;
        }

        $this->delete_chat_photo = isset($data['delete_chat_photo']) ? $data['delete_chat_photo'] : null;
        if ($this->delete_chat_photo) {
            $this->type = 'delete_chat_photo';
        }

        $this->group_chat_created = isset($data['group_chat_created']) ? $data['group_chat_created'] : null;
        if ($this->group_chat_created) {
            $this->type = 'group_chat_created';
        }
    }

    //return the entire command like /echo or /echo@bot1 if specified
    public function getFullCommand()
    {
        if (substr($this->text, 0, 1) === '/') {
            return strtok($this->text, ' ');
        } else {
            return;
        }
    }

    public function getCommand()
    {
        if (!empty($this->command)) {
            return $this->command;
        }

        $cmd = $this->getFullCommand();

        if (substr($cmd, 0, 1) === '/') {
            $cmd = substr($cmd, 1);

            //check if command is follow by botname
            $split_cmd = explode('@', $cmd);
            if (isset($split_cmd[1])) {
                //command is followed by name check if is addressed to me
                if (strtolower($split_cmd[1]) == strtolower($this->bot_name)) {
                    return $this->command = $split_cmd[0];
                }
            } else {
                //command is not followed by name
                return $this->command = $cmd;
            }
        }

        return false;
    }

    public function getMessageId()
    {
        return $this->message_id;
    }

    public function getFrom()
    {
        return $this->from;
    }

    public function getDate()
    {
        return $this->date;
    }

    public function getChat()
    {
        return $this->chat;
    }

    public function getForwardFrom()
    {
        return $this->forward_from;
    }

    public function getForwardDate()
    {
        return $this->forward_date;
    }

    public function getReplyToMessage()
    {
        return $this->reply_to_message;
    }

    public function getText($without_cmd = false)
    {
        $text = $this->text;
        if ($without_cmd) {
            $command = $this->getFullCommand();
            if (!empty($command)) {
                $text = substr($text, strlen($command.' '), strlen($text));
            }
        }

        return $text;
    }

    public function getAudio()
    {
        return $this->audio;
    }
    public function getDocument()
    {
        return $this->document;
    }

    public function getPhoto()
    {
        return $this->photo;
    }

    public function getSticker()
    {
        return $this->sticker;
    }

    public function getVideo()
    {
        return $this->video;
    }

    public function getVoice()
    {
        return $this->voice;
    }

    public function getCaption()
    {
        return $this->caption;
    }

    public function getContact()
    {
        return $this->contact;
    }

    public function getLocation()
    {
        return $this->location;
    }

    public function getNewChatParticipant()
    {
        return $this->new_chat_participant;
    }

    public function getLeftChatParticipant()
    {
        return $this->left_chat_participant;
    }

    public function getNewChatTitle()
    {
        return $this->new_chat_title;
    }


    public function getNewChatPhoto()
    {
        return $this->new_chat_photo;
    }


    public function getDeleteChatPhoto()
    {
        return $this->delete_chat_photo;
    }

    public function getGroupChatCreated()
    {
        return $this->group_chat_created;
    }


    public function botAddedInChat()
    {
        if (!empty($this->new_chat_participant)) {
            if ($this->new_chat_participant->getUsername() == $this->getBotName()) {
                return true;
            }
        }

        return false;
    }

    public function getType()
    {
        return $this->type;
    }
}
