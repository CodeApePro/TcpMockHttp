# TcpMockHttp
### 简介
一个简单的http并发请求类,原理是通过sockets扩展创建tcp连接,在tcp连接上传输符合http1.1协议的数据,从而将发送数据与读取结果分开来进行实现.
仅依赖sockets扩展,可在fpm环境使用.
适合php服务端需要大量多次获取不同其他http api时使用.

### 使用示例


### 其他说明

