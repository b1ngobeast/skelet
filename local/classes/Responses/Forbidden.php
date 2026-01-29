<?php

namespace Webizi\Responses;

use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;

#[Schema(
    schema: "ForbiddenResponse",
    title: 'Ответ для 403 кода',
    description: 'Возвращает ошибку и сообщение "Отказано в доступе"',
    required: [
        'error',
        'message'
    ],
)]
class Forbidden extends EmptyResponse
{
    #[Property(property: 'error', type: 'boolean', example: true)]
    #[Property(property: 'message', type: 'string', example: 'Отказано в доступе')]
    protected static string $message = 'Отказано в доступе';
    protected static int $code = 403;
}