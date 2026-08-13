<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Eski parol shartli talab qilinadi:
            //  - parolsiz foydalanuvchida (Google/Telegram orqali kirgan) yo'q;
            //  - SMS kodi bilan kirganida ham so'ralmaydi — parolni unutgan
            //    odam ta'rifiga ko'ra uni kirita olmaydi, telefon egaligi esa
            //    kod orqali allaqachon isbotlangan.
            'current_password' => [
                Rule::requiredIf(fn (): bool => $this->needsCurrentPassword()),
                'string',
                'current_password',
            ],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ];
    }

    /**
     * Eski parol so'ralishi kerakmi.
     *
     * Yo'q, agar: foydalanuvchida parol umuman bo'lmasa, yoki u yaqinda SMS
     * kodi bilan kirgan bo'lsa.
     */
    private function needsCurrentPassword(): bool
    {
        $user = $this->user();

        if (! $user || $user->password === null) {
            return false;
        }

        return ! $user->mustSetPassword();
    }
}
