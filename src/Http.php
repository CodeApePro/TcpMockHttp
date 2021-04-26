<?php
namespace TcpMockHttp;

class Http{
    
    //一次读取获取的数据量,可按照实际情况修改
    const READ_LENGTH = 8192;

    const DEFAULT_PORT = 80;
    const DEFAULT_DELIMITER = "\r\n";
    const DEFAULT_PROTOCOL = 'HTTP/1.1';
    const DEFAULT_UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.198 Safari/537.36';
    const SUCCESS_RESPONSE = 'HTTP/1.1 200 OK';
    const DEFAULT_TIMEO = 20;

    protected $connection = null;
    protected $host = '';
    protected $port = 80;

    protected $response_list = [];
    protected $response_str = '';
    protected $send_hit = 0;

    protected $last_mode = 'status';
    protected $last_once_response = [];
    protected $last_temp_data = [];

    public function __construct($host,$port = self::DEFAULT_PORT) {
        $this->host = $host;
        $this->port = $port;
        $this->connection = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($this->connection === false) {
            throw new \Exception('连接创建失败: ' . socket_strerror(socket_last_error())); 
        }
        $result = socket_connect($this->connection,$host,$port);
        if ($result === false) {
            throw new \Exception( '连接失败. ' . socket_strerror(socket_last_error())); 
        }
    }

    /**
     * $options 支持如下参数
     * ua:设置请求UA
     * read_timeout:读取超时时间,默认20s
     * send_timeout:发送超时时间,默认20s
     * header:其他请求头,数组格式
     */
    public function sendData(String $method,String $url,String $body = '',array $options = []){
        $method = strtoupper($method);
        $parse_url_info = parse_url($url);
        if($parse_url_info['scheme'] != 'http'){
            throw new \Exception('只能支持HTTP协议'); 
        }
        if(!isset($parse_url_info['port'])){
            $parse_url_info['port'] = self::DEFAULT_PORT;
        }
        if(!isset($parse_url_info['path'])){
            $parse_url_info['path'] = '/';
        }
        if(!isset($parse_url_info['query'])){
            $parse_url_info['query'] = '';
        }
        if($parse_url_info['port'] != $this->port || $parse_url_info['host'] != $this->host){
            throw new \Exception('地址或端口不一致'); 
        }
        if(isset($options['read_timeout'])){
            socket_set_option($this->connection,SOL_SOCKET,SO_RCVTIMEO,['sec'=> $options['read_timeout'],'usec'=> 0]);
        }else{
            socket_set_option($this->connection,SOL_SOCKET,SO_RCVTIMEO,['sec'=> self::DEFAULT_TIMEO,'usec'=> 0]);
        }
        if(isset($options['send_timeout'])){
            socket_set_option($this->connection,SOL_SOCKET,SO_SNDTIMEO,['sec'=> $options['send_timeout'],'usec'=> 0]);
        }else{
            socket_set_option($this->connection,SOL_SOCKET,SO_SNDTIMEO,['sec'=> self::DEFAULT_TIMEO,'usec'=> 0]);
        }
        $full_path = $parse_url_info['query'] ? $parse_url_info['path'] . '?' . $parse_url_info['query'] : $parse_url_info['path'];
        $request_str = $method . ' ' . $full_path . ' ' . self::DEFAULT_PROTOCOL;
        $request_str .= self::DEFAULT_DELIMITER . 'Host: '.($parse_url_info['port'] == self::DEFAULT_PORT ? $parse_url_info['host'] : $parse_url_info['host'].':'.$parse_url_info['port']);
        $request_str .= self::DEFAULT_DELIMITER . 'User-Agent: '.($options['ua'] ? $options['ua'] : self::DEFAULT_UA);
        $request_str .= self::DEFAULT_DELIMITER . 'Accept: */*';
        $request_str .= self::DEFAULT_DELIMITER . 'Connection: keep-alive';
        $request_str .= self::DEFAULT_DELIMITER . 'Content-Length: '.strlen($body);
        if(isset($options['header']) && is_array($options['header'])){
            foreach($options['header'] as $header_val){
                $request_str .= self::DEFAULT_DELIMITER . $header_val;
            }
        }
        $request_str .= self::DEFAULT_DELIMITER . self::DEFAULT_DELIMITER . $body;
        if(!socket_write($this->connection, $request_str, strlen($request_str))) {
            throw new \Exception('连接发送数据失败');
        }
        $this->send_hit = $this->send_hit + 1;
        return $this->send_hit;   
    }

