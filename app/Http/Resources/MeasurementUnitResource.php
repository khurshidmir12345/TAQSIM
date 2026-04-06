<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeasurementUnitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = $this->detectLocale($request);

        return [
            'id'      => $this->id,
            'type'    => $this->type,
            'code'    => $this->code,
            'icon'    => $this->icon,
            'name'    => $this->getLocalizedName($locale),
            'example' => $this->getLocalizedExample($locale),
            'names'   => [
                'uz'      => $this->name_uz,
                'uz_CYRL' => $this->name_uz_cyrl,
                'ru'      => $this->name_ru,
                'kk'      => $this->name_kk,
                'ky'      => $this->name_ky,
                'tr'      => $this->name_tr,
            ],
            'examples' => [
                'uz' => $this->example_uz,
                'ru' => $this->example_ru,
            ],
            'sort_order' => $this->sort_order,
        ];
    }

    private function detectLocale(Request $request): string
    {
        $locale = $request->header('Accept-Language', 'uz');
        return explode(',', explode('-', $locale)[0])[0];
    }
}
