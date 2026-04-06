<?php

namespace App\Http\Controllers\Api;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Nonvoyxona API',
    description: 'Nonvoyxona - Pekarnya boshqaruv tizimi API hujjatlari',
    contact: new OA\Contact(email: 'hello@nonvoyxona.uz')
)]
#[OA\Server(url: 'http://localhost:8086/api', description: 'Local Development')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Sanctum Token'
)]
#[OA\Tag(name: 'Auth', description: 'Autentifikatsiya')]
#[OA\Tag(name: 'Shops', description: 'Nonvoyxonalar')]
#[OA\Tag(name: 'BreadCategories', description: 'Non turlari')]
#[OA\Tag(name: 'Ingredients', description: 'Ingredientlar')]
#[OA\Tag(name: 'Recipes', description: 'Retseptlar')]
#[OA\Tag(name: 'Productions', description: 'Ishlab chiqarish')]
#[OA\Tag(name: 'Returns', description: 'Qaytarishlar')]
#[OA\Tag(name: 'Expenses', description: 'Xarajatlar')]
#[OA\Tag(name: 'Reports', description: 'Hisobotlar')]
class SwaggerController
{
}
