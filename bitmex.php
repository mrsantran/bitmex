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

namespace BitmexPHP;

/**
 * Main Bitmex class
 *
 * Eg. Usage: 
 * require 'vendor/autoload.php';
 * $api = new BitmexPHP\\API();
 */
class API {

    protected $ws = 'wss://www.bitmex.com/realtime?subscribe=';
    protected $ws_test = "wss://testnet.bitmex.com/realtime?subscribe=";
    protected $env;

    public function __construct($env = true) {
        $this->env = $env ? $this->ws : $this->ws_test;
    }

    public function orderBook10($symbols, $callback) {
        if (!is_array($symbols))
            $symbols = [$symbols];
        $loop = \React\EventLoop\Factory::create();
        $react = new \React\Socket\Connector($loop);
        $connector = new \Ratchet\Client\Connector($loop, $react);
        foreach ($symbols as $symbol) {
            $endpoint = 'orderBook10' . ':' . $symbol;
            $connector($this->env . $endpoint)->then(function ( $ws ) use ($callback, $symbol, $loop, $endpoint ) {
                $ws->on('message', function ( $data ) use ($ws, $loop, $symbol, $callback, $endpoint ) {
                    $json = json_decode($data, true);
                    if (isset($json["data"])) {
                        call_user_func($callback, $this, $symbol, $json["data"]);
                    }
                });
                $ws->on('close', function ( $code = null, $reason = null ) use ($symbol, $loop ) {
                    echo "orderBook10({$symbol}) WebSocket Connection closed! ({$code} - {$reason})" . PHP_EOL;
                    $loop->stop();
                });
            }, function ( $e ) use ($loop, $symbol ) {
                echo "orderBook10({$symbol})) Could not connect: {$e->getMessage()}" . PHP_EOL;
                $loop->stop();
            });
        }
        $loop->run();
    }

    public function trades($symbols, $callback) {
        if (!is_array($symbols))
            $symbols = [$symbols];
        $loop = \React\EventLoop\Factory::create();
        $react = new \React\Socket\Connector($loop);
        $connector = new \Ratchet\Client\Connector($loop, $react);
        foreach ($symbols as $symbol) {
            $endpoint = 'trade' . ':' . $symbol;
            $connector($this->env . $endpoint)->then(function ( $ws ) use ($callback, $symbol, $loop, $endpoint ) {
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

    public function instrument($symbols, $callback) {
        if (!is_array($symbols))
            $symbols = [$symbols];
        $loop = \React\EventLoop\Factory::create();
        $react = new \React\Socket\Connector($loop);
        $connector = new \Ratchet\Client\Connector($loop, $react);
        foreach ($symbols as $symbol) {
            $endpoint = 'instrument' . ':' . $symbol;
            $connector($this->env . $endpoint)->then(function ( $ws ) use ($callback, $symbol, $loop, $endpoint ) {
                $ws->on('message', function ( $data ) use ($ws, $loop, $symbol, $callback, $endpoint ) {
                    $json = json_decode($data, true);
                    if (isset($json["data"])) {
                        call_user_func($callback, $this, $symbol, $json["data"]);
                    }
                });
                $ws->on('close', function ( $code = null, $reason = null ) use ($symbol, $loop ) {
                    echo "instrument({$symbol}) WebSocket Connection closed! ({$code} - {$reason})" . PHP_EOL;
                    $loop->stop();
                });
            }, function ( $e ) use ($loop, $symbol ) {
                echo "instrument({$symbol})) Could not connect: {$e->getMessage()}" . PHP_EOL;
                $loop->stop();
            });
        }
        $loop->run();
    }

}
