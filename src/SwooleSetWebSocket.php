<?php
/**
 * Swoole 实现的 http server,用来处理异步多进程任务
 * author:houpeng
 * time:2017-05-29
 */

namespace xiaochengfu\swoole\src;

use xiaochengfu\swoole\models\Queue;

class SwooleSetWebSocket{

    private $server = null;

    /**
     * swoole 配置
     * @var array
     */
    private $setting = [];

    /**
     * Yii::$app 对象
     * @var array
     */
    private $app = null;

    /**
     * [__construct description]
     * @param string  $host [description]
     * @param integer $port [description]
     * @param string  $env  [description]
     */
    public function __construct($setting,$app){
        $this->setting = $setting;
        $this->app = $app;
    }

    /**
     * 设置swoole进程名称
     * @param string $name swoole进程名称
     */
    private function setProcessName($name){
        if (function_exists('cli_set_process_title')) {
            cli_set_process_title($name);
        } else {
            if (function_exists('swoole_set_process_name')) {
                swoole_set_process_name($name);
            } else {
                trigger_error(__METHOD__. " failed.require cli_set_process_title or swoole_set_process_name.");
            }
        }
    }

    /**
     * 运行服务
     * @return [type] [description]
     */
    public function run(){
        $this->server = new \swoole_websocket_server($this->setting['host'], $this->setting['port']);
        $this->server->set($this->setting);
        //回调函数
        $call = [
            'start',
            'workerStart',
            'managerStart',
            'open',
            'task',
            'finish',
            'close',
            'message',
            'receive',
            'request',
            'workerStop',
            'shutdown',
        ];
        //事件回调函数绑定
        foreach ($call as $v) {
            $m = 'on' . ucfirst($v);
            if (method_exists($this, $m)) {
                $this->server->on($v, [$this, $m]);
            }
        }

        echo "服务成功启动" . PHP_EOL;
        echo "服务运行名称:{$this->setting['process_name']}" . PHP_EOL;
        echo "服务运行端口:{$this->setting['host']}:{$this->setting['port']}" . PHP_EOL;

        return $this->server->start();
    }

    /**
     * [onStart description]
     * @param  [type] $server [description]
     * @return [type]         [description]
     */
    public function onStart($server){
        echo '[' . date('Y-m-d H:i:s') . "]\t swoole_websocket_server master worker start\n";
        $this->setProcessName($server->setting['process_name'] . '-master');
        //记录进程id,脚本实现自动重启
        $pid = "{$this->server->master_pid}\n{$this->server->manager_pid}";
        file_put_contents($this->setting['pidfile'], $pid);
        return true;
    }

    /**
     * [onManagerStart description]
     * @param  [type] $server [description]
     * @return [type]         [description]
     */
    public function onManagerStart($server){
        echo '[' . date('Y-m-d H:i:s') . "]\t swoole_http_server manager worker start\n";
        $this->setProcessName($server->setting['process_name'] . '-manager');
    }

    /**
     * [onWorkerStart description]
     * @param  [type] $server   [description]
     * @param  [type] $workerId [description]
     * @return [type]           [description]
     */
    public function onWorkerStart($server, $workerId){
        if ($workerId >= $this->setting['worker_num']) {
            $this->setProcessName($server->setting['process_name'] . '-task');
        } else {
            $this->setProcessName($server->setting['process_name'] . '-event');
        }
    }

    /**
     * [onWorkerStop description]
     * @param  [type] $server   [description]
     * @param  [type] $workerId [description]
     * @return [type]           [description]
     */
    public function onWorkerStop($server, $workerId){
        echo '['. date('Y-m-d H:i:s') ."]\t swoole_http_server[{$server->setting['process_name']}  worker:{$workerId} shutdown\n";
    }

