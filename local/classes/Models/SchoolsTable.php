<?php

namespace Webizi\Models;

use Bitrix\Main\Entity\DataManager;
use Bitrix\Main\Entity\IntegerField;
use Bitrix\Main\Entity\StringField;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;

#[Schema(
    schema: "school",
    title: 'Модель Школа',
)]
class SchoolsTable extends DataManager
{
    #[Property(
        title: 'ID',
        description: 'ID Школы',
        format: 'int64',
    )]
    private int $ID;

    #[Property(
        title: 'Наименование школы',
        description: 'Наименование школы',
        format: 'string',
    )]
    private string $UF_NAME;

    public static function getTableName(): string
    {
        return 'school';
    }

    public static function getUfId(): string
    {
        return 'School';
    }

    public static function getMap(): array
    {
        /**
         * Описание полей модели
         * https://docs.1c-bitrix.ru/pages/orm/orm-concepts.html
         */
        return array(
            new IntegerField('ID', array(
                'primary' => true,
                'autocomplete' => true,
                'title' => 'ID'
            )),
            new StringField('UF_NAME', array(
                'title' => 'Наименование школы'
            )),
        );
    }
}