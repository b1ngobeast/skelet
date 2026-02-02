<?php

namespace Webizi\OpenAPI\Schemas\Responses;

use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;

#[Schema(
    schema: "SuccessCreateResponse",
    title: 'Ответ для 200 кода с ID нового элемента',
    description: 'Возвращает 200, сообщение об успешном действии и ID нового элемента',
    required: [
        'error',
        'message',
        'data',
    ],
)]
class SuccessCreate extends EmptyResponse
{
    #[Property(property: 'error', type: 'boolean', example: false)]
    #[Property(property: 'message', type: 'string', example: 'Создание прошло успешно')]
    #[Property(property: 'data', type: 'object', example: ["ID" => 1])]
    protected static string $message = 'Все прошло хорошо';
    protected static int $code = 200;
    protected static array $data = ['ID' => 1];
}