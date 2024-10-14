<?php
require 'vendor/autoload.php';
use danog\MadelineProto\API;
use danog\MadelineProto\Settings;

$start_time = microtime(true);

$db_host = 'localhost';
$db_name_channels = 'thousand';
$db_name_posts = 'telegram_data';
$db_user = 'root';
$db_pass = 'AppLLd4m3';

// Подключение к базе данных для каналов и логов
$conn_channels = new mysqli($db_host, $db_user, $db_pass, $db_name_channels);
if ($conn_channels->connect_error) {
    die("Соединение не удалось: " . $conn_channels->connect_error);
}

$dsn = "mysql:host=$db_host;dbname=$db_name_posts;charset=utf8mb4";
$pdo = new PDO($dsn, $db_user, $db_pass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Создание объекта настроек
$settings = new Settings\AppInfo();
$settings->setApiId('20674764');
$settings->setApiHash('fbeaa23886273cefa57fdb0aa8359a37');

$MadelineProto = new API('session.madeline', $settings);
$MadelineProto->start();

// Получение ID канала из аргументов командной строки
$channel_id = $argv[1];

$stmt = $pdo->prepare("INSERT INTO posts (channel_id, author_id, date, text) VALUES (:channel_id, :author_id, :date, :text)");
$stmt_log = $conn_channels->prepare("INSERT INTO log (channel_id, request_time, response_time, status, error_message) VALUES (?, ?, ?, ?, ?)");

$offset_id = 0;
$total_messages = 0;
$limit = 100;

do {
    $request_time = date('Y-m-d H:i:s');
    $error_message = ''; // Установить значение по умолчанию

    try {
        $messages = $MadelineProto->messages->getHistory([
            'peer' => $channel_id,
            'offset_id' => $offset_id,
            'limit' => $limit
        ]);

        $response_time = date('Y-m-d H:i:s');
        $status = 'success';

        if (count($messages['messages']) == 0) {
            break;
        }

        foreach ($messages['messages'] as $message) {
            if (isset($message['message'])) {
                $author_id = isset($message['from_id']['user_id']) ? $message['from_id']['user_id'] : 'Канал';
                $date = date('Y-m-d H:i:s', $message['date']);
                $text = $message['message'];
                $stmt->execute([
                    ':channel_id' => $channel_id,
                    ':author_id' => $author_id,
                    ':date' => $date,
                    ':text' => $text
                ]);
                echo 'Код: ' . $message['id'] . ', Код канала: ' . $channel_id . ', Идентификатор автора: ' . $author_id . ', Дата: ' . $date . ', Текст: ' . $text . PHP_EOL;
            } else {
                echo 'Сообщение не содержит текст.' . PHP_EOL;
            }
        }

        $total_messages += count($messages['messages']);
        $offset_id = end($messages['messages'])['id'];
    } catch (\danog\MadelineProto\RPCErrorException $e) {
        $response_time = date('Y-m-d H:i:s');
        $status = 'error';
        $error_message = $e->getMessage();

        if (strpos($e->getMessage(), 'FLOOD_WAIT') !== false) {
            preg_match('/FLOOD_WAIT_(\d+)/', $e->getMessage(), $matches);
            $wait_time = (int)$matches[1];
            echo "Перегружено, подождите $wait_time секунд\n";
            sleep($wait_time);
        } else {
            throw $e;
        }
    }

    // Запись лога
    $stmt_log->bind_param("sssss", $channel_id, $request_time, $response_time, $status, $error_message);
    $stmt_log->execute();

} while ($total_messages < 1000);

$end_time = microtime(true);
$execution_time = ($end_time - $start_time);
$log_file = "log_$channel_id.txt";
file_put_contents($log_file, "Общее время выполнения для канала $channel_id: " . $execution_time . " секунд\n", FILE_APPEND);
?>
