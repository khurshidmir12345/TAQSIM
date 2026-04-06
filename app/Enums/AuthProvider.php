<?php

namespace App\Enums;

enum AuthProvider: string
{
    case Telegram = 'telegram';
    case Google = 'google';
    case Phone = 'phone';
}
