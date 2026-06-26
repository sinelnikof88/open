<?php

namespace frontend\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;

class ExplorerController extends Controller
{
    /**
     * Отключаем валидацию CSRF для GET запросов
     */
    public $enableCsrfValidation = false;

    /**
     * Базовый путь для работы с файлами (можно настроить)
     */
    private $basePath = '@app';

    /**
     * Главная страница - просмотр файлов и папок
     */
    public function actionIndex()
    {
        $path = Yii::$app->request->get('path', '');
        $fullPath = $this->getFullPath($path);

        // Проверяем существование пути
        if (!file_exists($fullPath)) {
            return $this->renderError("Путь не существует: " . $fullPath);
        }

        // Если это файл - показываем содержимое
        if (is_file($fullPath)) {
            return $this->viewFile($fullPath, $path);
        }

        // Если это папка - показываем содержимое
        return $this->viewDirectory($fullPath, $path);
    }

    /**
     * Редактирование файла
     */
    public function actionEdit()
    {
        $path = Yii::$app->request->get('path', '');
        $fullPath = $this->getFullPath($path);

        if (!file_exists($fullPath) || !is_file($fullPath)) {
            return $this->renderError("Файл не найден: " . $fullPath);
        }

        // Проверяем права на запись
        if (!is_writable($fullPath)) {
            return $this->renderError("Нет прав на запись в файл: " . $fullPath);
        }

        // Обработка сохранения файла
        if (Yii::$app->request->isPost) {
            $content = Yii::$app->request->post('content');
            if (file_put_contents($fullPath, $content) !== false) {
                Yii::$app->session->setFlash('success', 'Файл успешно сохранён!');
            } else {
                Yii::$app->session->setFlash('error', 'Ошибка при сохранении файла!');
            }
            return $this->redirect(['index', 'path' => $path]);
        }

        // Получаем содержимое файла
        $content = file_get_contents($fullPath);
        $extension = pathinfo($fullPath, PATHINFO_EXTENSION);
        $language = $this->getLanguageByExtension($extension);

        // Определяем тип файла для отображения
        $isBinary = $this->isBinaryFile($fullPath);

        $html = $this->renderHeader();
        $html .= '<div class="container">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="sql-editor">
                                <h2>✏️ Редактирование файла</h2>
                                <p><strong>Файл:</strong> ' . htmlspecialchars($path) . '</p>
                                <p><strong>Размер:</strong> ' . $this->formatFileSize(filesize($fullPath)) . '</p>';

        if ($isBinary) {
            $html .= '<div class="alert alert-warning">
                            ⚠️ Это бинарный файл. Редактирование может повредить его содержимое.
                        </div>';
        }

        $html .= '<form action="' . Yii::$app->urlManager->createUrl(['explorer/edit', 'path' => $path]) . '" method="post">
                            ' . Yii::$app->request->getCsrfTokenFromHeader() . '
                            <div class="form-group">
                                <textarea 
                                    name="content" 
                                    id="content" 
                                    rows="20" 
                                    class="form-control" 
                                    style="font-family: \'Courier New\', monospace; font-size: 13px;"
                                    spellcheck="false">'
            . htmlspecialchars($content) .
            '</textarea>
                            </div>
                            <div class="btn-group">
                                <button type="submit" class="btn btn-primary">
                                    <span class="glyphicon glyphicon-save"></span> 💾 Сохранить
                                </button>
                                <a href="' . Yii::$app->urlManager->createUrl(['explorer', 'path' => dirname($path)]) . '" class="btn btn-default">
                                    ← Назад
                                </a>
                            </div>
                        </form>
                        </div>
                    </div>
                </div>
            </div>';

        $html .= $this->renderFooter();
        return $html;
    }

    /**
     * Скачивание файла
     */
    public function actionDownload()
    {
        $path = Yii::$app->request->get('path', '');
        $fullPath = $this->getFullPath($path);

        if (!file_exists($fullPath) || !is_file($fullPath)) {
            return $this->renderError("Файл не найден");
        }

        return Yii::$app->response->sendFile($fullPath, basename($fullPath));
    }