    /**
     * @param $server
     * @param $frame
     * 接收websocket客户端信息，并实时返回
     */
    public function onMessage($server, $frame){
        echo "receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}\n";

        /**
         * 功能一:获取websocket的连接信息
         */
        if($frame->data == 'stats'){
            $websocket_number['websocket_number'] = count($this->server->connection_list(0,100));
            array_push($websocket_number,$this->server->stats());
            return $this->server->push($frame->fd,json_encode($websocket_number));
        }

        /**
         * 功能二:通过cli发起的异步任务
         */
        $requestData = json_decode($frame->data);
        if(isset($requestData->jobName) && !empty($requestData->jobName)){
            //mongodb请求任务
            $jobName = $requestData->jobName;
            $job = $queue = Queue::findOne(['id'=>$jobName]);
            if(empty($job)){
                $this->logger('[ warning-task data]-id为'.$jobName.'的任务未找到!');
                $queue['jobs'] = false;
            }else{
                $job->status = 3;//将任务删除
                if($job->save(false) === false){
                    $this->logger('[ warning-task data]-id为'.$jobName.'任务以投递,但未清空任务,请检测mongo入库情况!');
                };
            }
            $this->server->task(json_encode($queue['jobs']));
        }else{

            /**
             * 功能三:
             * 扩展部分,根据客户端发来的命令{$frame->data}来做出相应的处理,这里根据自己的需求来写不做处理...
             * 推荐使用yii2的runActon方法来做扩展处理,来实现解耦,主要通过client发来的socket指令data来自定义区分逻辑控制器
             * 推荐data协议指令：data=>['a'=>'test/test','p'=>['a'=>1]],a为控制器,p为参数
             *
             *
             * 你的控制器console/controllers可能是这样的:
             *  public function actionTest($param){
             *      $info = $param['data'];
             *      $param['server']->push($param['fid'],json_encode($info));
             *  }
             */
            if (isset($requestData->data->a) && $requestData->data->a) {
                \Yii::$app->runAction($requestData->data->a, [['server'=>$server,'fid'=> $frame->fd,'data'=>$requestData->data->p]]);
            }else{
                $server->push($frame->fd, "终于等到你啦!");
            }
        }



    }

    /**
     * http请求
     * @param $request
     * @param $response
     * @return mixed
     * 用于处理异步任务(urlweb任务,和console任务);用于处理推送消息(websocket的推送)
     */
    public function onRequest($request, $response){
        if(!empty($request->post) && is_array($request->post)){
            $requestData = $request->post;
            if(isset($requestData['type']) && $requestData['type'] == 'web'){
                //url请求任务
                $this->server->task(json_encode($requestData));
            }elseif(isset($requestData['type']) && $requestData['type'] == 'socket'){
                //websocket推送消息到客户端
                $this->server->push($requestData['fd'],$requestData['data']);
            }else{
                //mongodb请求任务
                $jobName = $request->post['jobName'];
                $job = $queue = Queue::findOne(['id'=>$jobName]);
                if(empty($job)){
                    $this->logger('[ warning-task data]-id为'.$jobName.'的任务未找到!');
                    $queue['jobs'] = false;
                }else{
                    $job->status = 3;//将任务删除
                    if($job->save(false) === false){
                        $this->logger('[ warning-task data]-id为'.$jobName.'任务以投递,但未清空任务,请检测mongo入库情况!');
                    };
                }
                $this->server->task(json_encode($queue['jobs']));
            }
        }
        $response->end(json_encode($this->server->stats()));
    }


