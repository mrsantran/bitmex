<?php

/*
 * ============================================================
 * @package bitmex
 * @link https://github.com/santran/bitmex
 * ============================================================
 * @copyright 2018
 * @author San Tran
 * @license MIT License
 * ============================================================
 * A curl HTTP REST wrapper for the bitmex currency exchange
 */

namespace Bitmex;

/**
 * Main Bitmex class
 *
 * Eg. Usage:
 * require 'vendor/autoload.php';
 * $api = new Bitmex\\API();
 */
class API {

    protected $base = 'https://www.bitmex.com/api/v1/';
    protected $stream = 'wss://www.bitmex.com/realtime?subscribe=';
    protected $api_key;
    protected $api_secret;
    protected $chartQueue = [];
    protected $charts = [];
    private $ch;
    public $error;
    public $printErrors = false;
    public $errorCode;
    public $errorMessage;

    public function __construct($api_key = '', $api_secret = '') {
        $this->api_key = $api_key;
        $this->api_secret = $api_secret;
    }

    public function apiRequest($url, $method = "GET", $opt = []) {
        
    }

    private function publicQuery($data) {

        $function = $data['function'];
        $params = http_build_query($data['params']);
        $url = $this->base . $function . "?" . $params;

        $headers = array();

        $headers[] = 'Connection: Keep-Alive';
        $headers[] = 'Keep-Alive: 90';

        curl_reset($this->ch);
        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);
        $return = curl_exec($this->ch);

        if (!$return) {
            $this->curlError();
            $this->error = true;
            return false;
        }

        $return = json_decode($return, true);

        if (isset($return['error'])) {
            $this->platformError($return);
            $this->error = true;
            return false;
        }

        $this->error = false;
        $this->errorCode = false;
        $this->errorMessage = false;

        return $return;
    }

    public function getCandles($symbol, $timeFrame, $count = 100, $offset = 0) {

        $data['function'] = "trade/bucketed";
        $data['params'] = array(
            "symbol" => $symbol,
            "count" => $count,
            "binSize" => $timeFrame,
            "partial" => "false",
            "reverse" => "true"
        );

        $return = $this->publicQuery($data);

        $candles = array();

        // Converting
        foreach ($return as $item) {

            $time = strtotime($item['timestamp']) + $offset; // Unix time stamp

            $candles[$time] = array(
                'timestamp' => date('Y-m-d H:i:s', $time), // Local time human-readable time stamp
                'time' => $time,
                'open' => $item['open'],
                'high' => $item['high'],
                'close' => $item['close'],
                'low' => $item['low'],
                "openTime" => 0,
                "closeTime" => 0
            );
        }

        // Sorting candles from the past to the present
        ksort($candles);

        return $candles;
    }
    
    public function candlesticks($symbol, $interval = "1m") {
        if (!isset($this->charts[$symbol])) {
            $this->charts[$symbol] = [];
        }

        $response = $this->getCandles($symbol, $interval);
        $ticks = $this->chartData($symbol, $interval, $response);
        $this->charts[$symbol][$interval] = $ticks;
        return $ticks;
    }

    private function chartData($symbol, $interval, $ticks) {
        $output = [];
        foreach ($ticks as $tick) {
            list( $openTime, $open, $high, $low, $close, $closeTime, $volume) = $tick;
            $output[$openTime] = [
                "open" => $open,
                "high" => $high,
                "low" => $low,
                "close" => $close,
                "volume" => $volume,
                "openTime" => $openTime,
                "closeTime" => $closeTime
            ];
        }
        return $output;
    }

    private function chartHandler($symbol, $interval, $chart) {
        $tick = date("YmdHi", strtotime($chart["timestamp"]));
        $open = $chart["open"];
        $high = $chart["high"];
        $low = $chart["low"];
        $close = $chart["close"];
        $volume = $chart["volume"];
        $this->charts[$symbol][$interval][$tick] = [
            "open" => $open,
            "high" => $high,
            "low" => $low,
            "close" => $close,
            "volume" => $volume
        ];
    }

    public function chart($symbols, $interval = "1m", $callback) {
        if (!is_array($symbols))
            $symbols = [
                $symbols
            ];
        $loop = \React\EventLoop\Factory::create();
        $react = new \React\Socket\Connector($loop);
        $connector = new \Ratchet\Client\Connector($loop, $react);
        foreach ($symbols as $symbol) {
            if (!isset($this->charts[$symbol]))
                $this->charts[$symbol] = [];
            $this->charts[$symbol][$interval] = [];
            if (!isset($this->chartQueue[$symbol]))
                $this->chartQueue[$symbol] = [];
            $this->chartQueue[$symbol][$interval] = [];

            $endpoint = 'tradeBin' . $interval . ':' . $symbol;
            $connector($this->stream . $endpoint)->then(function ( $ws ) use ($callback, $symbol, $loop, $endpoint, $interval ) {
                $ws->on('message', function ( $data ) use ($ws, $loop, $interval, $callback, $endpoint ) {
                    $json = json_decode($data, true);
                    $chart = $json["data"][0];
                    $symbol = $json["data"][0]["symbol"];
                    $this->chartHandler($symbol, $interval, $chart);
                    call_user_func($callback, $this, $symbol, $this->charts[$symbol][$interval]);
                });
                $ws->on('close', function ( $code = null, $reason = null ) use ($symbol, $loop, $interval ) {
                    echo "chart({$symbol},{$interval}) WebSocket Connection closed! ({$code} - {$reason})" . PHP_EOL;
                    $loop->stop();
                });
            }, function ( $e ) use ($loop, $symbol, $interval ) {
                echo "chart({$symbol},{$interval})) Could not connect: {$e->getMessage()}" . PHP_EOL;
                $loop->stop();
            });
            $this->candlesticks($symbol, $interval);
            foreach ($this->chartQueue[$symbol][$interval] as $json) {
                $this->chartHandler($symbol, $interval, $json);
            }
            $this->chartQueue[$symbol][$interval] = [];
            call_user_func($callback, $this, $symbol, $this->charts[$symbol][$interval]);
        }
        $loop->run();
    }

}