    /**
     * Создание новой папки
     */
    public function actionCreateFolder()
    {
        $path = Yii::$app->request->get('path', '');
        $fullPath = $this->getFullPath($path);

        if (!file_exists($fullPath) || !is_dir($fullPath)) {
            return $this->renderError("Папка не найдена");
        }

        if (Yii::$app->request->isPost) {
            $folderName = trim(Yii::$app->request->post('folder_name'));
            if (empty($folderName)) {
                Yii::$app->session->setFlash('error', 'Введите имя папки');
            } else {
                $newFolderPath = $fullPath . DIRECTORY_SEPARATOR . $folderName;
                if (file_exists($newFolderPath)) {
                    Yii::$app->session->setFlash('error', 'Папка с таким именем уже существует');
                } else {
                    if (mkdir($newFolderPath, 0755)) {
                        Yii::$app->session->setFlash('success', 'Папка успешно создана');
                    } else {
                        Yii::$app->session->setFlash('error', 'Ошибка при создании папки');
                    }
                }
            }
            return $this->redirect(['index', 'path' => $path]);
        }

        $html = $this->renderHeader();
        $html .= '<div class="container">
                    <div class="row">
                        <div class="col-md-6 col-md-offset-3">
                            <div class="sql-editor">
                                <h2>📁 Создание новой папки</h2>
                                <p><strong>Путь:</strong> ' . htmlspecialchars($path) . '</p>
                                
                                <form action="' . Yii::$app->urlManager->createUrl(['explorer/create-folder', 'path' => $path]) . '" method="post">
                                    ' . Yii::$app->request->getCsrfTokenFromHeader() . '
                                    <div class="form-group">
                                        <label for="folder_name">Имя папки:</label>
                                        <input type="text" name="folder_name" id="folder_name" class="form-control" required>
                                    </div>
                                    <div class="btn-group">
                                        <button type="submit" class="btn btn-success">Создать</button>
                                        <a href="' . Yii::$app->urlManager->createUrl(['explorer', 'path' => $path]) . '" class="btn btn-default">Отмена</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>';

        $html .= $this->renderFooter();
        return $html;
    }

    /**
     * Создание нового файла
     */
    public function actionCreateFile()
    {
        $path = Yii::$app->request->get('path', '');
        $fullPath = $this->getFullPath($path);

        if (!file_exists($fullPath) || !is_dir($fullPath)) {
            return $this->renderError("Папка не найдена");
        }

        if (Yii::$app->request->isPost) {
            $fileName = trim(Yii::$app->request->post('file_name'));
            $content = Yii::$app->request->post('content', '');

            if (empty($fileName)) {
                Yii::$app->session->setFlash('error', 'Введите имя файла');
            } else {
                $newFilePath = $fullPath . DIRECTORY_SEPARATOR . $fileName;
                if (file_exists($newFilePath)) {
                    Yii::$app->session->setFlash('error', 'Файл с таким именем уже существует');
                } else {
                    if (file_put_contents($newFilePath, $content) !== false) {
                        Yii::$app->session->setFlash('success', 'Файл успешно создан');
                    } else {
                        Yii::$app->session->setFlash('error', 'Ошибка при создании файла');
                    }
                }
            }
            return $this->redirect(['index', 'path' => $path]);
        }

        $html = $this->renderHeader();
        $html .= '<div class="container">
                    <div class="row">
                        <div class="col-md-8 col-md-offset-2">
                            <div class="sql-editor">
                                <h2>📄 Создание нового файла</h2>
                                <p><strong>Путь:</strong> ' . htmlspecialchars($path) . '</p>
                                
                                <form action="' . Yii::$app->urlManager->createUrl(['explorer/create-file', 'path' => $path]) . '" method="post">
                                    ' . Yii::$app->request->getCsrfTokenFromHeader() . '
                                    <div class="form-group">
                                        <label for="file_name">Имя файла:</label>
                                        <input type="text" name="file_name" id="file_name" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="content">Содержимое (опционально):</label>
                                        <textarea name="content" id="content" rows="5" class="form-control" style="font-family: \'Courier New\', monospace;"></textarea>
                                    </div>
                                    <div class="btn-group">
                                        <button type="submit" class="btn btn-success">Создать</button>
                                        <a href="' . Yii::$app->urlManager->createUrl(['explorer', 'path' => $path]) . '" class="btn btn-default">Отмена</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>';

        $html .= $this->renderFooter();
        return $html;
    }

