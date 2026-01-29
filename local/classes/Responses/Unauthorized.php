<?php

namespace Webizi\Responses;

use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;

#[Schema(
    schema: "UnauthorizedResponse",
    title: 'Ответ для 401 кода',
    description: 'Возвращает ошибку и сообщение "Неавторизован"',
    required: [
        'error',
        'message'
    ],
)]
class Unauthorized extends EmptyResponse
{
    #[Property(property: 'error', type: 'boolean', example: true)]
    #[Property(property: 'message', type: 'string', example: 'Пользователь не авторизован')]
    protected static string $message = 'Пользователь не авторизован';
    protected static int $code = 401;
}