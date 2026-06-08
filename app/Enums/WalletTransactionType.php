<?php

namespace App\Enums;

enum WalletTransactionType: string
{
    case Topup = 'topup';
    case SubscriptionCharge = 'subscription_charge';
    case EmployeeSeatCharge = 'employee_seat_charge';
    case Refund = 'refund';
    case Adjustment = 'adjustment';
}
