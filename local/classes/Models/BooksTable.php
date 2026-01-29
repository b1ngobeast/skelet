<?php

namespace Webizi\Models;

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
    title: 'Book Model',
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
    )]
    private string $UF_INVENTORY_NUMBER;
    #[Property(
        title: 'Номер школы',
        description: 'Номер школы из таблицы Школы',
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

//    public static function onBeforeAdd(Entity\Event $event)
//    {
//        $result = new Entity\EventResult;
//        $data = $event->getParameter("fields");
//
//        if (isset($data['UF_INVENTARY_NUMBER'])) {
//            try {
//                self::validateInventoryNumber($data['UF_INVENTARY_NUMBER']);
//            } catch (\InvalidArgumentException $e) {
//                $result->addError(new Entity\EntityError($e->getMessage()));
//            }
//        } else {
//            $result->addError(new Entity\EntityError('Не указан инвентарный номер'));
//        }
//
//        $enumList = ['UF_STATUS_POSITION', 'UF_STATUS_CONDITION'];
//        # получение ID полей, для добавления из XML_ID
//        $enums = EnumHelper::getListXmlId(self::getUfId(), $enumList, true);
//        if (empty($data['UF_STATUS_POSITION'])) {
//            $data['UF_STATUS_POSITION'] = 'in_library';
//        }
//        $modifyArray = [];
//        foreach ($data as $key => $value) {
//            if (isset($enums[$key])) {
//                if (empty($enums[$key][$value])) {
//                    $result->addError(
//                        new Entity\EntityError(
//                            'Невозможно обновить запись, ошибка в введенных данных'
//                        )
//                    );
//                }
//                $modifyArray[$key] = $enums[$key][$value];
//            }
//        }
//        $result->modifyFields($modifyArray);
//        # получение каталога
//
//        $catalog = CatalogBookTable::getByPrimary($data['UF_CATALOG_ID'], [
//            'select' => ['UF_BUILDING', 'ID']
//        ])->fetchObject();
//        if (empty($catalog)) {
//            $result->addError(
//                new Entity\EntityError(
//                    'Каталог не найден'
//                )
//            );
//        }
//        # проверка на уникальность инвентарного номера
//        $invCheck = BooksTable::getList([
//            'select' => ['UF_INVENTARY_NUMBER', 'CATALOG.UF_BUILDING'],
//            'filter' => ['UF_INVENTARY_NUMBER' => $data['UF_INVENTARY_NUMBER'], 'CATALOG.UF_BUILDING' => $catalog->get('UF_BUILDING')]
//        ])->fetch();
//        if (!empty($invCheck)) {
//            $result->addError(
//                new Entity\EntityError(
//                    'Книга с таким инвентарным номером уже существует'
//                )
//            );
//        }
//        return $result;
//    }

//    public static function validateInventoryNumber($number): void
//    {
//        if ($number === null || $number === '') {
//            throw new \InvalidArgumentException('Инвентарный номер не может быть пустым.');
//        }
//
//        if (!is_numeric($number) || (is_string($number) && !ctype_digit($number))) {
//            throw new \InvalidArgumentException('Инвентарный номер должен содержать только цифры.');
//        }
//    }

    public static function getUfId(): string
    {
        return 'Books';
    }

//    public static function onBeforeUpdate(Entity\Event $event)
//    {
//        $result = new Entity\EventResult;
//        $data = $event->getParameter("fields");
//
//        if (isset($data['UF_INVENTARY_NUMBER'])) {
//            try {
//                self::validateInventoryNumber($data['UF_INVENTARY_NUMBER']);
//            } catch (\InvalidArgumentException $e) {
//                $result->addError(new Entity\EntityError($e->getMessage()));
//                return $result;
//            }
//        }
//
//        $enumList = ['UF_STATUS_POSITION', 'UF_STATUS_CONDITION'];
//        # получение ID полей, для добавления из XML_ID
//        $enums = EnumHelper::getListXmlId(self::getUfId(), $enumList, true);
//        $modifyArray = [];
//        foreach ($data as $key => $value) {
//            if (isset($enums[$key])) {
//                if (empty($enums[$key][$value])) {
//                    $result->addError(
//                        new Entity\EntityError(
//                            'Невозможно обновить запись, ошибка в введенных данных'
//                        )
//                    );
//                }
//                $modifyArray[$key] = $enums[$key][$value];
//            }
//        }
//        //        $result->modifyFields([$key=>$enums[$key][$value]]);
//        $result->modifyFields($modifyArray);
//        //        $result->addError(new Entity\EntityError(
//        //            json_encode($result->getModified())
//        //        ));
//        return $result;
//    }

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
            )),
            new StringField('UF_INVENTORY_NUMBER', array(
                'format' => '/^[0-9]+$/',
                'required' => true,
                'unique' => true,
            )),
            new StringField('UF_NAME', array()),
            new DatetimeField('UF_TIMESTAMP', array(
                'default_value' => function () {
                    return ((new \DateTime())->format('d.m.Y H:i:s'));
                },
            )),
            new BooleanField('UF_ACTIVE', array(
                'default_value' => true,
            )),
            new ReferenceField('UF_SCHOOL', SchoolsTable::class, ['this.UF_SCHOOL' => 'ref.ID'], [
                'required' => true
            ]),
        );
    }
}
