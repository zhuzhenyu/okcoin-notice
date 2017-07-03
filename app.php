<?php
use Workerman\Worker;
use Workerman\Connection\AsyncTcpConnection;
use Workerman\Lib\Timer;

error_reporting(E_ALL);
date_default_timezone_set("PRC");

require __DIR__ . '/vendor/autoload.php';

$worker = new Worker();
$worker->count = 1;
$reConnect = 0;

$config = getConfig();
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
                $ltc = subscribeChannel("ok_sub_spotcny_ltc_ticker");
                $_conn->send($ltc);
            };
            $conn->onMessage = function ($_conn, $data) {
                $data = json_decode($data, true);
                $_data = $data[0];
                if (isset($_data["data"]['result']) && $_data["data"]['result'] == true) {
                    message("莱特币 - 行情订阅成功");
                }
                if (isset($_data['channel']) && $_data['channel'] == 'ok_sub_spotcny_ltc_ticker') {
                    $table = new LucidFrame\Console\ConsoleTable();
                    $datetime = date('Y-m-d H:i:s');
                    $content = $_data['data'];
                    $table->addRow(["当前订阅", "[莱特币(LTC)] - 实时行情"])
                        ->addRow(["最新成交", $content["last"]])
                        ->addRow(["买一价", $content["buy"]])
                        ->addRow(["最高价", $content["high"]])
                        ->addRow(["最低价", $content["low"]])
                        ->addRow(["卖一价", $content["sell"]])
                        ->addRow(["成交量", $content["vol"]])
                        ->addRow(["服务器时间", $datetime])
                        ->display();
                    $html = <<<EOL
| 参考数据 | 参考值 | 
| --- | --- |
| 最新成交价格 | {$content["last"]} |
| 买一价 | {$content["high"]} |
| 最高价 | {$content["low"]} |
| 最低价 | {$content["sell"]} |
| 卖一价 | {$content["vol"]} |
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
                            wechatPush("莱特币[低] - 监控", $html);
                        }

                        if ($content['last'] >= $config["monitor_max_price"]) {
                            $config['pushBear_pre_send_time'] = time();
                            $config['pushBear_send_count'] += 1;
                            wechatPush("莱特币[高] - 监控", $html);
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

/**
 * 订阅信息
 * @param $channel
 * @param string $event
 * @return string
 */
function subscribeChannel($channel, $event = "addChannel")
{
    $param = [
        "event" => $event,
        "channel" => $channel
    ];
    return json_encode($param);
}


/**
 * @desc 微信推送
 * @param $text
 * @param $desp
 * @return bool|string
 */
function wechatPush($text, $desp, $key = null)
{

    if ($key === null) {
        $key = getConfig("pushBear_sendKey");
    }
    $param = [
        'sendkey' => $key,
        "text" => $text,
        "desp" => $desp
    ];
    return file_get_contents("https://pushbear.ftqq.com/sub?" . http_build_query($param));
}


function message($message)
{
    $dateTime = date('Y-m-d H:i:s');
    echo "[{$dateTime}]：{$message}\n";
}


function getConfig($key = null)
{
    $configPath = __DIR__ . "/config.ini";
    $res = parse_ini_file($configPath, true);
    if ($key !== null) {
        return isset($res[$key]) ? $res[$key] : false;
    }
    return $res;
}


Worker::runAll();
