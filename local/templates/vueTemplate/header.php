<!DOCTYPE html>
<html>
<head>
    <?
    $APPLICATION->ShowHead();

    if ($USER->IsAdmin()) {
        $APPLICATION->ShowHeadStrings();
    }
    ?>
    <link rel="stylesheet" href="<?=SITE_TEMPLATE_PATH?> . /js/vue/vue-style.css">
</head>
<body>
<?$APPLICATION->ShowPanel(); ?>
<div id="app">