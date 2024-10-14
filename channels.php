<?php
require 'vendor/autoload.php';

// Настройки подключения к базе данных
$db_host = 'localhost';
$db_name = 'thousand';
$db_user = 'root';
$db_pass = 'AppLLd4m3';

// Создание подключения к базе данных
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Проверка подключения к базе данных
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Пример массива каналов
$channel_ids = ['habr_career', 'codel1fe', 'postnauka', 'skillboxru', 'obznam', 'github_tg', 'habr_com', 'ya_jobs', 'studyinspi', 'itProger_official'];

// Сохранение информации о каналах в базу данных
foreach ($channel_ids as $channel_id) {
    $sql = "INSERT INTO channels (channel_id) VALUES ('$channel_id')";
    if ($conn->query($sql) === TRUE) {
        echo "New record created successfully for channel: $channel_id" . PHP_EOL;
    } else {
        echo "Error: " . $sql . PHP_EOL . $conn->error;
    }
}

// Закрытие подключения к базе данных
$conn->close();

// Запуск fetch_posts.php для каждого канала в фоновом режиме
$start_time = microtime(true);
foreach ($channel_ids as $channel_id) {
    exec("php fetch_posts.php $channel_id > /dev/null 2>&1 &");
}

// Ожидание завершения всех дочерних процессов и вывод времени выполнения
sleep(10); // Увеличьте время ожидания, если необходимо
foreach ($channel_ids as $channel_id) {
    $log_file = "log_$channel_id.txt";
    if (file_exists($log_file)) {
        echo file_get_contents($log_file);
        unlink($log_file); // Удаление лог-файла после чтения
    }
}

$end_time = microtime(true);
$execution_time = ($end_time - $start_time);
echo "Общее время выполнения всех fetch_posts.php: " . $execution_time . " секунд\n";
?>