    /**
     * Удаление файла или папки
     */
    public function actionDelete()
    {
        $path = Yii::$app->request->get('path', '');
        $fullPath = $this->getFullPath($path);

        if (!file_exists($fullPath)) {
            Yii::$app->session->setFlash('error', 'Путь не найден');
            return $this->redirect(['index']);
        }

        // Защита от удаления корневых папок
        if ($this->isProtectedPath($fullPath)) {
            Yii::$app->session->setFlash('error', 'Этот путь защищён от удаления');
            return $this->redirect(['index', 'path' => dirname($path)]);
        }

        if (Yii::$app->request->isPost) {
            $confirm = Yii::$app->request->post('confirm', false);
            if ($confirm === 'yes') {
                $success = $this->deletePath($fullPath);
                if ($success) {
                    Yii::$app->session->setFlash('success', 'Удаление выполнено успешно');
                } else {
                    Yii::$app->session->setFlash('error', 'Ошибка при удалении');
                }
                return $this->redirect(['index', 'path' => dirname($path)]);
            }
        }

        $html = $this->renderHeader();
        $html .= '<div class="container">
                    <div class="row">
                        <div class="col-md-6 col-md-offset-3">
                            <div class="sql-editor">
                                <h2>⚠️ Подтверждение удаления</h2>
                                <div class="alert alert-danger">
                                    <strong>Вы уверены, что хотите удалить?</strong><br>
                                    <strong>Путь:</strong> ' . htmlspecialchars($path) . '<br>
                                    <strong>Тип:</strong> ' . (is_dir($fullPath) ? 'Папка' : 'Файл') . '<br>
                                    <strong>Размер:</strong> ' . $this->formatFileSize($this->getSize($fullPath)) . '
                                </div>
                                
                                <form action="' . Yii::$app->urlManager->createUrl(['explorer/delete', 'path' => $path]) . '" method="post">
                                    ' . Yii::$app->request->getCsrfTokenFromHeader() . '
                                    <input type="hidden" name="confirm" value="yes">
                                    <div class="btn-group">
                                        <button type="submit" class="btn btn-danger">🗑️ Удалить</button>
                                        <a href="' . Yii::$app->urlManager->createUrl(['explorer', 'path' => dirname($path)]) . '" class="btn btn-default">Отмена</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>';

        $html .= $this->renderFooter();
        return $html;
    }

