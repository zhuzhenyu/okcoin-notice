<?php
use Workerman\Worker;
use Workerman\Connection\AsyncTcpConnection;

include __DIR__ . '/vendor/autoload.php';
//okCoin API
const API_KEY = "";
const API_SECRET = "";
// 方糖微信通知api
const WECHAT_SECRET = "";
$coins = [
    //莱特币
    'ltc',
    //比特币
    'btc',
    //以太坊
    'eth'
];

echo "请输入参数来运行该程序\n";
echo "说明：\n";
echo "币种：0: ltc-莱特币,\t1: btc-比特币\t2: eth-以太坊\n";
echo "监控最低价位：支持小数点2位, 例如: 3.12\n";
echo "监控最高价位：支持小数点2位, 例如: 10.12\n";
echo "微信报警频率：秒数, 例如：10\n";
echo "Useage: \n";
echo "\t参数如下：币种,监控最低价位,监控最高价位,报警频率\n";
$fp = fopen("php://stdin", 'r');
$input = fgets($fp);
$input = trim($input);
$argvArr = explode(',', $input);
list($coin, $lowerPrice, $highPrice, $interval) = $argvArr;
echo "---------------------------------------------------\n";
if (!API_KEY || !API_SECRET || !WECHAT_SECRET) {
    die("请配置正确的key!");
}

//最大通知次数
$config['max_send_wechat_count'] = 100;
//币种
$config['coin'] = $coins[$coin];
//时间间隔
$config['interval'] = $interval;
//最高价格
$config['highPrice'] = $highPrice;
//最低价
$config['lowerPrice'] = $lowerPrice;
//上次微信通知时间
$config['pre_send_wechat'] = 0;
//微信通知次数
$config['send_wechat_count'] = 0;

$worker = new Worker();
$worker->onWorkerStart = function ($worker) {
    $wss = "ws://real.okcoin.cn:10440/websocket/okcoinapi";
    $conn = new AsyncTcpConnection($wss);
    $conn->transport = "ssl";
    $conn->onConnect = function ($conn) {
        global $config;
        echo "服务器连接成功.....\n";
        $ltc = subscribeChannel("ok_sub_spotcny_" . $config['coin'] . "_ticker");
        $conn->send($ltc);


    };

    //接收到消息
    $conn->onMessage = function ($conn, $data) {
        global $config;
        $data = json_decode($data, true);
        $_data = $data[0];
        if (isset($_data['result']) && $_data['result'] == true) {
            echo $config["coin"] . "-行情订阅成功！\n";
        }

        if (isset($_data['channel']) && $_data['channel'] == 'ok_sub_spotcny_' . $config["coin"] . '_ticker') {
            $datetime = date('Y-m-d H:i:s');
            $content = $_data['data'];
            $str = "------\n";
            $str .= "日期:\t" . $datetime . "\n";
            $str .= "买一价:\t" . $content['buy'] . "\n";
            $str .= "最高价:\t" . $content['high'] . "\n";
            $str .= "最低价:\t" . $content['low'] . "\n";
            $str .= "卖一价:\t" . $content['sell'] . "\n";
            $str .= "成交量:\t" . $content['vol'] . "\n";
            $str .= "最新成交价:\t" . $content['last'] . "\n";

            echo $str;

            $last = intval(round($content['last']) * 100);
            $compareLowerValue = $config['lowerPrice'] * 100;
            //最低价比较

            $time = time();
            if (($time - $config['pre_send_wechat']) >= $config['interval'] && $config['send_wechat_count'] <= $config['max_send_wechat_count']) {
                if ($last <= $compareLowerValue) {
                    //报警
                    wechatPush("[{$config['coin']}] - 低于 - {$config['lowerPrice']}价格", $str);
                    $config['pre_send_wechat'] = time();
                    $config['send_wechat_count'] += 1;
                }
                $compareHighValue = $config['highPrice'] * 100;

                if ($last >= $compareHighValue) {
                    wechatPush("[{$config['coin']}] - 高于 - {$config['highPrice']}价格", $str);
                    $config['pre_send_wechat'] = time();
                    $config['send_wechat_count'] += 1;

                }
            }
        }


    };


    //连接失败
    $conn->onError = function ($conn, $code, $msg) {
        //支持断线重连
        echo "error: $msg\n";
    };

    // 当连接远程websocket服务器的连接断开时
    $conn->onClose = function ($conn) {
        echo "connection closed\n";
        //断线重连
        $conn->reConnect(1);
        echo "reconnec...\n";
        
    };

    $conn->connect();

};


/**
 * @desc 生成签名
 * @param $param
 * @return string
 */
function generateSign($param)
{
    $str = encodeParam($param);
    $str .= "&secret_key=" . API_SECRET;
    return strtoupper(md5($str));
}

/**
 * @desc 参数编码
 * @param $param
 * @return string
 */
function encodeParam($param)
{
    ksort($param);
    $args = "";
    while (list($key, $value) = each($param)) {
        if ($key == "sign") {
            continue;
        }
        $args .= $key . '=' . $value . '&';
    }
    $args = rtrim($args, '&');
    return $args;
}


/**
 * @desc 登陆
 * @return string
 */
function login()
{
    $loginParam = [
        'event' => "login",
        'parameters' => [
            "api_key" => API_KEY,
            "sign" => "",
        ]
    ];
    $sign = generateSign($loginParam['parameters']);
    $loginParam['parameters']["sign"] = $sign;
    return json_encode($loginParam);
}

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
 * https://www.okcoin.cn/ws_api.html
 * @desc 下单
 * @param $symbol
 * @param $price
 * @param $amount
 * @return string
 */
function trade($symbol, $price, $amount)
{
    $param = [
        'event' => 'addChannel',
        'channel' => 'ok_spotcny_trade',
        'parameters' => [
            'api_key' => API_KEY,
            'symbol' => $symbol,
            'type' => 'buy',
            'price' => $price,
            'amount' => $amount
        ]
    ];
    $sign = generateSign($param['parameters']);
    $param['parameters']["sign"] = $sign;
    return json_encode($param);
}


/**
 * @desc 取消订单
 * @param $orderId
 * @param $symbol
 */
function cancelOrder($orderId, $symbol)
{

}


/**
 * @desc 微信推送
 * @param $text
 * @param $desp
 * @return bool|string
 */
function wechatPush($text, $desp)
{
    $postdata = http_build_query(
        array(
            'text' => $text,
            'desp' => $desp
        )
    );

    $opts = array('http' =>
        array(
            'method' => 'POST',
            'header' => 'Content-type: application/x-www-form-urlencoded',
            'content' => $postdata
        )
    );
    $context = stream_context_create($opts);
    return $result = file_get_contents('https://sc.ftqq.com/' . WECHAT_SECRET . '.send', false, $context);
}


Worker::runAll();