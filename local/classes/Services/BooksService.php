<?php

namespace Webizi\Services;

use OpenApi\Attributes\Get;
use OpenApi\Attributes\Items;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Parameter;
use OpenApi\Attributes\Post;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\RequestBody;
use OpenApi\Attributes\Response;
use OpenApi\Attributes\Schema;
use Webizi\Helpers\PaginationHelper;
use Webizi\Models\BooksTable;
use Webizi\Users\User;

class BooksService
{
    public static function getBooksForLimits(array $params): array
    {
        $page = $params['pagination']['page'] ?? 1;
        $pageSize = $params['pagination']['pageSize'] ?? 20;
        $filter = $params['filters'] ?? [];
        $order = $params['order'] ?? [];

        $nav = PaginationHelper::getNav([
            'pageSize' => $pageSize,
            'page' => $page
        ]);

        $user = new User();

        if ($user->checkNORMALUserGroups(['class', 'library'])) {
            $filter['=BUILD.ID'] = $user->getOrganizationAndClass()['organization']['BUILD_ID'];
        }

        if ($user->checkNORMALUserGroups(['director', 'admin'])) {
            $filter['=BUILD.ORG.ID'] = $user->getOrganizationAndClass()['organization']['ID'];
        }

        if ($user->checkNORMALUserGroups(['mun'])) {
            $filter['=BUILD.ORG.MUN.ID'] = $user->getMunicipality()['mun']['ID'];
        }

        $list = CatalogBookTable::getList(
            [
                'select' => [
                    'ID',
                    'UF_NAME',
                    'AUTHOR.UF_NAME'
                ],
                'filter' => $filter,
                'order' => $order,
                'limit' => $nav->getLimit(),
                'offset' => $nav->getOffset(),
                'data_doubling' => false,
                'count_total' => true,
            ]
        );

        while ($elem = $list->fetchObject()) {
            $result[] = [
                'ID' => (string)$elem->get('ID'),
                'UF_NAME' => $elem->get('UF_NAME'),
                'UF_AUTHOR' => $elem->getAuthor()?->get('UF_NAME'),
            ];
        }

        $nav->setRecordCount((int)$list->getCount());

        return [
            'pagination' => PaginationHelper::setResultNav($nav),
            'data' => $result ?? [],
            'error' => false,
        ];
    }

    #[Get(
        path: '/books/getList.php',
        summary: 'Получение книг с фильтрацией и пагинацией',
        tags: ['Books'],
        parameters: [
            new Parameter(
                name: 'UF_NAME',
                description: 'Наименование книги (Можно частично)',
                in: 'query',
                required: false,
                schema: new Schema(
                    type: 'string',
                ),
            ),
            new Parameter(
                name: 'UF_INVENTORY_NUMBER',
                description: 'Инвентарный номер',
                in: 'query',
                required: false,
                schema: new Schema(type: 'string'),
            ),

        ],
        responses: [
            new Response(
                response: 200,
                description: 'Успешное получение книг',
                content: [
                    new JsonContent(
                        properties: [
                            new Property('error', type: 'bool', example: false),
//                            new Property('data', type: 'array', items: new Items(ref: '#/components/schemas/book'))
                            new Property('data', type: 'array', items: new Items(ref: BooksTable::class)),
                        ]
                    )
                ]
            ),
            new Response(
                response: 400,
                description: 'Ошибка в веденных данных',
                content: new JsonContent(ref: "#/components/schemas/BadRequestResponse"),
            ),
            new Response(
                response: 401,
                description: 'Пользователь не авторизован',
                content: new JsonContent(ref: "#/components/schemas/UnauthorizedResponse")
            ),
            new Response(
                response: 403,
                description: 'Нет доступа',
                content: new JsonContent(ref: "#/components/schemas/ForbiddenResponse")
            ),
            new Response(
                response: 404,
                description: 'Страница не найдена',
                content: new JsonContent(ref: "#/components/schemas/NotFoundResponse")
            ),
        ]

    )]
    public static function getList(array $params = []): array
    {
        $filterFields = [
            'ID',
            'UF_NAME',
            'UF_INVENTORY_NUMBER',
            'UF_SCHOOL',
            'UF_ACTIVE',
        ];
        $orderList = [
            'ID',
            'UF_NAME',
            'UF_INVENTORY_NUMBER',
        ];

        $filter = $order = [];

        $list = BooksTable::getList(['select' => ['*']]);

        $result = [];
        while ($elem = $list->fetchObject()) {
            $result[] = [
                'ID' => (int)$elem->get('ID'),
                'UF_NAME' => (string)$elem->get('UF_NAME'),
                'UF_INVENTORY_NUMBER' => (string)$elem->get('UF_INVENTORY_NUMBER'),
                'UF_SCHOOL' => (int)$elem->get('UF_SCHOOL'),
                'UF_ACTIVE' => (bool)$elem->get('UF_ACTIVE'),
            ];
        }

        return $result;
    }

    #[Post(
        path: '/books/add.php',
        description: 'Добавление единичного экземпляра',
        summary: 'Добавление книги',
        requestBody: new RequestBody(
            description: 'Данные о книге',
            required: true,
            content: new JsonContent(ref: BooksTable::class),
        ),
        tags: ['Books'],
        responses: [
            new Response(
                response: 201,
                description: 'Успешное добавлении книги',
                content: new JsonContent()
            )
        ]
    )]
    //TODO Успешное добавление элемента ответ
    public static function add($addData): array
    {
        $result = BooksTable::add($addData);

        print "<pre>";
        print_r($result);
        print "</pre>";

        return ['ID' => 1];
    }
}
