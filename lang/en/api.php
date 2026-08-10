<?php

return [
    'success' => 'Success',
    'created' => 'Created',
    'updated' => 'Updated.',
    'deleted' => 'Deleted',
    'ping' => 'Taqseem API is running',

    'errors' => [
        'subscription_required' => 'Your subscription has expired. Choose a plan to continue.',
        'plan_limit_reached' => 'Plan limit reached. Upgrade to a higher plan.',
        'insufficient_balance' => 'Insufficient balance. Please top up your wallet.',
        'yearly_not_available' => 'Yearly subscription is not available for this plan.',
        'generic' => 'Error',
        'unauthenticated' => 'Authentication required.',
        'validation_failed' => 'The provided data is invalid.',
        'not_found' => 'Record not found.',
        'not_found_http' => 'Page not found.',
        'forbidden' => 'Access denied.',
        'forbidden_shop' => 'You do not have access to this business.',
        'forbidden_shop_bakery' => 'You do not have access to this bakery.',
        'forbidden_owner_only' => 'Only the owner can perform this action.',
        'forbidden_permission' => 'You do not have permission for this section.',
        'rate_limit' => 'Too many requests. Please wait a moment.',
        'server_error' => 'A server error occurred.',
        'invalid_expense_category' => 'Invalid expense category or it does not belong to you.',
        'expense_category_duplicate' => 'A category with this name already exists or conflicts with a system category.',
        'return_production_mismatch' => 'The selected batch does not match this product type or date.',
        'customer_has_orders' => 'This customer has orders. Delete or cancel the orders first.',
        'customer_order_customer_required' => 'Select a customer or enter new customer details.',
        'order_not_active' => 'This action is only available for active orders.',
        'order_total_below_paid' => 'The new total cannot be less than the amount already paid.',
        'payment_exceeds_remaining' => 'Payment exceeds the remaining amount.',
        'payment_amount_invalid' => 'Invalid payment amount.',
        'bread_category_not_in_shop' => 'The selected product does not belong to this business.',
        'order_has_payments' => 'An order with payments cannot be deleted. Cancel it instead.',
    ],

    'auth' => [
        'send_code_phone_exists' => 'This number is already registered. Verification code sent.',
        'send_code_new' => 'Verification code sent.',
        'register_phone_taken' => 'This phone number is already registered. Please sign in.',
        'invalid_code' => 'Verification code is invalid or expired.',
        'register_success' => 'Registration completed.',
        'login_invalid' => 'Invalid phone number or password.',
        'login_success' => 'Signed in successfully.',
        'profile_updated' => 'Profile updated.',
        'avatar_updated' => 'Avatar updated.',
        'avatar_removed' => 'Avatar removed.',
        'password_changed' => 'Password changed.',
        'account_deleted' => 'Account deleted.',
        'logout_success' => 'Signed out successfully.',
        'device_revoked' => 'Device removed.',
        'apple_invalid_token' => 'Apple identity token is invalid or expired.',
        'apple_login_success' => 'Signed in with Apple ID.',
        'google_invalid_token' => 'Google identity token is invalid or expired.',
        'google_login_success' => 'Signed in with Google.',
    ],

    'shop' => [
        'created' => 'Business created successfully.',
        'deleted' => 'Business deleted.',
    ],

    'recipe' => [
        'duplicate_bread_category' => 'A recipe for this product type already exists.',
    ],

    'employees' => [
        'code_sent' => 'Verification code sent to the employee phone number.',
        'created' => 'Employee added.',
        'phone_taken' => 'This number is already registered. Enter a different number.',
        'invite_expired' => 'Invitation expired. Please try again.',
        'seat_suspended' => 'Employee seat payment expired. Please top up the balance.',
        'owner_subscription_inactive' => 'Business owner subscription expired. Contact the owner.',
    ],

    'cash' => [
        'auto_entry_readonly' => 'This entry comes from a production or return record — edit it on the main page.',
    ],
];
