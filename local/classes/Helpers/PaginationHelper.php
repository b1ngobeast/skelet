<?php

namespace Webizi\Helpers;

use Bitrix\Main\UI\PageNavigation;

class PaginationHelper
{
    public static function getNav(array $data = [])
    {
        if (empty($data['pageSize']) || (int)$data['pageSize'] > 150) {
            $data['pageSize'] = 20;
        }
        if (empty($data['page'])) $data['page'] = 1;
        $nav = new PageNavigation('pagination');
        $nav->setPageSize($data['pageSize']);
        $nav->setCurrentPage($data['page']);
        return $nav;
    }

    public static function setResultNav(PageNavigation $nav): array
    {
        return [
            'count' => (int)$nav->getRecordCount(),
            'pages' => $nav->getPageCount(),
            'current_page' => $nav->getCurrentPage(),
        ];
    }
}
