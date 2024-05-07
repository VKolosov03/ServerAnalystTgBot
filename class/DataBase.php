<?php

use phpseclib3\Net\SSH2;

class DataBase
{

    private $pdo_connection;

    function __construct()
    {
        $this->pdo_connection = null;
    }

    private function openConnection() {
        try {
            $this->pdo_connection = new PDO('sqlite:dataBase/dbStatisticData.sqlite');
            $this->pdo_connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo_connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);
            return true;
        } catch (PDOException $e) {
            print_r($e->getMessage());
            return false;
        }
    }

    public function setUpdateChatServerTableData($chat_id, $data) {
        if (!$this->openConnection()) return false;

        $query = $this->pdo_connection->prepare(
            "INSERT INTO chats (chat_id, server_ip, server_username, server_password) 
            VALUES (:chat_id, :server_ip, :server_username, :server_password)"
        );
        $query->bindParam(":chat_id", $chat_id);
        $query->bindParam(":server_ip", $data[0]);
        $query->bindParam(":server_username", $data[1]);
        $query->bindParam(":server_password", $data[2]);
        $result = $query->execute();

        $this->closeConnection();
        return $result;
    }

    public function getChatsTableData() {
        if (!$this->openConnection()) return [];

        $response = $this->pdo_connection->query("SELECT * FROM chats");
        $result = $response->fetchAll(PDO::FETCH_ASSOC);

        $this->closeConnection();
        return $result;
    }

    public function getChatTableData($chat_id) {
        if (!$this->openConnection()) return false;

        $response = $this->pdo_connection->query("SELECT * FROM chats WHERE chat_id = " . $chat_id);
        $result = $response->fetchAll(PDO::FETCH_ASSOC);

        $this->closeConnection();
        return $result[0] ?? false;
    }

    public function deleteChatRow($chat_id) {
        if (!$this->openConnection()) return false;

        $query = $this->pdo_connection->prepare("DELETE FROM chats WHERE chat_id = :chat_id");
        $query->bindParam(":chat_id", $chat_id);
        $result = $query->execute();

        $this->closeConnection();
        return $result;
    }

    public function deleteCPURowByChatId($chat_id) {
        if (!$this->openConnection()) return false;

        $query = $this->pdo_connection->prepare("DELETE FROM cpu_info WHERE chat = :chat_id");
        $query->bindParam(":chat_id", $chat_id);
        $result = $query->execute();

        $this->closeConnection();
        return $result;
    }

    public function deleteRAMRowByChatId($chat_id) {
        if (!$this->openConnection()) return false;

        $query = $this->pdo_connection->prepare("DELETE FROM ram_info WHERE chat = :chat_id");
        $query->bindParam(":chat_id", $chat_id);
        $result = $query->execute();

        $this->closeConnection();
        return $result;
    }

    public function deleteDiskRowByChatId($chat_id) {
        if (!$this->openConnection()) return false;

        $query = $this->pdo_connection->prepare("DELETE FROM disk_info WHERE chat = :chat_id");
        $query->bindParam(":chat_id", $chat_id);
        $result = $query->execute();

        $this->closeConnection();
        return $result;
    }

    public function setUpdateStatsTableData($table_name, $chat, $data) {
        if (!$this->openConnection()) return false;

        $query = $this->pdo_connection->prepare(
            "INSERT INTO " . $table_name . " (chat, " . implode(', ', array_keys($data)) . ") 
            VALUES (" . $chat . ", " . implode(', ', $data) . ")"
        );
        $result = $query->execute();

        $this->closeConnection();
        return $result;
    }

    public function getLastUsageDataByChatId($table_name, $chat, $limit) {
        if (!$this->openConnection()) return [];

        $response = $this->pdo_connection->query(
            "SELECT `usage` FROM " . $table_name . " WHERE chat = " . $chat . " AND update_datetime > DATE_SUB(NOW(), INTERVAL " . $limit . " MINUTE) ORDER BY id"
        );
        $result = array_map(function ($item) {
            return $item['usage'];
        }, $response->fetchAll(PDO::FETCH_ASSOC));

        $this->closeConnection();
        return array_reverse($result);
    }

    private function closeConnection() {
        $this->pdo_connection = null;
    }
}