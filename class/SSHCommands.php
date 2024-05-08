<?php

use phpseclib3\Net\SSH2;

class SSHCommands
{
    public static function setCommandCPUUsage(SSH2 $ssh) {
        return $ssh->exec('php -r "echo implode(\" \", sys_getloadavg());"');
    }

    public static function setCommandCPUCount(SSH2 $ssh) {
        return $ssh->exec('nproc');
    }

    public static function setCommandRAMInfo(SSH2 $ssh) {
        return $ssh->exec('free');
    }

    public static function setCommandDiskFreeSpace(SSH2 $ssh) {
        return $ssh->exec('df -h');
    }

    public static function setCommandDiskTotalSpace(SSH2 $ssh) {
        return $ssh->exec('df');
    }

    public static function setCommandHostName(SSH2 $ssh) {
        return $ssh->exec('hostname');
    }

    public static function setCommandPHPVersion(SSH2 $ssh) {
        return $ssh->exec('php -r "echo phpversion();"');
    }

    public static function setCommandPHPMemoryUsage(SSH2 $ssh) {
        return $ssh->exec('php -r "echo memory_get_usage();"');
    }
}