    /**
     * Скачивание папки как ZIP архива
     */
    public function actionDownloadFolder()
    {
        $path = Yii::$app->request->get('path', '');
        $fullPath = $this->getFullPath($path);

        if (!file_exists($fullPath) || !is_dir($fullPath)) {
            return $this->renderError("Папка не найдена");
        }

        // Проверяем наличие расширения zip
        if (!class_exists('ZipArchive')) {
            return $this->renderError("Расширение ZIP не установлено на сервере");
        }

        $zip = new \ZipArchive();
        $zipFileName = tempnam(sys_get_temp_dir(), 'folder_') . '.zip';

        if ($zip->open($zipFileName, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return $this->renderError("Не удалось создать ZIP архив");
        }

        $this->addFolderToZip($fullPath, $zip, basename($fullPath));
        $zip->close();

        return Yii::$app->response->sendFile($zipFileName, basename($fullPath) . '.zip');
    }

    /**
     * Отображение содержимого папки
     */
    private function viewDirectory($fullPath, $path)
    {
        $items = scandir($fullPath);
        if ($items === false) {
            return $this->renderError("Не удалось прочитать папку");
        }

        $files = [];
        $folders = [];

        foreach ($items as $item) {
            if ($item == '.' || $item == '..') continue;

            $itemPath = $fullPath . DIRECTORY_SEPARATOR . $item;
            $isDir = is_dir($itemPath);

            $data = [
                'name' => $item,
                'path' => $path ? $path . '/' . $item : $item,
                'is_dir' => $isDir,
                'size' => $isDir ? '-' : $this->formatFileSize(filesize($itemPath)),
                'modified' => date('Y-m-d H:i:s', filemtime($itemPath)),
                'permissions' => substr(sprintf('%o', fileperms($itemPath)), -4),
            ];

            if ($isDir) {
                $folders[] = $data;
            } else {
                $files[] = $data;
            }
        }

        // Сортируем папки и файлы по имени
        usort($folders, function($a, $b) { return strcmp($a['name'], $b['name']); });
        usort($files, function($a, $b) { return strcmp($a['name'], $b['name']); });

        $allItems = array_merge($folders, $files);

        $html = $this->renderHeader();

        // Выводим flash сообщения
        $flashMessages = Yii::$app->session->getAllFlashes();
        foreach ($flashMessages as $type => $message) {
            $alertClass = $type === 'success' ? 'alert-success' : 'alert-danger';
            $html .= '<div class="container"><div class="alert ' . $alertClass . '">' . $message . '</div></div>';
        }

        $html .= '<div class="container">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="sql-editor">
                                <h2>📂 ' . ($path ?: 'Корневая папка') . '</h2>
                                
                                <div class="row" style="margin-bottom: 15px;">
                                    <div class="col-md-6">
                                        <p><strong>Путь:</strong> ' . htmlspecialchars($path ?: '/') . '</p>
                                        <p><strong>Всего элементов:</strong> ' . count($allItems) . '</p>
                                    </div>
                                    <div class="col-md-6 text-right">
                                        <div class="btn-group">
                                            <a href="' . Yii::$app->urlManager->createUrl(['explorer/create-folder', 'path' => $path]) . '" class="btn btn-primary btn-sm">
                                                📁 Создать папку
                                            </a>
                                            <a href="' . Yii::$app->urlManager->createUrl(['explorer/create-file', 'path' => $path]) . '" class="btn btn-success btn-sm">
                                                📄 Создать файл
                                            </a>
                                            ' . ($path ? '<a href="' . Yii::$app->urlManager->createUrl(['explorer/download-folder', 'path' => $path]) . '" class="btn btn-warning btn-sm">
                                                📦 Скачать папку (ZIP)
                                            </a>' : '') . '
                                        </div>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-striped table-bordered table-hover">
                                        <thead>
                                            <tr>
                                                <th>Имя</th>
                                                <th>Тип</th>
                                                <th>Размер</th>
                                                <th>Изменён</th>
                                                <th>Права</th>
                                                <th>Действия</th>
                                            </tr>
                                        </thead>
                                        <tbody>';

        // Ссылка на родительскую папку
        if ($path) {
            $parentPath = dirname($path);
            $parentPath = $parentPath == '.' ? '' : $parentPath;
            $html .= '<tr>
                            <td><strong><a href="' . Yii::$app->urlManager->createUrl(['explorer', 'path' => $parentPath]) . '">..</a></strong></td>
                            <td>Папка</td>
                            <td>-</td>
                            <td>-</td>
                            <td>-</td>
                            <td>-</td>
                        </tr>';
        }

        foreach ($allItems as $item) {
            $icon = $item['is_dir'] ? '📁' : '📄';
            $url = Yii::$app->urlManager->createUrl(['explorer', 'path' => $item['path']]);

            $html .= '<tr>
                            <td>
                                <a href="' . $url . '">' . $icon . ' ' . htmlspecialchars($item['name']) . '</a>
                            </td>
                            <td>' . ($item['is_dir'] ? 'Папка' : 'Файл') . '</td>
                            <td>' . $item['size'] . '</td>
                            <td>' . $item['modified'] . '</td>
                            <td>' . $item['permissions'] . '</td>
                            <td>';

            if (!$item['is_dir']) {
                // Действия для файлов
                $editUrl = Yii::$app->urlManager->createUrl(['explorer/edit', 'path' => $item['path']]);
                $downloadUrl = Yii::$app->urlManager->createUrl(['explorer/download', 'path' => $item['path']]);
                $deleteUrl = Yii::$app->urlManager->createUrl(['explorer/delete', 'path' => $item['path']]);

                $html .= '<div class="btn-group btn-group-xs">
                                    <a href="' . $editUrl . '" class="btn btn-primary">✏️</a>
                                    <a href="' . $downloadUrl . '" class="btn btn-success">⬇️</a>
                                    <a href="' . $deleteUrl . '" class="btn btn-danger" onclick="return confirm(\'Удалить файл?\')">🗑️</a>
                                </div>';
            } else {
                // Действия для папок
                $deleteUrl = Yii::$app->urlManager->createUrl(['explorer/delete', 'path' => $item['path']]);
                $downloadFolderUrl = Yii::$app->urlManager->createUrl(['explorer/download-folder', 'path' => $item['path']]);

                $html .= '<div class="btn-group btn-group-xs">
                                    <a href="' . $downloadFolderUrl . '" class="btn btn-warning">📦</a>
                                    <a href="' . $deleteUrl . '" class="btn btn-danger" onclick="return confirm(\'Удалить папку?\')">🗑️</a>
                                </div>';
            }

            $html .= '</td>
                        </tr>';
        }

        $html .= '</tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>';

        $html .= $this->renderFooter();
        return $html;
    }

    /**
     * Отображение содержимого файла
     */
    private function viewFile($fullPath, $path)
    {
        $extension = pathinfo($fullPath, PATHINFO_EXTENSION);
        $isBinary = $this->isBinaryFile($fullPath);
        $fileSize = filesize($fullPath);

        $html = $this->renderHeader();

        if ($isBinary) {
            // Для бинарных файлов показываем только информацию
            $html .= '<div class="container">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="sql-editor">
                                    <h2>📄 ' . htmlspecialchars($path) . '</h2>
                                    <div class="alert alert-warning">
                                        <strong>⚠️ Бинарный файл</strong><br>
                                        Просмотр содержимого недоступен.
                                    </div>
                                    <p><strong>Размер:</strong> ' . $this->formatFileSize($fileSize) . '</p>
                                    <p><strong>Расширение:</strong> ' . htmlspecialchars($extension) . '</p>
                                    <a href="' . Yii::$app->urlManager->createUrl(['explorer', 'path' => dirname($path)]) . '" class="btn btn-default">← Назад</a>
                                </div>
                            </div>
                        </div>
                    </div>';
        } else {
            // Для текстовых файлов показываем содержимое
            $content = file_get_contents($fullPath);
            $language = $this->getLanguageByExtension($extension);

            $html .= '<div class="container">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="sql-editor">
                                    <h2>📄 ' . htmlspecialchars($path) . '</h2>
                                    <p><strong>Размер:</strong> ' . $this->formatFileSize($fileSize) . '</p>
                                    <p><strong>Расширение:</strong> ' . htmlspecialchars($extension) . '</p>
                                    
                                    <div class="btn-group" style="margin-bottom: 15px;">
                                        <a href="' . Yii::$app->urlManager->createUrl(['explorer/edit', 'path' => $path]) . '" class="btn btn-primary">✏️ Редактировать</a>
                                        <a href="' . Yii::$app->urlManager->createUrl(['explorer/download', 'path' => $path]) . '" class="btn btn-success">⬇️ Скачать</a>
                                        <a href="' . Yii::$app->urlManager->createUrl(['explorer', 'path' => dirname($path)]) . '" class="btn btn-default">← Назад</a>
                                    </div>
                                    
                                    <div style="background: #f8f9fa; padding: 15px; border-radius: 4px; border: 1px solid #ddd;">
                                        <pre style="margin: 0; white-space: pre-wrap; word-wrap: break-word;">' . htmlspecialchars($content) . '</pre>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>';
        }

        $html .= $this->renderFooter();
        return $html;
    }

    /**
     * Вспомогательные методы
     */

    private function getFullPath($path)
    {
        $basePath = Yii::getAlias($this->basePath);
        $fullPath = $basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        return realpath($fullPath) ?: $fullPath;
    }

    private function renderError($message)
    {
        $html = $this->renderHeader();
        $html .= '<div class="container">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="alert alert-danger" style="margin-top: 20px;">
                                <strong>❌ Ошибка!</strong><br>
                                ' . htmlspecialchars($message) . '
                            </div>
                            <a href="' . Yii::$app->urlManager->createUrl(['explorer']) . '" class="btn btn-primary">← На главную</a>
                        </div>
                    </div>
                </div>';
        $html .= $this->renderFooter();
        return $html;
    }

    private function renderHeader()
    {
        return '<!DOCTYPE html>
        <html>
        <head>
            <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
            <style>
                body { padding: 20px; background: #f5f5f5; }
                .container { max-width: 1400px; }
                .sql-editor {
                    background: white;
                    padding: 20px;
                    border-radius: 4px;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                    margin-bottom: 20px;
                }
                .breadcrumb { background: white; }
                .table td { vertical-align: middle; }
                pre { max-height: 600px; overflow: auto; }
                .btn-group-xs > .btn { font-size: 12px; padding: 1px 5px; }
            </style>
        </head>
        <body>';
    }

    private function renderFooter()
    {
        return '</body></html>';
    }

    private function formatFileSize($bytes)
    {
        if ($bytes === 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes, 1024));
        return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
    }

    private function getLanguageByExtension($extension)
    {
        $map = [
            'php' => 'php',
            'js' => 'javascript',
            'json' => 'json',
            'html' => 'html',
            'css' => 'css',
            'xml' => 'xml',
            'sql' => 'sql',
            'txt' => 'text',
            'md' => 'markdown',
            'py' => 'python',
            'rb' => 'ruby',
            'go' => 'go',
            'java' => 'java',
            'c' => 'c',
            'cpp' => 'cpp',
            'h' => 'c',
            'hpp' => 'cpp',
        ];
        return $map[$extension] ?? 'text';
    }

    private function isBinaryFile($path)
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $binaryExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'ico', 'svg', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'rar', '7z', 'tar', 'gz', 'exe', 'dll', 'so', 'bin', 'iso'];

