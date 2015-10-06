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

class UserProfilePhotos extends Entity
{

    protected $total_count;
    protected $photos;

    public function __construct(array $data)
    {

        $this->total_count = isset($data['total_count']) ? $data['total_count'] : null;
        if (empty($this->total_count)) {
            throw new TelegramException('total_count is empty!');
        }
        $this->photos = isset($data['photos']) ? $data['photos'] : null;
        if (empty($this->photos)) {
            throw new TelegramException('photos is empty!');
        }

        //TODO check
        $photos = [];
        foreach ($this->photos as $key => $photos) {
            if (is_array($photo)) {
                $photos[] = [new PhotoSize($photo[0])];
            } else {
                throw new TelegramException('photo is not an array!');
            }
        }

        $this->photos = $photos;
    }

    public function getTotalCount()
    {
        return $this->total_count;
    }

    public function getPhotos()
    {
        return $this->photos;
    }
}
