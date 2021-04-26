<?php
namespace TcpMockHttp;

class Helper {

    public static function wait(&$task){
        if($task instanceof Decode){
            $task = $task->decode();
        }
    }
    
}