    /**
     * 解析data对象
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    private function parseData($data){
        $data = json_decode($data,true);
        $data = $data ?: [];
        if(!isset($data["data"]) || empty($data["data"])){
            return false;
        }
        return $data;
    }



    /**
     * 任务处理
     * @param $server
     * @param $taskId
     * @param $fromId
     * @param $request
     * @return mixed
     */
    public function onTask($serv, $task_id, $from_id, $data){
        $this->logger('[task data] '.$data);
        $data = $this->parseData($data);
        if($data === false){
            return false;
        }
        foreach ($data['data'] as $param) {
            if(!isset($param['a']) || empty($param['a'])){
                continue;
            }
            $action = $param["a"];
            $params = [];
            if(isset($param['p'])){
                $params = $param['p'];
                if(!is_array($params)){
                    $params = [strval($params)];
                }
            }
            try{
                if(isset($data['type']) && $data['type'] === 'web'){
                    if ($action) {
                        $res = $this->httpGet($action,$params);
                        $this->logger('[task result]-web任务执行成功!'.var_export($res,true));
                    }
                }else{
                    $parts = $this->app->createController($action);
                    if (is_array($parts)) {
                        $res = $this->app->runAction($action,$params);
                        $this->logger('[task result] '.var_export($res,true));
                    }
                }
            }catch(\yii\base\Exception $e){
                $this->logger($e);
            }
        }
        return $data;
    }


    protected function httpGet($url,$data){
        if ($data) {
            $url .='?'.http_build_query($data) ;
        }
        $curlObj = curl_init();    //初始化curl，
        curl_setopt($curlObj, CURLOPT_URL, $url);   //设置网址
        curl_setopt($curlObj, CURLOPT_RETURNTRANSFER, 1);  //将curl_exec的结果返回
        curl_setopt($curlObj, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curlObj, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($curlObj, CURLOPT_HEADER, 0);         //是否输出返回头信息
        $response = curl_exec($curlObj);   //执行
        curl_close($curlObj);          //关闭会话
        return $response;
    }

    /**
     * 解析onfinish数据
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    private function genFinishData($data){
        if(!isset($data['finish']) || !is_array($data['finish'])){
            return false;
        }
        return json_encode(['data'=>$data['finish']]);
    }

    /**
     * 任务结束回调函数
     * @param  [type] $server [description]
     * @param  [type] $taskId [description]
     * @param  [type] $data   [description]
     * @return [type]         [description]
     */
    public function onFinish($server, $taskId, $data){

        $data = $this->genFinishData($data);

        if($data !== false ){
            return $this->server->task($data);
        }
        return true;
    }

    /**
     * @param $server
     * @param $request
     * websocket连接的回调函数
     */
    public function onOpen($server, $request){
        echo "server: websocketclient success with fd{$request->fd}\n";
    }

    /**
     * [onShutdown description]
     * @return [type] [description]
     * 客户端关闭后,服务端的消息回调
     */
    public function onClose($ser, $fd){
        echo "client {$fd} closed\n";
    }


    /**
     * 记录日志 日志文件名为当前年月（date("Y-m")）
     * @param  [type] $msg 日志内容
     * @return [type]      [description]
     */
    public function logger($msg,$logfile='') {
        if (empty($msg)) {
            return;
        }
        if (!is_string($msg)) {
            $msg = var_dump($msg);
        }
        //日志内容
        $msg = '['. date('Y-m-d H:i:s') .'] '. $msg . PHP_EOL;
        //日志文件大小
        $maxSize = $this->setting['log_size'];
        //日志文件位置
        $file = $logfile ?: $this->setting['log_dir']."/".date('Y-m').".log";
        //切割日志
        if (file_exists($file) && filesize($file) >= $maxSize) {
            $bak = $file.'-'.time();
            if (!rename($file, $bak)) {
                error_log("rename file:{$file} to {$bak} failed", 3, $file);
            }
        }
        error_log($msg, 3, $file);
    }

    /**
     * @param $server
     * @param $fd
     * @param $from_id
     * @param $data
     * @return bool
     * 已废弃
     * 处理tcp客户端请求,由于开启的服务为websocket,tcp客户端无法与其通信,全部功能转到request回调中
     */
    public function onReceive($server, $fd, $from_id, $data){
        if($data == 'stats'){
            return $this->server->send($fd,var_export($this->server->stats(),true),$from_id);
        }
        $this->server->task($data);//非阻塞的，将任务扔到任务池，并及时返还
        return true;

    }
}

