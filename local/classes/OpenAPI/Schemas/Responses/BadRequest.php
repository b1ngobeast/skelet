<?php

namespace Webizi\OpenAPI\Schemas\Responses;

use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;

#[Schema(
    schema: "BadRequestResponse",
    title: 'Ответ для 400 кода',
    description: 'Возвращает ошибку и сообщение "Ошибка запроса"',
    required: [
        'error',
        'message'
    ],
)]
class BadRequest extends EmptyResponse
{
    #[Property(property: 'error', type: 'boolean', example: true)]
    #[Property(property: 'message', type: 'string', example: 'Ошибка запроса')]
    protected static string $message = 'Ошибка запроса';
    protected static int $code = 400;
}