    public function recvData($hit){
        $hit = $hit - 1;
        if(isset($this->response_list[$hit])){
            if($this->response_list[$hit]['status'] == self::SUCCESS_RESPONSE){
                return $this->response_list[$hit]['body'];
            }else{
                throw new \Exception('响应错误:'.$this->response_list[$hit]['status']);
            }
        }else{
            for($i = 0;$i <= $hit;$i++){
                $this->_innerRecvData();
                if(isset($this->response_list[$hit])){
                    if($this->response_list[$hit]['status'] == self::SUCCESS_RESPONSE){
                        return $this->response_list[$hit]['body'];
                    }else{
                        throw new \Exception('响应错误:'.$this->response_list[$hit]['status']);
                    }
                }
            }
            throw new \Exception('无法获取到数据.');
        }
    }

    private function _innerRecvData(){
        if($this->connection === null){
            throw new \Exception('连接不存在');
        }
        //模式
        //status 获取响应状态和协议
        //header_name 获取响应头名称
        //header_val 获取响应头值
        //content 获取响应体,响应头为Content-Length时
        //chunked 获取响应体,响应头为Transfer-Encoding时
        //chunked_end Transfer-Encoding时的最后一行
        while(true) {
            $need_break = false;
            $out = socket_read($this->connection,self::READ_LENGTH);
            $this->response_str .= $out;
            $out_len = strlen($out);
            $i = 0;
            while($i < $out_len) {
                $val = $out[$i];
                switch ($this->last_mode) {
                    case 'status':
                        if($val === "\r"){

                        }elseif($val === "\n"){
                            $this->last_mode = 'header_name';
                        }else{
                            $this->last_once_response['status'] .= $val;
                        }
                        break;
                    case 'header_name':
                        if($val === "\r"){

                        }elseif($val === "\n"){
                            $this->last_mode = 'content';
                        }elseif($val === ":"){
                            $this->last_mode = 'header_val';
                        }else{
                            $this->last_temp_data['header_name'] .= $val;
                        }
                        break;
                    case 'header_val':
                        if($val === "\r"){

                        }elseif($val === "\n"){
                            $this->last_mode = 'header_name';
                            $this->last_once_response['header'][$this->last_temp_data['header_name']] = trim($this->last_temp_data['header_val']);
                            $this->last_temp_data['header_name'] = '';
                            $this->last_temp_data['header_val'] = '';
                        }else{
                            $this->last_temp_data['header_val'] .= $val;
                        }
                        break;
                    case 'content':
                        if(isset($this->last_once_response['header']['Content-Length'])){
                            $body_str_len = strlen($this->last_once_response['body']);
                            if($body_str_len < $this->last_once_response['header']['Content-Length']){
                                $diff = $this->last_once_response['header']['Content-Length'] - $body_str_len;
                                $this->last_once_response['body'] .= substr($out,$i,$diff);
                                $i = $i + $diff - 1;
                            }
                            if(strlen($this->last_once_response['body']) >= $this->last_once_response['header']['Content-Length']){
                                $this->response_list[] = $this->last_once_response;
                                $this->last_once_response = [];
                                $this->last_temp_data = [];
                                $this->last_mode = 'status';
                                $need_break = true;
                            }
                        }elseif(isset($this->last_once_response['header']['Transfer-Encoding']) && $this->last_once_response['header']['Transfer-Encoding'] == 'chunked'){
                            if($val === "\r"){

                            }elseif($val === "\n"){
                                $this->last_mode = 'chunked';
                                $this->last_temp_data['chunked_once_length'] = (int)hexdec($this->last_temp_data['chunked_once_length']);
                                $this->last_temp_data['chunked_once_body'] = '';
                            }else{
                                $this->last_temp_data['chunked_once_length'] .= $val;
                            }
                        }
                        break;
                    case 'chunked':
                        if($this->last_temp_data['chunked_once_length'] === 0){
                            if($val === "\n"){
                                $this->last_mode = 'chunked_end';
                            }
                        }else{
                            $body_str_len = strlen($this->last_temp_data['chunked_once_body']);
                            if($body_str_len < $this->last_temp_data['chunked_once_length']){
                                $diff = $this->last_temp_data['chunked_once_length'] - $body_str_len;
                                $this->last_temp_data['chunked_once_body'] .= substr($out,$i,$diff);
                                $i = $i + $diff - 1;
                            }
                            if(strlen($this->last_temp_data['chunked_once_body']) >= $this->last_temp_data['chunked_once_length']){
                                $this->last_mode = 'content';
                                $this->last_once_response['body'] .= $this->last_temp_data['chunked_once_body'];
                                $this->last_temp_data['chunked_once_body'] = '';
                                $this->last_temp_data['chunked_once_length'] = '';
                            }
                        }
                        break;
                    case 'chunked_end':
                        if($val === "\n"){
                            $this->response_list[] = $this->last_once_response;
                            $this->last_once_response = [];
                            $this->last_temp_data = [];
                            $this->last_mode = 'status';
                            $need_break = true;
                        }
                        break;
                    default :
                        $need_break = true;
                        break;
                }
                $i ++;
            }
            if($need_break){
                break;
            }
        }
        return true;
    }

    public function __destruct() {
        if($this->connection !== null){
            socket_close($this->connection);
        }
    }
}

