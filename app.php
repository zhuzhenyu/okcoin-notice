<?php
ini_set('display_errors', 'on');
error_reporting(E_ALL);
date_default_timezone_set("PRC");

require __DIR__ . '/vendor/autoload.php';
use Workerman\Worker;

//环境检测
if (strpos(strtolower(PHP_OS), 'win') === 0) {
    exit("当前脚本只允许在Linux环境运行\n");
}

if (!extension_loaded('pcntl')) {
    exit("请安装 pcntl 扩展\n");
}

if (!extension_loaded('posix')) {
    exit("请安装 posix 扩展\n");
}


$scripts = [
    //监控以太坊
    __DIR__ . '/scripts/eth_app.php',
    //监控莱特币
    __DIR__ . '/scripts/ltc_app.php'
];

define('GLOBAL_START', 1);
//全局启动
foreach ($scripts as $file) {
    require_once $file;
}

Worker::runAll();