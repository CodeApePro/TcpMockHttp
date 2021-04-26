<?php
namespace TcpMockHttp;

class Pool{

    //对于相同的地址和端口,最多使用多少条tcp连接
    protected static $max_num = 10;
    protected static $connection_pool = [];

    public static function setMaxNum(int $max_num){
        self::$max_num = $max_num;
    }

    public static function call(String $method,String $url,String $body = '',array $options = []){
        $parse_url_info = parse_url($url);
        if(!isset($parse_url_info['port'])){
            $parse_url_info['port'] = 80;
        }
        $reuse_key = $parse_url_info['host'].':'.$parse_url_info['port'];
        if(!isset(self::$connection_pool[$reuse_key])){
            self::$connection_pool[$reuse_key] = [
                'list' => [],
                'used_num' => 0,
            ];
        }
        $_index = self::$connection_pool[$reuse_key]['used_num'] % self::$max_num;
        if(!isset(self::$connection_pool[$reuse_key]['list'][$_index])){
            self::$connection_pool[$reuse_key]['list'][$_index] = new Http($parse_url_info['host'],$parse_url_info['port']);
        }
        $pool = self::$connection_pool[$reuse_key]['list'][$_index];
        $send_num = $pool->sendData($method,$url,$body,$options);
        self::$connection_pool[$reuse_key]['used_num'] += 1;
        $decode_function = function($pool) use ($send_num){
            return $pool->recvData($send_num);
        };
        return new Decode($pool,$decode_function);
    }

    public static function clear(){
        self::$connection_pool = [];
    }

}

