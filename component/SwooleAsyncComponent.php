<?php
/**
 * 异步组件
 * time:2017-05-27
 * author:houpeng
 */
namespace xiaochengfu\swoole\component;

use linslin\yii2\curl\Curl;
use Yii;

class SwooleAsyncComponent extends \yii\base\Component
{

    const NORMAL = 1;
    const TYPE_SOCKET = 'socket';//socket请求
    const TYPE_WEB = 'web';//web任务
    const TYPE_CLI = 'cli';//cli任务
    /**
     * 异步执行入口
     * $data.data 定义需要执行的任务列表，其中如果指定多个任务(以数组形式),则server将顺序执行
     * $data.finish 定义了data中的任务执行完成后的回调任务，执行方式同$data.data
     * 格式为:
     * $data = [
     *   'type' => 'cli',
     *   'data' => [
     *         [
     *             'a' => 'test1/mail1' #要执行的console控制器和action
     *             'p' => ['p1','p2','p3'] // action参数列表
     *         ],
     *         [
     *             'a' => 'test2/mail2' #要执行的console控制器和action
     *             'p' => ['p1','p2','p3'] // action参数列表
     *         ]
     *     ],
     *     'finish' => [
     *         [
     *             'a' => 'test3/mail3' #要执行的console控制器和action
     *             'p' => ['p1','p2','p3'] // action参数列表
     *         ],
     *         [
     *             'a' => 'test4/mail4' #要执行的console控制器和action
     *             'p' => ['p1','p2','p3'] // action参数列表
     *         ]
     *     ]
     * ]
     * 使用范围：在websocket服务中，只有通过异步客户端swoole_http_client才可以与websocket通信,而且仅限于命令行
     * 由服务端的message回调函数接收
     */
    public function cliAsync($data){
        $settings = Yii::$app->params['swooleAsync'];
        $cli = new \swoole_http_client($settings['host'], $settings['port']);
        $len     = 20;
        $hashStr = substr(str_shuffle(str_repeat('abcdefghijklmnopqrstuvwxyz0123456789', $len)), 0, $len);
        $uuid = md5(uniqid($hashStr, true) . microtime(true) . mt_rand(0, 1000));
        $collection = Yii::$app->mongodb->getCollection ('queue');
        $collection->insert ( [
            'id'=>$uuid,
            'jobs'=>$data,
            'status'=>self::NORMAL,
            'addtime'=>date('Y-m-d h:i:s',time())
        ] );

        $cli->on('message', function ($cli, $frame) use ($data){
//            var_dump($frame);
        });

        $cli->upgrade('/', function ($cli)  use ($uuid){
            $cli->push(json_encode(['jobName'=>$uuid]));
            $cli->close();
        });
    }


    /**
     * @param $data '任务队列'
     * @return array
     * 往redis中投递异步任务，任务名称为随机字符串
     * 格式为:
     * $data = [
     *    'data' => [
     *         [
     *             'a' => 'test1/mail1' #要执行的console控制器和action
     *             'p' => ['p1','p2','p3'] // action参数列表
     *         ],
     *         [
     *             'a' => 'test2/mail2' #要执行的console控制器和action
     *             'p' => ['p1','p2','p3'] // action参数列表
     *         ]
     *     ],
     *     'finish' => [
     *         [
     *             'a' => 'test3/mail3' #要执行的console控制器和action
     *             'p' => ['p1','p2','p3'] // action参数列表
     *         ],
     *         [
     *             'a' => 'test4/mail4' #要执行的console控制器和action
     *             'p' => ['p1','p2','p3'] // action参数列表
     *         ]
     *     ]
     * ]
     */
    public function webTask($data){
        $settings = Yii::$app->params['swooleAsync'];
        $data['type'] = self::TYPE_WEB;
        $curl = new Curl();
        return $curl->setPostParams($data)->post($settings['swoole_http']);
    }

    /**
     * @param $data
     * @return array
     * 往mongodb中投递异步任务，任务名称为随机字符串
     * 格式为:
     *   'data' => [
     *         [
     *             'a' => 'test1/mail1' #要执行的console控制器和action
     *             'p' => ['p1','p2','p3'] // action参数列表
     *         ],
     *         [
     *             'a' => 'test2/mail2' #要执行的console控制器和action
     *             'p' => ['p1','p2','p3'] // action参数列表
     *         ]
     *     ],
     *     'finish' => [
     *         [
     *             'a' => 'test3/mail3' #要执行的console控制器和action
     *             'p' => ['p1','p2','p3'] // action参数列表
     *         ],
     *         [
     *             'a' => 'test4/mail4' #要执行的console控制器和action
     *             'p' => ['p1','p2','p3'] // action参数列表
     *         ]
     *     ]
     */
    public function mongodbTask($data){
        $settings = Yii::$app->params['swooleAsync'];
        $len     = 20;
        $hashStr = substr(str_shuffle(str_repeat('abcdefghijklmnopqrstuvwxyz0123456789', $len)), 0, $len);
        $uuid = md5(uniqid($hashStr, true) . microtime(true) . mt_rand(0, 1000));
        $collection = Yii::$app->mongodb->getCollection ('queue');
        $collection->insert ( [
            'id'=>$uuid,
            'jobs'=>$data,
            'status'=>self::NORMAL,
            'addtime'=>date('Y-m-d h:i:s',time())
        ] );
        $curl = new Curl();
        return $curl->setPostParams(['jobName'=>$uuid])->post($settings['swoole_http']);
    }

    /**
     * @param $fd
     * @param $data
     * @return mixed
     * websocket消息推送
     * 格式为:
     *   'fd' => xx,//客户端id
     *   'data' => [],//消息体
     */
    public function pushMsg($fd,$data){
        $settings = Yii::$app->params['swooleAsync'];
        $data['type'] = self::TYPE_SOCKET;
        $datas['data'] = $data;
        $datas['fd'] = $fd;
        $curl = new Curl();
        return $curl->setPostParams($data)->post($settings['swoole_http']);
    }
    
}