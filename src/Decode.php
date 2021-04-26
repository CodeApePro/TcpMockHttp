<?php
namespace TcpMockHttp;

class Decode {

    protected $mock_http = null;
    protected $decode_function = null;

    public function __construct($mock_http,\Closure $decode_function) {
        $this->mock_http = $mock_http;
        $this->decode_function = $decode_function;
    }

    public function decode(){
        if($this->mock_http instanceof Decode){
            $raw_result = $this->mock_http->decode();
            if($this->decode_function instanceOf \Closure) {
                $decode_function = $this->decode_function;
                return $decode_function($raw_result);
            }
        }else{
            $raw_result = $this->mock_http;
            if($this->decode_function instanceOf \Closure) {
                $decode_function = $this->decode_function;
                return $decode_function($raw_result);
            }
        }
    }

}
