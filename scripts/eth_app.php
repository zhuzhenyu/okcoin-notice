<?php
use Workerman\Worker;
use Workerman\Lib\Timer;
use Workerman\Connection\AsyncTcpConnection;



$worker = new Worker();
$worker->count = 1;
$reConnect = 0;
$configPath = __DIR__ . "/../config/eth_config.ini";
$config = getConfig(null, $configPath);

//发送次数
$config['pushBear_send_count'] = 0;
//上次发送时间
$config['pushBear_pre_send_time'] = 0;


$worker->onWorkerStart = function ($_worker) {
    $interval = 0.5;
    Timer::add($interval, function () {
        global $reConnect;
        if (!$reConnect) {
            $wss = "ws://real.okcoin.cn:10440/websocket/okcoinapi";
            $conn = new AsyncTcpConnection($wss);
            $conn->transport = "ssl";
            $conn->onConnect = function ($_conn) {
                global $reConnect;
                $reConnect = 1;
                message("服务器连接成功...");
                $ltc = subscribeChannel("ok_sub_spotcny_eth_ticker");
                $_conn->send($ltc);
            };
            $conn->onMessage = function ($_conn, $data) {
                $data = json_decode($data, true);
                $_data = $data[0];
                if (isset($_data["data"]['result']) && $_data["data"]['result'] == true) {
                    message("以太坊 - 行情订阅成功");
                }
                if (isset($_data['channel']) && $_data['channel'] == 'ok_sub_spotcny_eth_ticker') {
                    $table = new LucidFrame\Console\ConsoleTable();
                    $datetime = date('Y-m-d H:i:s');
                    $content = $_data['data'];
                    $table->addRow(["当前订阅", "[以太坊(ETH)] - 实时行情"])
                        ->addRow(["最新成交", $content["last"]])
                        ->addRow(["买一价", $content["buy"]])
                        ->addRow(["最高价", $content["high"]])
                        ->addRow(["最低价", $content["low"]])
                        ->addRow(["卖一价", $content["sell"]])
                        ->addRow(["成交量", $content["vol"]])
                        ->addRow(["服务器时间", $datetime])
                        ->display();
                    $markdown = <<<EOL
| 参考数据 | 参考值 | 
| --- | --- |
| 最新成交价 | {$content["last"]} |
| 买一价 | {$content["buy"]} |
| 最高价 | {$content["high"]} |
| 最低价 | {$content["low"]} |
| 卖一价 | {$content["sell"]} |
| 成交量 | {$content["vol"]} |
| 服务器时间 | {$datetime} |
EOL;
                    $cur = time();
                    global $config;
                    if (
                        $cur - $config['pushBear_pre_send_time'] >= $config['pushBear_send_interval'] &&
                        $config['pushBear_send_count'] <= $config['pushBear_max_send_count']
                    ) {

                        if ($content['last'] <= $config['monitor_min_price']) {
                            $config['pushBear_pre_send_time'] = time();
                            $config['pushBear_send_count'] += 1;
                            pushBear("以太坊[低] - 监控", $markdown, $config["pushBear_sendKey"]);
                        }

                        if ($content['last'] >= $config["monitor_max_price"]) {
                            $config['pushBear_pre_send_time'] = time();
                            $config['pushBear_send_count'] += 1;
                            pushBear("以太坊[高] - 监控", $markdown, $config["pushBear_sendKey"]);

                        }
                    }

                }
            };

            //连接出错
            $conn->onError = function ($_conn, $code, $msg) {
                //支持断线重连
                echo "error: $msg\n";
                global $reConnect;
                $reConnect = 0;
                $_conn->close("close connection\n");
            };
            //连接断开
            $conn->onClose = function ($_conn) use ($conn) {
                echo "connection closed\n";
                global $reConnect;
                $reConnect = 0;
            };
            //连接
            $conn->connect();
        }
    }, []);
};


if (!defined('GLOBAL_START')) {
    Worker::runAll();
}
