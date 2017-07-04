<?php

/**
 * 打印日志
 * @param $message
 */
function message($message)
{
    $dateTime = date('Y-m-d H:i:s');
    echo "[{$dateTime}]：{$message}\n";
}


/**
 * @desc 微信推送
 * @param $text
 * @param $desp
 * @return bool|string
 */
function pushBear($text, $desp, $key = null)
{
    $param = [
        'sendkey' => $key,
        "text" => $text,
        "desp" => $desp
    ];
    if ($key) {
        return file_get_contents("https://pushbear.ftqq.com/sub?" . http_build_query($param));
    }
    message("pushBear key Not Found");
    return false;
}


/**
 * 获取配置信息
 * @param null $key
 * @param string $configPath
 * @return array|bool
 */
function getConfig($key = null, $configPath = "")
{
    $res = parse_ini_file($configPath, true);
    if ($key !== null) {
        return isset($res[$key]) ? $res[$key] : false;
    }
    return $res;
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