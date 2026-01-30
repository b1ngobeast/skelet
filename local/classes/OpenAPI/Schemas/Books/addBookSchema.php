<?php

namespace Webizi\OpenAPI\Schemas\Books;

use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;

#[Schema(
    schema: "addBook",
    title: 'Добавление книги',
    description: 'Схема данных для добавления книги',
    required: [
        'UF_NAME',
        'UF_INVENTORY_NUMBER',
        'UF_SCHOOL',
    ]
)]

class addBookSchema
{
    #[Property(
        title: 'Наименование книги',
        description: 'Наименование книги',
        format: 'string',
    )]
    private string $UF_NAME;
    #[Property(
        title: 'Инвентарный номер',
        description: 'Инвентарный номер',
        format: 'string',
    )]
    private string $UF_INVENTORY_NUMBER;
    #[Property(
        title: 'Номер школы',
        description: 'Номер школы из таблицы Школы',
        format: 'int64',
    )]
    private int $UF_SCHOOL;
}