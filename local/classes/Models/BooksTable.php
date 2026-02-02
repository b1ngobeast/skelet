<?php

namespace Webizi\Models;

use Bitrix\Main\Entity;
use Bitrix\Main\Entity\BooleanField;
use Bitrix\Main\Entity\DataManager;
use Bitrix\Main\Entity\DatetimeField;
use Bitrix\Main\Entity\IntegerField;
use Bitrix\Main\Entity\ReferenceField;
use Bitrix\Main\Entity\StringField;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;

#[Schema(
    schema: "book",
    title: 'Модель Книга',
    description: 'Таблица книг с привязкой к школам',
    required: [
        'ID',
        'UF_NAME',
        'UF_INVENTORY_NUMBER',
        'UF_SCHOOL',
        'UF_ACTIVE',
    ],

)]
class BooksTable extends DataManager
{
    #[Property(
        title: 'ID',
        description: 'ID книги',
        format: 'int64',
    )]
    private int $ID;

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
        maxLength: 20,
        minLength: 1,
        pattern: '/^[0-9]+$/',
    )]
    private string $UF_INVENTORY_NUMBER;
    #[Property(
        title: 'ID школы',
        description: 'ID школы',
        format: 'int64',
    )]
    private int $UF_SCHOOL;
    #[Property(
        title: 'Статус активности',
        description: 'Статус активности, true - активно',
        format: 'bool',
    )]
    private bool $UF_ACTIVE;

    public static function getTableName(): string
    {
        return 'book';
    }

    public static function onBeforeAdd(Entity\Event $event): Entity\EventResult
    {
        $result = new Entity\EventResult;
        $data = $event->getParameter("fields");

        if(empty($data['UF_SCHOOL'])){
            $result->addError(new Entity\FieldError($event->getEntity()->getField('UF_SCHOOL'), 'Не заполнено поле "Школа"'));
            return $result;
        }
        if(empty(SchoolsTable::getById($data['UF_SCHOOL'])->fetchCollection()->count())){
            $result->addError(new Entity\FieldError($event->getEntity()->getField('UF_SCHOOL'), 'Указана неверная школа'));
        }

        return $result;
    }

    public static function getUfId(): string
    {
        return 'Books';
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
            new StringField('UF_INVENTORY_NUMBER', array(
                'format' => '/^[0-9]+$/',
                'required' => true,
                'unique' => true,
                'title' => 'Инвентарный номер',
            )),
            new StringField('UF_NAME', array(
                'title' => 'Наименование книги'
            )),
            new DatetimeField('UF_TIMESTAMP', array(
                'default_value' => function () {
                    return ((new \DateTime())->format('d.m.Y H:i:s'));
                },
                'title' => 'Дата и время создания'
            )),
            new BooleanField('UF_ACTIVE', array(
                'default_value' => true,
                'title' => 'Активность'
            )),
            /*Решил не делать валидацию, потому что надо проверять существование школы*/
            new IntegerField('UF_SCHOOL', [
                'required' => true,
                'title' => 'Школа'
            ]),


            new ReferenceField('SCHOOL', SchoolsTable::class, ['this.UF_SCHOOL' => 'ref.ID'], [
                'required' => true,
            ]),
        );
    }
}
