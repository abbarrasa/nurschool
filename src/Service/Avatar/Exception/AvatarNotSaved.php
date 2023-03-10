<?php

namespace Nurschool\Service\Avatar\Exception;

use Exception;

class AvatarNotSaved extends Exception
{
    static public function create()
    {
        return new self('Avatar can not be saved');
    }
}