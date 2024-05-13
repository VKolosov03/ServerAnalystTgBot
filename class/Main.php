<?php

use Longman\TelegramBot\Request;
use phpseclib3\Net\SSH2;

class Main
{
    private static $instance;

    private $ssh;

    private $db;

    private function __construct() {
        $this->ssh = null;
        $this->db = (new DataBase());
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function checkConnection($chat_id)
    {
        $chat_data = $this->db->getChatTableData($chat_id);
        if ($chat_data) {
            $this->ssh = new SSH2($chat_data['server_ip']);
            $this->ssh->login(
                $chat_data['server_username'],
                $chat_data['server_password']
            );
        }

        return isset($this->ssh) && $this->ssh->isConnected();
    }

    public function connectToServer($chat_id, $data)
    {

        try {
            $this->ssh = new SSH2($data[0]);
            if (!$this->ssh->login($data[1], $data[2])) {
                $response = 'Неправильні ім\'я користувача або пароль!';
            } else {
                $response = 'Успішне підключення';
                $this->db->setInsertChatServerTableData($chat_id, $data);
            }
        } catch (Exception $e) {
            $response = 'Неправильна IP адреса!';
        }

        return $response;
    }

    public function disconnectFromServer($chat_id)
    {
        $response = 'Роз\'єднання пройшло успішно!';

        if (!$this->checkConnection($chat_id)) {
            $response = 'Немає підключення до сервера';
            return $response;
        }

        try {
            $this->ssh->disconnect();

            $this->db->deleteChatRow($chat_id);
            $this->db->deleteCPURowByChatId($chat_id);
            $this->db->deleteRAMRowByChatId($chat_id);
            $this->db->deleteDiskRowByChatId($chat_id);

            $this->ssh = null;
        } catch (Exception $e) {
            $response = 'Невдала спроба роз\'єднання!';
        }

        return $response;
    }

    public function getCPUData($chat_id) {
        $response = '';

        if (!$this->checkConnection($chat_id)) {
            $response = 'Немає підключення до сервера';
            return $response;
        }

        $chat_data = $this->db->getChatTableData($chat_id);

        if (!$chat_data) {
            $response = 'Відсутні дані про акаунт';
            return $response;
        }

        $cpu_data = Processor::processCPUData($this->ssh);


        $response .= '🖥️ Використання процесора: ' . $cpu_data['usage'] . "%;\n";
        $response .= '🖥️ Потоків процесора: ' . $cpu_data['threads'] . ";\n";

        if ($cpu_data['usage'] > $chat_data['cpu_serious_warning_value']) {
            $response .= '🔴 Критичне навантаження на процесор!';
        } elseif ($cpu_data['usage'] > $chat_data['cpu_light_warning_value']) {
            $response .= '🟡 Середнє навантаження на процесор.';
        } else {
            $response .= '🟢 Мінімальне навантаження на процесор';
        }

        $this->db->setUpdateStatsTableData('cpu_info', $chat_id, $cpu_data);
        $last_cpu_usage_data = $this->db->getLastUsageDataByChatId('cpu_info', $chat_id, $chat_data['warning_time_limit']);
        $photo = [];

        if (count($last_cpu_usage_data) > 1) {
            $bar_chart_caption = 'Стовпчаста діаграма останнього використання процесору.' . "\n";
            $bar_chart_caption .= 'Медіана останніх значень - ' . median($last_cpu_usage_data) . "\n";
            $bar_chart_caption .= 'Середнє значення - ' . average($last_cpu_usage_data) . "\n";
            $bar_chart_caption .= 'Мінімальне значення - ' . min($last_cpu_usage_data) . "\n";
            $bar_chart_caption .= 'Максимальне значення - ' . max($last_cpu_usage_data) . "\n";

            $photo[] = [
                'url' => (new DiagramsImages())->createUsageVerticalBarChart(
                    $last_cpu_usage_data,
                    $chat_data['cpu_light_warning_value'],
                    $chat_data['cpu_serious_warning_value']
                ),
                'text' => $bar_chart_caption
            ];
        }

        return [
            'text' => $response,
            'photo' => $photo
        ];
    }

    public function getRAMData($chat_id) {
        $response = '';

        if (!$this->checkConnection($chat_id)) {
            $response = 'Немає підключення до сервера';
            return $response;
        }

        $chat_data = $this->db->getChatTableData($chat_id);

        if (!$chat_data) {
            $response = 'Відсутні дані про акаунт';
            return $response;
        }

        $ram_data = Processor::processRAMData($this->ssh);

        $response .= '🌡️ Використання оперативної пам\'яті: ' . $ram_data['usage'] . "%;\n";
        $response .= '🌡️ Всього оперативної пам\'яті: ' . $ram_data['total'] . "GB;\n";
        $response .= '🌡️ Використано оперативної пам\'яті: ' . $ram_data['used'] . "GB;\n";
        $response .= '🌡️ Вільно оперативної пам\'яті: ' . $ram_data['available'] . "GB;\n";

        if ($ram_data['usage'] > $chat_data['ram_serious_warning_value']) {
            $response .= '🔴 Критичне використання оперативної пам\'яті!';
        } elseif ($ram_data['usage'] > $chat_data['ram_light_warning_value']) {
            $response .= '🟡 Середнє використання оперативної пам\'яті.';
        } else {
            $response .= '🟢 Мінімальне використання оперативної пам\'яті.';
        }

        $this->db->setUpdateStatsTableData('ram_info', $chat_id, $ram_data);
        $last_ram_usage_data = $this->db->getLastUsageDataByChatId('ram_info', $chat_id, $chat_data['warning_time_limit']);
        $photo = [];

        $photo[] = [
            'url' => (new DiagramsImages())->createAvailableUsedPieChart($ram_data),
            'text' => 'Кругова діаграма використання оперативної пам\'яті'
        ];

        if (count($last_ram_usage_data) > 1) {
            $bar_chart_caption = 'Стовпчаста діаграма останнього використання оперативної пам\'яті.' . "\n";
            $bar_chart_caption .= 'Медіана останніх значень - ' . median($last_ram_usage_data) . "\n";
            $bar_chart_caption .= 'Середнє значення - ' . average($last_ram_usage_data) . "\n";
            $bar_chart_caption .= 'Мінімальне значення - ' . min($last_ram_usage_data) . "\n";
            $bar_chart_caption .= 'Максимальне значення - ' . max($last_ram_usage_data) . "\n";

            $photo[] = [
                'url' => (new DiagramsImages())->createUsageVerticalBarChart(
                    $last_ram_usage_data,
                    $chat_data['ram_light_warning_value'],
                    $chat_data['ram_serious_warning_value']
                ),
                'text' => $bar_chart_caption
            ];
        }

        return [
            'text' => $response,
            'photo' => $photo
        ];
    }

    public function getDiscData($chat_id) {
        $response = '';

        if (!$this->checkConnection($chat_id)) {
            $response = 'Немає підключення до сервера';
            return $response;
        }

        $chat_data = $this->db->getChatTableData($chat_id);

        if (!$chat_data) {
            $response = 'Відсутні дані про акаунт';
            return $response;
        }

        $disk_data = Processor::processHardDiskData($this->ssh);

        $response .= '💽 Використання жортского диску: ' . $disk_data['usage'] . "%;\n";
        $response .= '💽 Всього місця на жорсткому диску: ' . $disk_data['total'] . "GB;\n";
        $response .= '💽 Використано місця на жорсткому диску: ' . $disk_data['used'] . "GB;\n";
        $response .= '💽 Вільно  місця на жорсткому диску: ' . $disk_data['available'] . "GB;\n";

        if ($disk_data['usage'] > $chat_data['disk_serious_warning_value']) {
            $response .= '🔴 Критичне використання жорсткого диску!';
        } elseif ($disk_data['usage'] > $chat_data['disk_light_warning_value']) {
            $response .= '🟡 Середнє використання жорсткого диску.';
        } else {
            $response .= '🟢 Мінімальне використання жорсткого диску.';
        }

        $this->db->setUpdateStatsTableData('disk_info', $chat_id, $disk_data);
        $last_disk_usage_data = $this->db->getLastUsageDataByChatId('disk_info', $chat_id, $chat_data['warning_time_limit']);
        $photo = [];

        $photo[] = [
            'url' => (new DiagramsImages())->createAvailableUsedPieChart($disk_data),
            'text' => 'Кругова діаграма використання жорсткого диску'
        ];

        if (count($last_disk_usage_data) > 1) {
            $bar_chart_caption = 'Стовпчаста діаграма останнього використання жорсткого диску.' . "\n";
            $bar_chart_caption .= 'Медіана останніх значень - ' . median($last_disk_usage_data) . "\n";
            $bar_chart_caption .= 'Середнє значення - ' . average($last_disk_usage_data) . "\n";
            $bar_chart_caption .= 'Мінімальне значення - ' . min($last_disk_usage_data) . "\n";
            $bar_chart_caption .= 'Максимальне значення - ' . max($last_disk_usage_data) . "\n";

            $photo[] = [
                'url' => (new DiagramsImages())->createUsageVerticalBarChart(
                    $last_disk_usage_data,
                    $chat_data['disk_light_warning_value'],
                    $chat_data['disk_serious_warning_value']
                ),
                'text' => $bar_chart_caption
            ];
        }

        return [
            'text' => $response,
            'photo' => $photo
        ];
    }

    public function getAdditionalData($chat_id) {
        $response = '';

        if (!$this->checkConnection($chat_id)) {
            $response = 'Немає підключення до сервера';
            return $response;
        }

        $additional_data = Processor::processAdditionalData($this->ssh);

        $response .= '🌀 Назва хосту: ' . $additional_data['hostname'] . ";\n";
        $response .= '🌀 Версія PHP: ' . $additional_data['php_version'] . ";\n";
        $response .= '🌀 Використано пам\'яті на PHP: ' . $additional_data['php_usage'] . "GB;\n";

        return $response;
    }

    public function updateStatsInfo() {
        $chats_data = $this->db->getChatsTableData();

        foreach ($chats_data as $chat) {
            if (!$this->checkConnection($chat['chat_id'])) {
                continue;
            }

            $cpu_data = Processor::processCPUData($this->ssh);
            $ram_data = Processor::processRAMData($this->ssh);
            $disk_data = Processor::processHardDiskData($this->ssh);

            $this->db->setUpdateStatsTableData('cpu_info', $chat['chat_id'], $cpu_data);
            $this->db->setUpdateStatsTableData('ram_info', $chat['chat_id'], $ram_data);
            $this->db->setUpdateStatsTableData('disk_info', $chat['chat_id'], $disk_data);
        }
    }

    public function sendErrorMessages()
    {
        $chats_data = $this->db->getChatsTableData();

        foreach ($chats_data as $chat) {
            if (!$this->checkConnection($chat['chat_id'])) {
                continue;
            }

            $check_time = strtotime((new DateTime('now'))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'));
            $last_update_time = (new DateTime($chat['last_update_time']))->getTimestamp();
            if ($check_time - $last_update_time < ($chat['warning_time_limit'] * 60)) {
                continue;
            }

            $last_cpu_usage_data = $this->db->getLastUsageDataByChatId('cpu_info', $chat['chat_id'], $chat['warning_time_limit']);
            $last_ram_usage_data = $this->db->getLastUsageDataByChatId('ram_info', $chat['chat_id'], $chat['warning_time_limit']);
            $last_disk_usage_data = $this->db->getLastUsageDataByChatId('disk_info', $chat['chat_id'], $chat['warning_time_limit']);

            if (num_array_check_less_than_or_equal($last_cpu_usage_data, $chat['cpu_serious_warning_value'])) {
                $this->sendErrorMessage($last_cpu_usage_data, 'CPU', $chat['chat_id'], $chat['cpu_light_warning_value'], $chat['cpu_serious_warning_value']);
            }

            if (num_array_check_less_than_or_equal($last_ram_usage_data, $chat['ram_serious_warning_value'])) {
                $this->sendErrorMessage($last_ram_usage_data, 'RAM', $chat['chat_id'], $chat['ram_light_warning_value'], $chat['ram_serious_warning_value']);
            }

            if (num_array_check_less_than_or_equal($last_disk_usage_data, $chat['disk_serious_warning_value'])) {
                $this->sendErrorMessage($last_disk_usage_data, 'HDD', $chat['chat_id'], $chat['disk_light_warning_value'], $chat['disk_serious_warning_value']);
            }

            $this->db->setChatLastUpdateTime($chat['chat_id']);
        }
    }

    public function deleteOldRows() {
        $this->db->deleteOldCPURow();
        $this->db->deleteOldRAMRow();
        $this->db->deleteOldDiskRow();
    }

    private function sendErrorMessage($error_data, $part_name, $chat_id, $light_warning_value, $serious_warning_value)
    {
        $photo_url = (new DiagramsImages())->createUsageVerticalBarChart(
            $error_data,
            $light_warning_value,
            $serious_warning_value
        );

        $photo_caption = 'Критичне навантаження на ' . $part_name . "\n";
        $photo_caption .= 'Медіана останніх значень - ' . median($error_data) . "\n";
        $photo_caption .= 'Середнє значення - ' . average($error_data) . "\n";
        $photo_caption .= 'Мінімальне значення - ' . min($error_data) . "\n";
        $photo_caption .= 'Максимальне значення - ' . max($error_data) . "\n";

        Request::sendPhoto([
            'chat_id' => $chat_id,
            'photo' => $photo_url,
            'caption' => $photo_caption
        ]);
    }

    private function checkWarningsValue($light_warning, $serious_warning) {
        $response = false;

        if ($light_warning < 1) {
            $response = 'Значення попередження має бути більше 1';
        } elseif ($serious_warning > 99) {
            $response = 'Значення критичного попередження не може бути більше за 99';
        } elseif ($serious_warning - $light_warning < 1) {
            $response = 'Різниця від попередження до критичного попередження має бути більше за 1';
        }

        return $response;
    }

    public function updateChatCPUParameters($chat_id, $cpu_data)
    {
        if (!$this->checkConnection($chat_id)) {
            $response = 'Немає підключення до сервера';
        } else {
            $response = $this->checkWarningsValue($cpu_data[0], $cpu_data[1]) ?: '';
        }

        if (strlen($response) === 0) {
            if ($this->db->setUpdateChatCPUTableData($chat_id, $cpu_data)) {
                $response = 'Оновлення пройшло успішно';
            } else {
                $response = 'Помилка оновлення';
            }
        }

        return $response;
    }

    public function updateChatRAMParameters($chat_id, $ram_data)
    {
        if (!$this->checkConnection($chat_id)) {
            $response = 'Немає підключення до сервера';
        } else {
            $response = $this->checkWarningsValue($ram_data[0], $ram_data[1]) ?: '';
        }

        if (strlen($response) === 0) {
            if ($this->db->setUpdateChatRAMTableData($chat_id, $ram_data)) {
                $response = 'Оновлення пройшло успішно';
            } else {
                $response = 'Помилка оновлення';
            }
        }

        return $response;
    }

    public function updateChatDiskParameters($chat_id, $disk_data)
    {
        if (!$this->checkConnection($chat_id)) {
            $response = 'Немає підключення до сервера';
        } else {
            $response = $this->checkWarningsValue($disk_data[0], $disk_data[1]) ?: '';
        }

        if (strlen($response) === 0) {
            if ($this->db->setUpdateChatDiskTableData($chat_id, $disk_data)) {
                $response = 'Оновлення пройшло успішно';
            } else {
                $response = 'Помилка оновлення';
            }
        }

        return $response;
    }

    public function updateChatWarningTimeParameters($chat_id, $limit)
    {
        if (!$this->checkConnection($chat_id)) {
            $response = 'Немає підключення до сервера';
        } elseif ($limit <= 3) {
            $response = 'Значення ліміту має бути як мінімум 3 хвилини';
        } elseif ($limit >= 15) {
            $response = 'Значення ліміту має бути не більше за 15 хвилин';
        }

        if (strlen($response) === 0) {
            if ($this->db->setUpdateChatLimitTableData($chat_id, $limit)) {
                $response = 'Оновлення пройшло успішно';
            } else {
                $response = 'Помилка оновлення';
            }
        }

        return $response;
    }
}

