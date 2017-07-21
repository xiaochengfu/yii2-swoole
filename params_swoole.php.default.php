<?php

/**
 * 配置文件示例
 */
return [
    'swooleAsync' => [
        'host'             => 'ip', 		//服务启动IP
        'port'             => '9512',      		//服务启动端口
        'swoole_http'      => 'http://ip:9512',//推送触发连接地址
        'process_name'     => 'swooleWebSocket',		//服务进程名
        'open_tcp_nodelay' => '1',         		//启用open_tcp_nodelay
        'daemonize'        => false,				//守护进程化
        'heartbeat_idle_time' => 180,               //客户端向服务端请求的间隔时间,单位秒(s)
        'heartbeat_check_interval' => 120,          //服务端向客户端发送心跳包的间隔时间，两参数要配合使用,单位秒(s)
        'worker_num'       => '2',				//work进程数目
        'task_worker_num'  => '2',				//task进程的数量
        'task_max_request' => '10000',			//work进程最大处理的请求数
        'pidfile'           => Yii::getAlias('@swoole').'/yii2-swoole/yii2-swoole.pid',
        'log_dir'           => Yii::getAlias('@swoole').'/yii2-swoole',
        'task_tmpdir'       => Yii::getAlias('@swoole').'/yii2-swoole',
        'log_file'          => Yii::getAlias('@swoole').'/yii2-swoole/swoole.log',
        'log_size'          => 204800000,       //运行时日志 单个文件大小
    ]
];