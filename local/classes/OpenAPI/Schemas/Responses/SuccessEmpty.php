<?php

namespace Webizi\OpenAPI\Schemas\Responses;

use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;

#[Schema(
    schema: "SuccessEmptyResponse",
    title: 'Ответ для 200 кода без данных',
    description: 'Возвращает 200 и сообщение об успешном действии',
    required: [
        'error',
        'message'
    ],
)]
class SuccessEmpty extends EmptyResponse
{
    #[Property(property: 'error', type: 'boolean', example: false)]
    #[Property(property: 'message', type: 'string', example: 'Действие успешно выполнено')]
    protected static string $message = 'Все прошло хорошо';
    protected static int $code = 200;
}