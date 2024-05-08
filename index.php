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


$last_command = [];
$last_update_time = (new DateTime('now'))->getTimestamp();
$last_delete_time = (new DateTime('now'))->getTimestamp();

while (true) {
    try {

        $telegram = new Longman\TelegramBot\Telegram(Constants::BOT_API_KEY, Constants::BOT_USERNAME);
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
                            $response = 'Введіть ip адресу сервера';
                            $last_command[$message_chat_id]['command'] = $message_text;
                            $last_command[$message_chat_id]['stage'] = 1;
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
                    case '/set_cpu_parameters':
                    case '/set_ram_parameters':
                    case '/set_disk_parameters':
                        $response = 'Введіть значення пороку попередження';
                        $last_command[$message_chat_id]['command'] = $message_text;
                        $last_command[$message_chat_id]['stage'] = 1;
                        break;
                    case '/set_warning_range_time':
                        $response = 'Введіть проміжок, за який фіксується помилка(від 5 до 15 хвилин)';
                        $last_command[$message_chat_id]['command'] = $message_text;
                        break;
                    case '/help':
                    case '/start':
                        $response = "ServerAnalyticBot - це бот, що надає користувачу детальну інформацію про сервер, а також його статистичні дані. Окрім цього бот слідкує за значеннями нвантаження для того, щоб повідомити про проблеми. нижче описано список команд, які цей бот може виконувати.\n\n/connect - Під'єднатися до сервера. Користувач передає ip сервера, а також username та пароль, якщо користувач вже підключений, то бот повідомить про це.\n/disconnect - Закрити з'єднання та очистити дані про це з'єднання.\n/get_cpu_info - Отримати дані використання процесора. Детальна інформація з оцінкою навантаження, а також статистичні діаграми до отриманих даних.\n/get_ram_info - Отримати дані використання оперативної пам'яті. Схожий функціонал, що і у /get_cpu_info.\n/get_hard_disk_info - Отримати дані використання жорсткого диску. Схожий функціонал, що і у /get_cpu_info.\n/get_additional_info - Отримати додаткову інформацію про сервер. Ім'я хосту, версії ітд.\n/set_cpu_parameters - Змінити критичні значення для процесора при яких графік буде виділяти значення для попередження, а також надсилати графік зі статистикою, якщо на проміжку часу усі значення критичні.\n/set_ram_parameters - Змінити критичні значення для оперативної пам'яті, використовуються так само, як і значення /set_cpu_parameters.\n/set_disk_parameters - Змінити критичні значення для жорсткого диску, використовуються так само, як і значення /set_cpu_parameters.\n/set_warning_range_time - Змінити час, протягом якого сервер має перевищувати критичні значення, щоб була надіслана статистика останнього проміжку значень для попередження.\n/help - Детальна інформація";
                        break;
                    default:
                        if (($last_command[$message_chat_id]['command'] ?? '') === '/connect') {
                            if ($message_text === '/stop') {
                                unset($last_command[$message_chat_id]);

                                $response = 'Відміна команди';
                            } elseif ($last_command[$message_chat_id]['stage'] === 1) {
                                $last_command[$message_chat_id]['data'][] = trim($message_text);
                                $last_command[$message_chat_id]['stage'] = 2;

                                $response = 'Тепер введіть username';
                            } elseif ($last_command[$message_chat_id]['stage'] === 2) {
                                $last_command[$message_chat_id]['data'][] = trim($message_text);
                                $last_command[$message_chat_id]['stage'] = 3;

                                $response = 'Тепер введіть пароль';
                            } elseif ($last_command[$message_chat_id]['stage'] === 3) {
                                $last_command[$message_chat_id]['data'][] = trim($message_text);

                                $response = Main::getInstance()->connectToServer(
                                    $message_chat_id,
                                    $last_command[$message_chat_id]['data']
                                );
                                unset($last_command[$message_chat_id]);
                            }
                        } elseif (in_array(
                            ($last_command[$message_chat_id]['command'] ?? ''),
                            ['/set_cpu_parameters', '/set_ram_parameters', '/set_disk_parameters']
                        )) {
                            if ($message_text === '/stop') {
                                unset($last_command[$message_chat_id]);

                                $response = 'Відміна команди';
                            } elseif ($last_command[$message_chat_id]['stage'] === 1) {
                                if (filter_var($message_text, FILTER_VALIDATE_FLOAT)) {
                                    $last_command[$message_chat_id]['data'][] = (float)$message_text;
                                    $last_command[$message_chat_id]['stage'] = 2;

                                    $response = 'Тепер введіть значення пороку критичного попередження';
                                } else {
                                    $response = 'Значення має бути числовим, спробуйте знову';
                                }
                            } elseif ($last_command[$message_chat_id]['stage'] === 2) {
                                if (filter_var($message_text, FILTER_VALIDATE_FLOAT)) {
                                    $last_command[$message_chat_id]['data'][] = (float)$message_text;

                                    if ($last_command[$message_chat_id]['command'] === '/set_cpu_parameters') {
                                        $response = Main::getInstance()->updateChatCPUParameters(
                                            $message_chat_id,
                                            $last_command[$message_chat_id]['data']
                                        );
                                    } elseif ($last_command[$message_chat_id]['command'] === '/set_ram_parameters') {
                                        $response = Main::getInstance()->updateChatRAMParameters(
                                            $message_chat_id,
                                            $last_command[$message_chat_id]['data']
                                        );
                                    } elseif ($last_command[$message_chat_id]['command'] === '/set_disk_parameters') {
                                        $response = Main::getInstance()->updateChatDiskParameters(
                                            $message_chat_id,
                                            $last_command[$message_chat_id]['data']
                                        );
                                    }

                                    unset($last_command[$message_chat_id]);
                                } else {
                                    $response = 'Значення має бути числовим, спробуйте знову';
                                }
                            }
                        } elseif (($last_command[$message_chat_id]['command'] ?? '') === '/set_warning_range_time') {
                            if ($message_text === '/stop') {
                                unset($last_command[$message_chat_id]);

                                $response = 'Відміна команди';
                            } else {
                                if (filter_var($message_text, FILTER_VALIDATE_INT)) {
                                    $last_command[$message_chat_id]['data'] = (float)$message_text;

                                    $response = Main::getInstance()->updateChatWarningTimeParameters(
                                        $message_chat_id,
                                        $last_command[$message_chat_id]['data']
                                    );

                                    unset($last_command[$message_chat_id]);
                                } else {
                                    $response = 'Значення має бути формату int, спробуйте знову';
                                }
                            }
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

        $new_update_time = (new DateTime('now'))->getTimestamp();
        if ($new_update_time - $last_update_time > Constants::TIME_FOR_UPDATE) {
            Main::getInstance()->updateStatsInfo();
            $last_update_time = (new DateTime('now'))->getTimestamp();
        }

        Main::getInstance()->sendErrorMessages();

        $new_delete_time = (new DateTime('now'))->getTimestamp();
        if ($new_delete_time - $last_delete_time > Constants::TIME_FOR_DELETE) {
            Main::getInstance()->deleteOldRows();
            $last_delete_time = (new DateTime('now'))->getTimestamp();
        }

    } catch (Longman\TelegramBot\Exception\TelegramException $e) {
        echo $e->getMessage();
    }

    sleep(3);
}