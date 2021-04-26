<?php

include './src/Http.php';
include './src/Pool.php';
include './src/Decode.php';
include './src/Helper.php';

use TcpMockHttp\Http;
use TcpMockHttp\Pool;
use TcpMockHttp\Helper;
use TcpMockHttp\Decode;

//直接使用原始的模拟请求类
$http = new Http('api.ipify.org',80);
$sendResult = $http->sendData('GET','http://api.ipify.org/?format=json');
$recvResult = $http->recvData($sendResult);
print_r($recvResult);
echo PHP_EOL;

//推荐:使用Pool类,使用简单,还可以支持设置相同地址端口时最大连接数
//Pool::setMaxNum(5);
$result1 = Pool::call('GET','http://api.ipify.org/?format=json');
$result2 = Pool::call('GET','http://api.ipify.org/?format=json');
Helper::wait($result1);
Helper::wait($result2);
print_r($result1);
echo PHP_EOL;
print_r($result2);
echo PHP_EOL;

//如果你需要对请求结果进行处理,但是又不希望在此处就对请求结果进行阻塞获取,期望这个方法本身也是可以并发的,则可以使用Decode类对结果进行包装来处理
//通过不断的嵌套Decode,可以跳出多个层级进行数据后续处理
//此处在实现时也可以使用yield关键字,可以使代码可读性更好不需要回调函数,但是出于个人习惯和掌握程度的考虑,这里使用回调方法来实现
function testGetData(){
    $result = Pool::call('GET','http://api.ipify.org/?format=json');
    $decode_function = function($result){
        return json_decode($result,true);
    };
    return new Decode($result,$decode_function);
}
$result = testGetData();
Helper::wait($result);
print_r($result);
echo PHP_EOL;