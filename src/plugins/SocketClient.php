<?php

/*!
 * metowolf BilibiliHelper
 * https://i-meto.com/
 *
 * Copyright 2018, metowolf
 * Released under the MIT license
 */

namespace BilibiliHelper\Plugin;

use BilibiliHelper\Lib\Log;
use BilibiliHelper\Lib\Curl;
use Wrench\Client;
use Socket\Raw\Factory;

class SocketClient extends Base
{
    const PLUGIN_NAME = 'socketClient';

    protected static function init()
    {
        if (static::data('socket') === NULL) {
            $factory = new Factory();
            try {
                $socket = $factory
                    ->createClient('tcp://' . getenv('SOCKET_SERVER_ADDR') . ':' . getenv('SOCKET_SERVER_PORT'))
                    ->setBlocking(false);
                static::data('socket', $socket);
            } catch (\Exception $e) {
                Log::warning('无法连接到指定监听服务器！将会在下一个循环重试。', [$e->getMessage()]);
            }
        }
    }

    protected static function work()
    {
        if (static::data('socket') !== NULL) {
            self::checkConnection();
            $content = self::getContent();
            $parse = self::parse($content);
            self::unpack($parse);
            Log::debug('解包完成！', $parse);
        }
    }

    protected static function checkConnection()
    {
        $socket = static::data('socket');
        try {
            $socket->write('success');
            Log::debug('监听服务器心跳包已正常发送！', []);
        } catch (\Exception $e) {
            // Looks like disconnect
            Log::warning('与监听服务器的连接好像断开了！', [$e->getMessage()]);
            $socket->close();
            static::$config['data'][static::PLUGIN_NAME]['socket'] = NULL;
        }
    }

    protected static function getContent()
    {
        $result = '';
        $socket = static::data('socket');
        while (true) {
            try {
                $tmp = $socket->read(1);
                $result .= $tmp;
            } catch (\Exception $e) {
                break;
                // Finished
            }
        }
        if (empty($result)) {
            Log::debug('本次没有获得数据！', []);
            return '[]';
        }
        Log::debug('本次获得了数据：' . $result, []);
        return $result;
    }

    protected static function parse($data)
    {
        $result = json_decode($data, true);
        if ($result === NULL) {
            Log::warning('监控服务器传入了无法解析的数据！:' . $data, []);
            return [];
        } else if (empty($result)) {
            return [];
        }
        return $result;
    }

    protected static function unpack(array $data)
    {
        foreach ($data as $pluginName => $section) {
            foreach ($section as $sectionName => $value) {
                foreach ($value as $pair) {
                    foreach ($pair as $k => $v) {
                        self::setValue($pluginName, $sectionName, $k, $v);
                    }
                }
            }
        }
    }

    protected static function setValue($pluginName, $section, $key, $value)
    {
        static::$config['data'][$pluginName][$section][$key] = $value;
        Log::debug('正在设置数据中： ' . $pluginName . ' ' . $section . ' ' . $key . ' ' . $value, []);
    }
}
