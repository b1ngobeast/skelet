<?php

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php');

$APPLICATION->SetTitle('Документация API');
?>
    <div>
        <button>
            <a style="text-decoration:none; color: #000000;" href="/local/swagger/generate.php">Обновить сваггер</a>
        </button>
    </div>
    <!-- Контейнер для Swagger -->
    <div id="swagger-ui" style="margin: 20px;"></div>

    <!-- Подключаем Swagger -->
    <link rel="stylesheet" href="/local/swagger/swagger-ui.css"/>
    <script src="/local/swagger/swagger-ui-bundle.js" charset="UTF-8"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof SwaggerUIBundle !== 'undefined') {
                const ui = SwaggerUIBundle({
                    url: '/local/swagger/openapi.yaml',
                    dom_id: '#swagger-ui',
                    presets: [
                        SwaggerUIBundle.presets.apis,
                        SwaggerUIBundle.presets.standalone
                    ],
                    deepLinking: true,
                    showExtensions: true
                });
            }
        });
    </script>

<?php
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php');
