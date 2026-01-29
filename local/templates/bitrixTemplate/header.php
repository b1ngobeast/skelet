<?

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Page\Asset;

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?
        $APPLICATION->ShowTitle() ?></title>
    <?
    /*Добавление стилей и JS файлов из шаблона*/
    $asset = Asset::getInstance();
    $asset->addCss(SITE_TEMPLATE_PATH . '/path/to/style.css');
    $asset->addJs(SITE_TEMPLATE_PATH . "/path/to/script.js");
    ?>
    <?
    $APPLICATION->ShowHead();
    ?>
</head>
<body>
<div id="panel">
    <?
    $APPLICATION->ShowPanel(); ?>
</div>
<header>
    <!--Хедер страницы-->
</header>
<main>
    <!--    Основной контент страницы -->