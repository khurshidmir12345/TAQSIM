<?php

namespace App\Enums;

enum ShopUserType: string
{
    case Owner = 'owner';
    case Seller = 'seller';
}