        if (in_array($extension, $binaryExtensions)) {
            return true;
        }

        // Проверка содержимого для файлов без расширения
        $content = file_get_contents($path, false, null, 0, 512);
        if ($content === false) return true;

        $isBinary = substr($content, 0, 1) === "\x00" || substr($content, 0, 1) === "\xFF";
        return $isBinary;
    }

    private function isProtectedPath($path)
    {
        $basePath = Yii::getAlias($this->basePath);
        $protected = [
            $basePath . DIRECTORY_SEPARATOR . '..',
            $basePath,
            $basePath . DIRECTORY_SEPARATOR . 'protected',
            $basePath . DIRECTORY_SEPARATOR . 'vendor',
            $basePath . DIRECTORY_SEPARATOR . 'runtime',
        ];
        return in_array($path, $protected);
    }

    private function getSize($path)
    {
        if (is_file($path)) {
            return filesize($path);
        }

        if (is_dir($path)) {
            $size = 0;
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($files as $file) {
                $size += $file->getSize();
            }
            return $size;
        }

        return 0;
    }

    private function deletePath($path)
    {
        if (is_file($path)) {
            return unlink($path);
        }

        if (is_dir($path)) {
            $items = scandir($path);
            foreach ($items as $item) {
                if ($item == '.' || $item == '..') continue;
                if (!$this->deletePath($path . DIRECTORY_SEPARATOR . $item)) {
                    return false;
                }
            }
            return rmdir($path);
        }

        return false;
    }

    private function addFolderToZip($folder, $zip, $zipFolderName)
    {
        $items = scandir($folder);
        foreach ($items as $item) {
            if ($item == '.' || $item == '..') continue;

            $itemPath = $folder . DIRECTORY_SEPARATOR . $item;
            $itemInZip = $zipFolderName . '/' . $item;

            if (is_dir($itemPath)) {
                $zip->addEmptyDir($itemInZip);
                $this->addFolderToZip($itemPath, $zip, $itemInZip);
            } else {
                $zip->addFile($itemPath, $itemInZip);
            }
        }
    }
}
