<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    private string $apiUrl  = 'https://devsms.uz/api/send_sms.php';
    private string $token;
    private string $from;

    public function __construct()
    {
        $this->token = config('services.devsms.token', '');
        $this->from  = config('services.devsms.from', '4546');
    }

    /**
     * Send OTP code to the given phone number.
     * Phone must be in 998XXXXXXXXX format.
     */
    public function sendOtp(string $phone, string $code): bool
    {
        $message = "TAQSEEM ilovasiga kirish uchun bir martalik kod: {$code}";

        return $this->send($phone, $message);
    }

    /**
     * Send a raw SMS message.
     */
    public function send(string $phone, string $message): bool
    {
        if (empty($this->token)) {
            Log::warning('SmsService: token not configured, skipping send.', [
                'phone' => $phone,
            ]);
            return false;
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->token}",
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ])
                ->post($this->apiUrl, [
                    'phone'   => $phone,
                    'message' => $message,
                    'from'    => $this->from,
                ]);

            if ($response->successful()) {
                Log::info('SmsService: sent successfully.', [
                    'phone'  => $phone,
                    'status' => $response->status(),
                ]);
                return true;
            }

            Log::error('SmsService: API error.', [
                'phone'  => $phone,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return false;

        } catch (\Throwable $e) {
            Log::error('SmsService: exception.', [
                'phone'   => $phone,
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
