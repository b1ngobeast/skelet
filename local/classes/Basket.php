<?php

namespace Webizi;

use Webizi\Books\Book;
use Webizi\Books\Catalog;
use Webizi\Helpers\EnumHelper;
use Webizi\Models\BasketsTable;
use Webizi\Models\BooksTable;
use Webizi\Users\User;

class Basket
{
    public static function addBook($book, $count=1, User $user)
    {
        # получаем есть ли книга уже в корзине
        $basket = BasketsTable::getList([
            'select' => ['*'],
            'filter' => ['UF_USER' => $user->getUserID(), 'UF_CATALOG' => $book]
        ])->fetch();
        if (!empty($basket)) throw new \Exception("Книга уже добавлена в портфель", 400);
        # проверка существования каталога
        $catalog = Catalog::checkCatalog($book,true);
        if (!$catalog) throw new \Exception("Книга больше недоступна", 400);
        $result = BasketsTable::add([
            'UF_USER' => $user->getUserID(),
            'UF_CATALOG' => $book,
            'UF_QUANTITY' => $count,
        ]);
        if (!$result->isSuccess()) throw new \Exception('Ошибка при добавлении в портфель', 400);
        return ['error' => 'false'];
    }

    public static function getBasket(User $user)
    {
        # получаем есть ли книга уже в корзине
        $basket = BasketsTable::getList([
            'select' => [
                '*',
                'CATALOG',
                'CATALOG.AUTHOR',
                'CATALOG.SIGN',
                'CATALOG.BOOK',
                'CATALOG.GENRE',
                'CATALOG.UF_PICTURE'
            ],
            'filter' => ['UF_USER' => $user->getUserID()]
        ])->fetchCollection();
        if (empty($basket)) return ['data' => []];

        $result = [
            'data'=>[]
        ];
        foreach ($basket as $basketItem) {
            # проверка книг в библиотеке
//            $haveBooks = false;
//            if (!empty($basketItem->getCatalog()->getBook())) {
//                foreach ($basketItem->getCatalog()->getBook() as $book) {
//                    if ($statusBook['in_library'] == $book->get('UF_STATUS_POSITION')) {
//                        $haveBooks=true;
//                        break;
//                    }
//                }
//            }
            $haveBooks = Book::checkBookInLibraryFromCatalog($basketItem->getcatalog());
            $result['data'][] = [
                'AUTHOR_NAME'=>$basketItem->getCatalog()->getAuthor()?->get('UF_NAME'),
                'SIGN'=>$basketItem->getCatalog()?->getSign()?->get('UF_CATEGORY'),
                'GENRE'=>$basketItem->getCatalog()?->getGenre()?->get('UF_NAME'),
                'UF_CATALOG'=>$basketItem->getCatalog()?->getId(),
                'UF_NAME'=>$basketItem->getCatalog()?->get('UF_NAME'),
                'HAVE_BOOKS'=>$haveBooks,
                'UF_PICTURE'=>!empty($basketItem->getCatalog()?->get('UF_PICTURE')) ? $_SERVER['SERVER_NAME'] . \CFile::getPath($basketItem->getCatalog()->get('UF_PICTURE')) : ""
            ];
        }

//        $result['data'] = $basket;
        return $result;
    }

    public static function deleteBook($book, User $user)
    {
        # получаем запись
        $filter = [];
        $filter['UF_USER'] = $user->getUserID();
        if ($book != 'all') {
            $filter['UF_CATALOG'] = $book;
        }
        $basket = BasketsTable::getList([
            'select' => ['*'],
            'filter' => $filter
        ])->fetchAll();
        if (empty($basket)) {
//            throw new \Exception("Невозможно удалить элемент", 400);
            return ['error'=>false];
        }
        foreach ($basket as $basketItem) {
            $result = BasketsTable::delete($basketItem['ID']);
            if (!$result->isSuccess()) throw new \Exception('Ошибка при удалении элемента', 400);
        }
        return ['error' => 'false'];
    }

    public static function changeCount($book, $newCount, User $user)
    {
        # получаем запись
        $basket = BasketsTable::getList([
            'select' => ['*'],
            'filter' => ['UF_USER' => $user->getUserID(), 'UF_CATALOG' => $book]
        ])->fetch();
        $listStatusPart = EnumHelper::getListXmlId(BooksTable::getUfId(), ['UF_STATUS_POSITION'], false);
        $listConditionPart = EnumHelper::getListXmlId(BooksTable::getUfId(), ['UF_STATUS_CONDITION'], false);
        $booksCount = BooksTable::getList([
            'select' => ['ID'],
            'filter' => [
                'UF_CATALOG_ID' => $book,
                'UF_STATUS_POSITION' => $listStatusPart['in_library'],
                'UF_STATUS_CONDITION' => [$listConditionPart['damaged'], $listConditionPart['new']]
            ],
            'count_total' => true,
        ])->getCount();
        if (empty($basket)) throw new \Exception("Книга не добавлена в портфель", 400);
        $result = BasketsTable::update($basket['ID'], ['UF_QUANTITY' => $newCount]);
        if (!$result->isSuccess()) throw new \Exception('Ошибка при изменении количества', 400);
        return ['error' => 'false', 'books_count' => $booksCount];
    }

}