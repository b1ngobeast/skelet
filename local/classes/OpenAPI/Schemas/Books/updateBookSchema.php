<?php

namespace Webizi\OpenAPI\Schemas\Books;

use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;

#[Schema(
    schema: "updateBook",
    title: 'Обновление книги',
    description: 'Схема данных для обновления книги',
    required: [
        'ID',
        'UF_NAME'
    ]
)]

class updateBookSchema
{
    #[Property(
        title: 'ID',
        description: 'ID Книги для обновления',
        format: 'int64',
    )]
    private int $ID;

    #[Property(
        title: 'UF_NAME',
        description: 'Наименование книги',
        format: 'string',
    )]
    private int $UF_NAME;
}