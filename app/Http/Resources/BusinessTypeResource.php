<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Detect locale from Accept-Language header or query param
        $locale = $request->query('locale')
            ?? $this->detectLocale($request->header('Accept-Language', 'uz'));

        return [
            'id'          => $this->id,
            'key'         => $this->key,
            'icon'        => $this->icon,
            'color'       => $this->color,
            'sort_order'  => $this->sort_order,
            'name'        => $this->getLocalizedName($locale),
            'names'       => [
                'uz'      => $this->name_uz,
                'uz_CYRL' => $this->name_uz_cyrl,
                'ru'      => $this->name_ru,
                'kk'      => $this->name_kk,
                'ky'      => $this->name_ky,
                'tr'      => $this->name_tr,
                'tg'      => $this->name_ru,
            ],
            'terminology' => $this->terminology,
        ];
    }

    private function detectLocale(string $header): string
    {
        $supported = ['uz', 'uz_CYRL', 'ru', 'kk', 'ky', 'tr', 'tg'];
        $lang = strtolower(explode(',', explode(';', $header)[0])[0]);
        $lang = str_replace('-', '_', $lang);
        return in_array($lang, $supported) ? $lang : 'uz';
    }
}
