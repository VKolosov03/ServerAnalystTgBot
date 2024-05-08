<?php

class Processor
{
    public static function processCPUData($ssh)
    {
        $cpu = [];

        $cpu_usage_data = SSHCommands::setCommandCPUUsage($ssh);
        $cpu_count_data = SSHCommands::setCommandCPUCount($ssh);

        $cpu['threads'] = trim($cpu_count_data);

        $cpu_usage_loadavg = explode(" ", $cpu_usage_data);
        $cpu['usage'] = round($cpu_usage_loadavg[0], 2);

        $cpu['update_datetime'] = (new DateTime('now'))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        return $cpu;
    }

    public static function processRAMData($ssh)
    {
        $ram = [];

        $ram_response = SSHCommands::setCommandRAMInfo($ssh);

        $ram_response_arr = explode("\n", trim($ram_response));
        $ram_response_arr = explode(" ", $ram_response_arr[1]);
        $ram_response_arr = array_merge(array_filter($ram_response_arr, function ($value) {
            return ($value !== null && $value !== false && $value !== '');
        }));

        $ram['total']       = round($ram_response_arr[1] / 1000000,2);
        $ram['used']        = round($ram_response_arr[2] / 1000000,2);
        $ram['available']   = round($ram_response_arr[6] / 1000000,2);
        $ram['usage']       = round(($ram['used']/$ram['total'])*100);
        $ram['update_datetime'] = (new DateTime('now'))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        return $ram;
    }

    public static function processHardDiskData($ssh)
    {
        $disk = [];

        $disk_available_data = SSHCommands::setCommandDiskFreeSpace($ssh);
        $disk_total_data = SSHCommands::setCommandDiskTotalSpace($ssh);

        $disk_available_lines = explode("\n", $disk_available_data);

        $disk_available_space = 0;
        foreach ($disk_available_lines as $line) {
            if (strpos($line, '/dev/') !== false) {
                $parts = preg_split('/\s+/', $line);
                $disk_available_space += filter_var($parts[3], FILTER_SANITIZE_NUMBER_FLOAT);
            }
        }

        $disk_total_lines = explode("\n", $disk_total_data);

        $disk_total_space = 0;
        foreach ($disk_total_lines as $line) {
            if (strpos($line, '/dev/') !== false) {
                $parts = preg_split('/\s+/', $line);
                $disk_total_space += $parts[1] * 1024;
            }
        }

        $disk['total']       = round($disk_total_space / 1000000000, 2);
        $disk['available']   = round($disk_available_space, 2);
        $disk['used']        = round($disk['total'] - $disk['available'], 2);
        $disk['usage']       = round(($disk['used']/$disk['total'])*100);
        $disk['update_datetime'] = (new DateTime('now'))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        return $disk;
    }

    public static function processAdditionalData($ssh)
    {
        $additional_data = [];

        $hostname = SSHCommands::setCommandHostName($ssh);
        $php_version = SSHCommands::setCommandPHPVersion($ssh);
        $php_usage = SSHCommands::setCommandPHPMemoryUsage($ssh);

        $additional_data['hostname']       = $hostname;
        $additional_data['php_version']    = $php_version;
        $additional_data['php_usage']      = round($php_usage / 1000000,2);

        return $additional_data;
    }
}