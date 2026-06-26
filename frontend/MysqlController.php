<?php

namespace frontend\controllers;

use Yii;
use yii\data\ArrayDataProvider;
use yii\grid\GridView;
use yii\web\Controller;
use yii\web\Response;

class MysqlController extends Controller
{
    /**
     * Отключаем валидацию CSRF для GET запросов
     */
    public $enableCsrfValidation = false;

    /**
     * Главная страница с формой и результатами
     */
    public function actionIndex()
    {
        $html = '<!DOCTYPE html>
        <html>
        <head>
            <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
            <style>
                body { padding: 20px; background: #f5f5f5; }
                .container { max-width: 1400px; }
                .sql-editor {                    background: white;                    padding: 20px;                    border-radius: 4px;                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);                    margin-bottom: 20px;               }
                .sql-editor textarea {                    width: 100%;                    font-family: "Courier New", monospace;                    font-size: 14px;                    padding: 10px;                    border: 1px solid #ddd;                    border-radius: 4px;                }
                .result-box {                    background: white;                    padding: 20px;                    border-radius: 4px;                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);                }                .result-header {                     margin-bottom: 15px;                    padding-bottom: 10px;                    border-bottom: 1px solid #eee;                }                .alert { margin-top: 20px; }                .btn-group { margin-top: 10px; }
                .export-buttons { margin-top: 15px; }
                .export-buttons .btn { margin-right: 10px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="row">
                    <div class="col-md-12">
                        <h1>SQL Query Executor</h1>
                        ' . $this->renderForm() . '
                    </div>
                </div>
            </div>
        </body>
        </html>';

        // Обработка GET запроса через URL
        if (isset($_GET['query']) && !empty($_GET['query'])) {
            $sql = urldecode($_GET['query']);

            // Для экспорта используем отдельный метод
            if (isset($_GET['export']) && $_GET['export'] == 'csv') {
                return $this->actionExportCsv($sql);
            }

            $html .= '<div class="row">
                        <div class="col-md-12">
                            <div class="result-box">
                                ' . $this->executeQuery($sql) . '
                            </div>
                        </div>
                      </div>';
        }

        return $html;
    }

    /**
     * Выполнение SQL запроса и вывод результатов
     */
    private function executeQuery($sql)
    {
        if (empty(trim($sql))) {
            return '<div class="alert alert-warning">⚠️ Пожалуйста, введите SQL запрос</div>';
        }

        try {
            $startTime = microtime(true);

            // Определяем тип запроса
            $sqlUpper = strtoupper(trim($sql));

            if (strpos($sqlUpper, 'SELECT') === 0) {
                // SELECT запрос
                $data = Yii::$app->db->createCommand($sql)->queryAll();
                $executionTime = round((microtime(true) - $startTime) * 1000, 2);

                if (empty($data)) {
                    return '<div class="alert alert-info">
                                <strong>✅ Запрос выполнен успешно!</strong><br>
                                Результатов не найдено.<br>
                                <small>⏱ Время выполнения: ' . $executionTime . ' мс</small>
                            </div>';
                }

                return $this->renderTable($data, $sql, $executionTime);

            } elseif (strpos($sqlUpper, 'INSERT') === 0 ||
                strpos($sqlUpper, 'UPDATE') === 0 ||
                strpos($sqlUpper, 'DELETE') === 0) {

                // Запросы модификации данных
                $affectedRows = Yii::$app->db->createCommand($sql)->execute();
                $executionTime = round((microtime(true) - $startTime) * 1000, 2);

                $message = '';
                if (strpos($sqlUpper, 'INSERT') === 0) {
                    $lastId = Yii::$app->db->getLastInsertID();
                    $message = "Вставлено строк: $affectedRows<br>Последний ID: $lastId";
                } elseif (strpos($sqlUpper, 'UPDATE') === 0) {
                    $message = "Обновлено строк: $affectedRows";
                } elseif (strpos($sqlUpper, 'DELETE') === 0) {
                    $message = "Удалено строк: $affectedRows";
                }

                return '<div class="alert alert-success">
                            <strong>✅ Запрос выполнен успешно!</strong><br>
                            ' . $message . '<br>
                            <small>⏱ Время выполнения: ' . $executionTime . ' мс</small>
                        </div>';

            } elseif (strpos($sqlUpper, 'CREATE') === 0 ||
                strpos($sqlUpper, 'ALTER') === 0 ||
                strpos($sqlUpper, 'DROP') === 0 ||
                strpos($sqlUpper, 'TRUNCATE') === 0) {

                // DDL запросы
                Yii::$app->db->createCommand($sql)->execute();
                $executionTime = round((microtime(true) - $startTime) * 1000, 2);

                return '<div class="alert alert-success">
                            <strong>✅ DDL запрос выполнен успешно!</strong><br>
                            <small>⏱ Время выполнения: ' . $executionTime . ' мс</small>
                        </div>';
            } else {
                // Другие запросы (SHOW, DESCRIBE, и т.д.)
                $result = Yii::$app->db->createCommand($sql)->queryAll();
                $executionTime = round((microtime(true) - $startTime) * 1000, 2);

                if (!empty($result)) {
                    return $this->renderTable($result, $sql, $executionTime);
                } else {
                    return '<div class="alert alert-info">
                                <strong>✅ Запрос выполнен успешно!</strong><br>
                                <small>⏱ Время выполнения: ' . $executionTime . ' мс</small>
                            </div>';
                }
            }

        } catch (\Exception $e) {
            return '<div class="alert alert-danger">
                        <strong>❌ Ошибка выполнения запроса!</strong><br>
                        ' . $e->getMessage() . '
                    </div>';
        }
    }

    /**
     * Отображение результатов в GridView
     */
    private function renderTable($data, $sql = null, $executionTime = null)
    {
        $html = '<div class="result-header">
                    <strong>📊 Результат запроса:</strong><br>
                    <span class="text-muted">📝 Найдено записей: ' . count($data) . '</span>';

        if ($executionTime) {
            $html .= '<br><span class="text-muted">⏱ Время выполнения: ' . $executionTime . ' мс</span>';
        }

        if ($sql) {
            $html .= '<br><span class="text-muted">🔍 SQL: ' . htmlspecialchars(substr($sql, 0, 200)) . '</span>';
        }

        $html .= '</div>';

        // Кнопки экспорта
        if (!empty($data) && $sql) {
            $encodedSql = urlencode($sql);
            $currentUrl = Yii::$app->urlManager->createUrl(['mysql', 'query' => $encodedSql]);
            $csvUrl = $currentUrl . '&export=csv';

            $html .= '<div class="export-buttons">
                        <a href="' . $csvUrl . '" class="btn btn-success btn-sm">
                            <span class="glyphicon glyphicon-download-alt"></span> 📥 Скачать CSV
                        </a>
                      </div>';
        }

        // Определяем колонки из ключей первой записи
        $columns = [];
        if (!empty($data)) {
            $firstRow = $data[0];
            foreach ($firstRow as $key => $value) {
                $columns[] = [
                    'attribute' => $key,
                    'label' => $key, // Используем реальное имя колонки без преобразования
                    'format' => 'html',
                    'value' => function ($model) use ($key) {
                        $value = $model[$key] ?? '';
                        // Обработка null значений
                        if ($value === null) {
                            return '<span class="text-muted"><em>NULL</em></span>';
                        }
                        // Обработка длинных текстов
                        if (is_string($value) && strlen($value) > 100) {
                            return '<span title="' . htmlspecialchars($value) . '">'
                                . htmlspecialchars(substr($value, 0, 100)) . '...</span>';
                        }
                        return htmlspecialchars($value);
                    }
                ];
            }
        }


        $html .= GridView::widget([
            'dataProvider' => new ArrayDataProvider([
                'allModels' => $data,
                'pagination' => false,
                'sort' => [
                    'attributes' => array_keys($data[0] ?? []),
                ],
            ]),
            'columns' => $columns,
            'tableOptions' => ['class' => 'table table-striped table-bordered table-hover'],
            'showOnEmpty' => true,
            'emptyText' => 'Нет данных для отображения',
            'summary' => 'Показаны записи <strong>{begin}</strong> - <strong>{end}</strong> из <strong>{totalCount}</strong>',
        ]);

        return $html;
    }

    /**
     * Экспорт результатов запроса в CSV файл
     */
    public function actionExportCsv($sql = null)
    {
        // Если SQL не передан через параметр, пробуем получить из POST или GET
        if (!$sql) {
            $sql = Yii::$app->request->get('query');
            if (!$sql) {
                $sql = Yii::$app->request->post('query');
            }
        }

        if (empty($sql)) {
            echo "Не указан SQL запрос для экспорта";
            return;
        }

        $sql = urldecode($sql);

        try {
            // Выполняем запрос
            $data = Yii::$app->db->createCommand($sql)->queryAll();

            if (empty($data)) {
                echo "Нет данных для экспорта";
                return;
            }

            // Формируем имя файла
            $filename = 'export_' . date('Y-m-d_H-i-s') . '.csv';

            // Устанавливаем заголовки для скачивания CSV файла
            Yii::$app->response->format = Response::FORMAT_RAW;
            Yii::$app->response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
            Yii::$app->response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
            Yii::$app->response->headers->set('Pragma', 'no-cache');
            Yii::$app->response->headers->set('Expires', '0');

            // Открываем выходной поток
            $output = fopen('php://output', 'w');

            // Добавляем BOM для корректного отображения UTF-8 в Excel
            fwrite($output, "\xEF\xBB\xBF");

            // Получаем заголовки колонок
            $headers = array_keys($data[0]);
            fputcsv($output, $headers);

            // Записываем данные
            foreach ($data as $row) {
                $csvRow = [];
                foreach ($headers as $header) {
                    $value = $row[$header] ?? '';
                    // Обработка null значений
                    if ($value === null) {
                        $value = 'NULL';
                    }
                    // Экранирование и обработка специальных символов
                    if (is_string($value)) {
                        $value = str_replace(["\r\n", "\n", "\r"], ' ', $value);
                    }
                    $csvRow[] = $value;
                }
                fputcsv($output, $csvRow);
            }

            fclose($output);

        } catch (\Exception $e) {
            echo "Ошибка при экспорте данных: " . $e->getMessage();
        }

        return;
    }

    /**
     * Отображение формы ввода SQL
     */
    private function renderForm()
    {
        $currentQuery = '';
        if (isset($_GET['query'])) {
            $currentQuery = urldecode($_GET['query']);
        }

        $actionUrl = Yii::$app->urlManager->createUrl(['mysql']);

        $form = '<div class="sql-editor">
                    <form action="' . $actionUrl . '" method="get" id="sqlForm">
                        <div class="form-group">
                            <label for="query">✏️ Введите SQL запрос:</label>
                            <textarea 
                                name="query" 
                                id="query" 
                                rows="6" 
                                class="form-control" 
                                placeholder="Например: SELECT * FROM users LIMIT 10">'
            . htmlspecialchars($currentQuery) .
            '</textarea>
                        </div>
                        <div class="btn-group">
                            <button type="submit" class="btn btn-primary">
                                <span class="glyphicon glyphicon-play"></span> 🚀 Выполнить
                            </button>
                        </div>
                    </form>
                </div>';

        return $form;
    }

    /**
     * Просмотр структуры таблицы
     */
    public function actionDescribe()
    {
        $table = Yii::$app->request->get('table');

        if (empty($table)) {
            echo "Не указано имя таблицы";
            return;
        }

        $sql = "DESCRIBE `$table`";
        $data = Yii::$app->db->createCommand($sql)->queryAll();

        // Проверяем, нужен ли экспорт
        if (isset($_GET['export']) && $_GET['export'] == 'csv') {
            return $this->actionExportCsv(urlencode($sql));
        }

        $html = '<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
                <div class="container" style="margin-top: 20px;">
                    <h2>Структура таблицы: ' . htmlspecialchars($table) . '</h2>';

        $html .= $this->renderTable($data, $sql);

        // Кнопка экспорта
        $encodedSql = urlencode($sql);
        $html .= '<div style="margin-top: 20px;">
                    <a href="' . Yii::$app->urlManager->createUrl(['mysql/describe', 'table' => $table, 'export' => 'csv']) . '" class="btn btn-success">
                        📥 Скачать CSV
                    </a>
                    <a href="' . Yii::$app->urlManager->createUrl(['mysql']) . '" class="btn btn-primary">← Назад</a>
                  </div>';
        $html .= '</div>';

        echo $html;
    }

    /**
     * Просмотр всех таблиц в базе данных
     */
    public function actionTables()
    {
        $sql = "SHOW TABLES";
        $tables = Yii::$app->db->createCommand($sql)->queryColumn();

        // Проверяем, нужен ли экспорт списка таблиц
        if (isset($_GET['export']) && $_GET['export'] == 'csv') {
            $data = [];
            foreach ($tables as $table) {
                $data[] = ['table_name' => $table];
            }

            // Временно сохраняем данные для экспорта
            $_GET['query'] = urlencode("SHOW TABLES");
            return $this->actionExportCsv(urlencode("SHOW TABLES"));
        }

        $html = '<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
                <div class="container" style="margin-top: 20px;">
                    <h2>Список таблиц в базе данных</h2>
                    
                    <div style="margin-bottom: 20px;">
                        <a href="' . Yii::$app->urlManager->createUrl(['mysql/tables', 'export' => 'csv']) . '" class="btn btn-success btn-sm">
                            📥 Скачать список таблиц (CSV)
                        </a>
                    </div>
                    
                    <div class="list-group">';

        foreach ($tables as $table) {
            $describeUrl = Yii::$app->urlManager->createUrl(['mysql/describe', 'table' => $table]);
            $selectUrl = Yii::$app->urlManager->createUrl(['mysql', 'query' => urlencode("SELECT * FROM `$table` LIMIT 50")]);

            $html .= '<div class="list-group-item">
                        <div class="row">
                            <div class="col-md-6">
                                <strong>📊 ' . htmlspecialchars($table) . '</strong>
                            </div>
                            <div class="col-md-6 text-right">
                                <a href="' . $describeUrl . '" class="btn btn-xs btn-info">Структура</a>
                                <a href="' . $selectUrl . '" class="btn btn-xs btn-primary">SELECT</a>
                            </div>
                        </div>
                      </div>';
        }

        $html .= '</div><br><a href="' . Yii::$app->urlManager->createUrl(['mysql']) . '" class="btn btn-primary">← Назад</a></div>';

        echo $html;
    }
}
