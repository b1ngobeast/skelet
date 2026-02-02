<?php

namespace Webizi\Services;

use OpenApi\Attributes\Delete;
use OpenApi\Attributes\Get;
use OpenApi\Attributes\Items;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Parameter;
use OpenApi\Attributes\Post;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Put;
use OpenApi\Attributes\RequestBody;
use OpenApi\Attributes\Response;
use OpenApi\Attributes\Schema;
use Webizi\Helpers\PaginationHelper;
use Webizi\Models\BooksTable;

class BooksService
{
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
            new Parameter(
                name: 'page',
                description: 'Порядковый номер страницы',
                in: 'query',
                required: false,
                schema: new Schema(type: 'int64'),
            ),
            new Parameter(
                name: 'pageSize',
                description: 'Размер страницы',
                in: 'query',
                required: false,
                schema: new Schema(type: 'int64'),
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
                            new Property('data', type: 'array', items: new Items(ref: BooksTable::class)),
                            new Property(property: 'pagination', ref: '#/components/schemas/navSchema')
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
//    TODO доделать в гете навигацию
    public static function getList(
        array $params = []
    ): array {
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

        $page = $params['page'] ?? 1;
        $pageSize = $params['pageSize'] ?? 20;
        $nav = PaginationHelper::getNav([
            'pageSize' => $pageSize,
            'page' => $page
        ]);

        $list = BooksTable::getList([
            'select' => ['*'],
            'filter' => $filter,
            'order' => $order,
            'limit' => $nav->getLimit(),
            'offset' => $nav->getOffset(),
            'data_doubling' => false,
            'count_total' => true,
        ]);

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

        return ['error' => false, 'data' => $result ?? [], 'pagination' => PaginationHelper::setResultNav($nav)];
    }

    #[Post(
        path: '/books/add.php',
        description: 'Добавление единичного экземпляра',
        summary: 'Добавление книги',
        requestBody: new RequestBody(
            description: 'Данные о книге',
            required: true,
            content: new JsonContent(ref: '#/components/schemas/addBook'),
        ),
        tags: ['Books'],
        responses: [
            new Response(
                response: 201,
                description: 'Успешное добавлении книги',
                content: new JsonContent(ref: "#/components/schemas/SuccessCreateResponse"),
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
    public static function add($addData): array
    {
        try {
            $result = BooksTable::add($addData);

            if (!$result->isSuccess()) {
                throw new \Exception(implode(', ', $result->getErrorMessages()), 400);
            }
        } catch (\Exception $e) {
            http_response_code($e->getCode());
            return ['error' => true, 'message' => $e->getMessage()];
        }

        return ['error' => false, 'data' => ['ID' => $result->getId()], 'message' => 'Книга успешно создана'];
    }

    #[Delete(
        path: '/books/delete.php',
        description: 'Удаление книги',
        requestBody: new RequestBody(
            description: 'Данные о книге',
            required: true,
            content: new JsonContent(ref: '#/components/schemas/deleteBook'),
        ),
        tags: ['Books'],
        responses: [
            new Response(
                response: 200,
                description: 'Действие выполнено успешно',
                content: new JsonContent(ref: "#/components/schemas/SuccessEmptyResponse"),
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
    public static function delete($id): array
    {
        try {
            $result = BooksTable::delete($id);
            if (!$result->isSuccess()) {
                throw new \Exception(implode(', ', $result->getErrorMessages()), 400);
            }
        } catch (\Exception $e) {
            http_response_code($e->getCode());
            return ['error' => true, 'message' => $e->getMessage()];
        }

        return ['error' => false, 'message' => 'Книга успешно удалена'];
    }

    #[Put(
        path: '/books/update.php',
        description: 'Обновление наименования книги',
        requestBody: new RequestBody(
            description: 'Данные для обновления книги',
            required: true,
            content: new JsonContent(ref: '#/components/schemas/updateBook'),
        ),
        tags: ['Books'],
        responses: [
            new Response(
                response: 200,
                description: 'Действие выполнено успешно',
                content: new JsonContent(ref: "#/components/schemas/SuccessEmptyResponse"),
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
    public static function update($data): array
    {
        try {
            $result = BooksTable::update($data['ID'], $data);
            if (!$result->isSuccess()) {
                throw new \Exception(implode(', ', $result->getErrorMessages()), 400);
            }
        } catch (\Exception $e) {
            http_response_code($e->getCode());
            return ['error' => true, 'message' => $e->getMessage()];
        }
        return ['error' => false, 'message' => 'Данные успешно обновлены'];
    }
}
