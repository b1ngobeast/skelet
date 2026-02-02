<?php

namespace Webizi\OpenAPI\Schemas\Books;

use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;

#[Schema(
    schema: "deleteBook",
    title: 'Удаление книги',
    description: 'Схема данных для удаления книги',
    required: [
        'ID',
    ]
)]

class deleteBookSchema
{
    #[Property(
        title: 'ID',
        description: 'ID Книги для удаления',
        format: 'int64',
    )]
    private int $ID;
}