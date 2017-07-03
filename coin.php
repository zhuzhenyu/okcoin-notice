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
                echo message("服务器连接成功...");
                $ltc = subscribeChannel("ok_sub_spotcny_ltc_ticker");
                $_conn->send($ltc);
            };
            $conn->onMessage = function ($_conn, $data) {
                $data = json_decode($data, true);
                $_data = $data["data"];
//                var_dump($_data);
                if (isset($_data["result"])) {
                    echo message("莱特币-行情定义成功。");
//                    echo  "莱特币-行情订阅成功！\n";
                }

            };

            //连接出错
            $conn->onError = function ($_conn, $code, $msg) {
                //支持断线重连
                echo "error: $msg\n";
                global $reConnect;
                $reConnect = 0;

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
function wechatPush($text, $desp)
{
    $posData = http_build_query(
        array(
            'text' => $text,
            'desp' => $desp
        )
    );

    $opts = array('http' =>
        array(
            'method' => 'POST',
            'header' => 'Content-type: application/x-www-form-urlencoded',
            'content' => $posData
        )
    );
    $context = stream_context_create($opts);
    return $result = file_get_contents('https://sc.ftqq.com/' . WECHAT_SECRET . '.send', false, $context);
}


function message($message)
{
    $dateTime = date('Y-m-d H:i:s');
    return "[{$dateTime}]：{$message}\n";
}

Worker::runAll();
