<?php

namespace App\Enums;

enum OrderType: string
{
    case Subscription = 'subscription';
    case Topup = 'topup';
    case EmployeeSeat = 'employee_seat';
}
