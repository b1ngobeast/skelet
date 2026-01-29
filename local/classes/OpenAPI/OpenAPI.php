<?php

namespace Webizi\OpenAPI;

use OpenApi\Attributes as OA;

#[OA\OpenApi(
    info: new OA\Info(
        version: '1.0.0',
        title: 'Тестовое апи для демонстрации'
    ),
    servers: [
        new OA\Server(
            url: 'http://skelet/api/',
            description: 'The local environment.'
        ),
        new OA\Server(
            url: 'https://example.com',
            description: 'The production server.'
        )
    ]
)]
#[OA\Tag(
    name: 'Books',
    description: 'Операции с книгами',
)]
class OpenApi
{
}
