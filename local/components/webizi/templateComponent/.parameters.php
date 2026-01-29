<?

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}
/**
 * Параметры компонента, которые заполняются в визуальном редакторе, затем отображаются в массиве arParams
 *https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2132
 * */
$arComponentParameters = array(
    "PARAMETERS" => array(
        "EXAMPLE_PARAMETER" => array(
            "NAME" => "Параметр для компонента",
            "TYPE" => "STRING",
            "DEFAULT" => "Текст по умолчанию",
        ),
    )
);
?>