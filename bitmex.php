<?php

/*
 * ============================================================
 * @package bitmex
 * @link https://github.com/mrsantran/bitmex
 * ============================================================
 * @copyright 2018
 * @author San Tran
 * @license MIT License
 * ============================================================
 * Bitmex Websocket
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

    protected $ws = 'wss://www.bitmex.com/realtime?subscribe=';
    protected $api_key;
    protected $api_secret;

    public function __construct($api_key = '', $api_secret = '') {
        $this->api_key = $api_key;
        $this->api_secret = $api_secret;
    }

    public function trades($symbols, $callback) {
        if (!is_array($symbols))
            $symbols = [$symbols];
        $loop = \React\EventLoop\Factory::create();
        $react = new \React\Socket\Connector($loop);
        $connector = new \Ratchet\Client\Connector($loop, $react);
        foreach ($symbols as $symbol) {
            $endpoint = 'trade' . ':' . $symbol;
            $connector($this->ws . $endpoint)->then(function ( $ws ) use ($callback, $symbol, $loop, $endpoint ) {
                $ws->on('message', function ( $data ) use ($ws, $loop, $symbol, $callback, $endpoint ) {
                    $json = json_decode($data, true);
                    if (isset($json["data"])) {
                        call_user_func($callback, $this, $symbol, $json["data"]);
                    }
                });
                $ws->on('close', function ( $code = null, $reason = null ) use ($symbol, $loop ) {
                    echo "trade({$symbol}) WebSocket Connection closed! ({$code} - {$reason})" . PHP_EOL;
                    $loop->stop();
                });
            }, function ( $e ) use ($loop, $symbol ) {
                echo "trade({$symbol})) Could not connect: {$e->getMessage()}" . PHP_EOL;
                $loop->stop();
            });
        }
        $loop->run();
    }

}
