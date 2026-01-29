<?php

namespace Webizi\Responses;

use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;

#[Schema(
    schema: "NotFoundResponse",
    title: 'Ответ для 404 кода',
    description: 'Возвращает ошибку и сообщение "Страница не найдена"',
    required: [
        'error',
        'message'
    ],
)]
class NotFound extends EmptyResponse
{
    #[Property(property: 'error', type: 'boolean', example: true)]

    #[Property(property: 'message', type: 'string', example: 'Страница не найдена')]
    protected static string $message = 'Страница не найдена';
    protected static int $code = 404;
}