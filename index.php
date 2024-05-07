<?php

require 'vendor/autoload.php';
require 'function.php';

use Longman\TelegramBot\Request;

spl_autoload_register(function ($class) {
    if (substr($class, 0, 8) == 'Project\\') {
        $class = substr($class, 8);
    }

    $file = 'class/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        include $file;
    }
});


$bot_username = 'server_analytics_bot';
$bot_api_key = '7076849962:AAHxXoFNzF4yPqMYKAqkBN9fdtXnD_DWXho';

$last_command = [];
$last_check_time = (new DateTime('now'))->getTimestamp();

while (true) {
    try {
        // Create Telegram API object
        $telegram = new Longman\TelegramBot\Telegram($bot_api_key, $bot_username);
        $telegram->useGetUpdatesWithoutDatabase();

        $server_response = $telegram->handleGetUpdates();

        if ($server_response->isOk()) {
            $result = $server_response->getResult();

            foreach ($result as $message_item) {
                $message = $message_item->getMessage();

                $message_chat_id = $message->getFrom()->getId();
                $message_text = $message->getText();

                switch ($message_text) {
                    case '/connect':
                        if (Main::getInstance()->checkConnection($message_chat_id)) {
                            $response = 'З\'єднання вже встановлено!';
                        } else {
                            $response = 'Введіть ip ітд.';
                            $last_command[$message_chat_id] = $message_text;
                        }
                        break;
                    case '/disconnect':
                        $response = Main::getInstance()->disconnectFromServer($message_chat_id);
                        break;
                    case '/get_cpu_info':
                        $response = Main::getInstance()->getCPUData($message_chat_id);
                        break;
                    case '/get_ram_info':
                        $response = Main::getInstance()->getRAMData($message_chat_id);
                        break;
                    case '/get_hard_disk_info':
                        $response = Main::getInstance()->getDiscData($message_chat_id);
                        break;
                    case '/get_additional_info':
                        $response = Main::getInstance()->getAdditionalData($message_chat_id);
                        break;
                    case '/check_overload':
                        $response = 'zroz';
                        break;
                    case '/help':
                        $response = 'zroz';
                        break;
                    default:
                        if ($last_command[$message_chat_id] ?? '' === '/connect') {
                            $form_data = explode("\n", $message_text);
                            $response = Main::getInstance()->connectToServer($message_chat_id, $form_data);
                            unset($last_command[$message_chat_id]);
                        } else {
                            $response = 'Оберіть команду для того, щоб щось отримати';
                        }
                }

                if (is_array($response)) {
                    $result = Request::sendMessage([
                        'chat_id' => $message_chat_id,
                        'text' => $response['text']
                    ]);

                    foreach ($response['photo'] as $photo) {
                        $result = Request::sendPhoto([
                            'chat_id' => $message_chat_id,
                            'photo' => $photo['url'],
                            'caption' => $photo['text']
                        ]);
                    }
                } else {
                    $result = Request::sendMessage([
                        'chat_id' => $message_chat_id,
                        'text' => $response
                    ]);
                }

            }
        }

        $new_check_time = (new DateTime('now'))->getTimestamp();
        if ($new_check_time - $last_check_time > 15) {
            print_r ((new DateTime('now'))->format('Y-m-d H:i:s') . "\n");
            Main::getInstance()->updateStatsInfo();
            $last_check_time = (new DateTime('now'))->getTimestamp();
        }

    } catch (Longman\TelegramBot\Exception\TelegramException $e) {
        // log telegram errors
        echo $e->getMessage();
    }
}