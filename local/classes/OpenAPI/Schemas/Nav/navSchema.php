<?php

namespace Webizi\OpenAPI\Schemas\Nav;

use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;

#[Schema(
    schema: "navSchema",
    title: 'Пагинация',
    description: 'Пагинация',
    required: [
        'current_page',
        'pages',
        'count'
    ]
)]

class navSchema
{
    #[Property(
        title: 'count',
        description: 'Общее количество записей',
        format: 'int64',
    )]
    private int $count;

    #[Property(
        title: 'current_page',
        description: 'Текущая страница',
        format: 'int64',
    )]
    private int $current_page;

    #[Property(
        title: 'pages',
        description: 'Общее количество страниц',
        format: 'int64',
    )]
    private int $pages;
}