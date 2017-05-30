# yii2 Swoole 扩展

扩展内容主要包括:
一.异步任务队列
这里根据不同的需求,我设计了三种不同的异步处理方法
```
方法一(可用http请求投递):
    将异步任务,以触发浏览器链接的方式执行,适用于能通过web请求来处理的小耗时任务
    优点:操作简单
    缺点:安全性低,任务链接要做权限处理;
        稳定性较差,如果链接中有脚本错误,或连接超时,会导致任务丢失.

方法二(可用http请求投递):
    将异步任务,投递到mongo中,由yii2的console来执行,用来处理耗时,重要的任务
    优点:稳定性好,任务队列记录到mongodb,可查找任务处理记录
    缺点:需要安装mongodb,配置mongo,操作较复杂

方法三(用cli请求投递):
    以cli的形式来投递任务,适用场景较多的为计划任务,来通过yii2的console来执行,同样由mongo来传递和记录任务队列
```

二.websocket通信
本扩展以websocket为基础服务,所以可以处理websocket的请求,多客户端连接通信,通过自定义命令来实时处理业务

三.基于websocket的实时推送
服务端有消息变更时,通过向客户端推送消息,来达到消息的同步和实时反馈

四.简单的启动/关闭/重启/状态获取命令


swoole版本要求：>=1.8.1

实现原理
------------

适用场景
------------
需要客户端触发的耗时请求，客户端无需等待返回结果
websocket的这种场景

安装
------------
```
composer require xiaochengfu/yii2-swoole "dev-master"
```

如何使用

安装前准备:
1.需要安装curl扩展,
composer require linslin/yii2-curl "1.1.3"
2.需要安装mongodb,因为有部分异步任务是需要存储到mongodb中的,你需要建立queue模型,用于AR处理

queue.php内容如下:
```
<?php
namespace common\models;
use yii\mongodb\ActiveRecord;

class Queue extends ActiveRecord
{

    public static function collectionName()
    {
        return 'queue';
    }

    public function rules()
    {
        return [
            [['_id','id','jobs','status'],'required'],
        ];
    }

    public function attributes()
    {
        return [
            '_id',
            'id',
            'jobs',
            'status'
        ];
    }


}
```


-----
1、修改common/config/params.php
```php
return [
    'swooleAsync' => [
        'host'             => 'ip', 		//服务启动IP
        'port'             => '9512',      		//服务启动端口
        'swoole_http'      => 'http://ip:9512',//推送触发连接地址
        'process_name'     => 'swooleWebSocket',		//服务进程名
        'open_tcp_nodelay' => '1',         		//启用open_tcp_nodelay
        'daemonize'        => false,				//守护进程化
        'worker_num'       => '2',				//work进程数目
        'task_worker_num'  => '2',				//task进程的数量
        'task_max_request' => '10000',			//work进程最大处理的请求数
        'client_timeout'   => '20',
        'pidfile'           => Yii::getAlias('@swoole').'/yii2-swoole/yii2-swoole.pid',
        'log_dir'           => Yii::getAlias('@swoole').'/yii2-swoole',
        'task_tmpdir'       => Yii::getAlias('@swoole').'/yii2-swoole',
        'log_file'          => Yii::getAlias('@swoole').'/yii2-swoole/swoole.log',
        'log_size'          => 204800000,       //运行时日志 单个文件大小
    ]
];
```
2.上一步中,我把pidfile和log目录单独定义到了swoolelog目录下,如果你也采用相同的方法,你需要设置swoolelog别名
修改common/bootstrap.php,添加如下内容:
```
Yii::setAlias('@swoole',dirname(dirname(__DIR__)) . '/swoolelog');
```

3、在common/main.php配置文件中增加controllerMap
```php
 'controllerMap' => [
        'swoole' => [
            'class' => 'xiaochengfu\swoole\SwooleController',
        ],
    ],
```

4、在主配置文件中增加components
```php
'components' => [
     'swoole' => [
                 'class' => 'xiaochengfu\swoole\component\SwooleAsyncComponent',
             ]
]
```

5、服务管理
```
//启动
php /path/to/yii/application/yii swoole start
 
//重启
php /path/to/yii/application/yii swoole restart

//停止
php /path/to/yii/application/yii swoole stop

//查看状态
php /path/to/yii/application/yii swoole status

//查看进程列表
php /path/to/yii/application/yii swoole list

```

5、测试
a.通过分别访问front/site/web|mongo|del|来测试异步任务
b.通过访问front/site/push来测试websocket推送,客户端需要自己建立连接,fd为1
c.通过执行
```
php /path/to/yii/application/yii job cli
```
来测试命令行的异步任务

site控制内容如下:
```
<?php
namespace frontend\controllers;

use common\models\User;
use common\models\UserDb;
use linslin\yii2\curl\Curl;
use Yii;
use yii\base\InvalidParamException;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use common\models\LoginForm;
use frontend\models\PasswordResetRequestForm;
use frontend\models\ResetPasswordForm;
use frontend\models\SignupForm;
use frontend\models\ContactForm;

/**
 * Site controller
 */
class SiteController extends Controller
{

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['logout', 'signup','test','mongo','del','push','cli'],
                'rules' => [
                    [
                        'actions' => ['signup','test','mongo','del','push','cli'],
                        'allow' => true,
                        'roles' => ['?'],
                    ],
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    /**
     * Displays homepage.
     *
     * @return mixed
     */
    public function actionWeb()
    {
        $data = [
            "data"=>[
                [
                    "a" => "http://ip/site/test",
                ],
                [
                    "a" => "http://ip/site/test",
                ],
                [
                    "a" => "http://ip/site/test",
                ],

            ],
        ];
        Yii::$app->swoole->webTask($data);
        return $this->renderAjax('index');
    }

    public function actionMongo(){
        $data = [
            "data"=>[
                [
                    "a" => "job/insert",
                ],
                [
                    "a" => "job/insert",
                ],
                [
                    "a" => "job/insert",
                ],

            ],
        ];
        Yii::$app->swoole->mongodbTask($data);
        echo '执行成功';

    }

    public function actionDel(){
        $data = [
            "data"=>[
                [
                    "a" => "job/cs",
                ],

            ],
        ];
        Yii::$app->swoole->mongodbTask($data);
        echo '执行成功';
    }

    public function actionPush(){
        $data = [
            'fd'=>1,
            "data"=>'hello world',
        ];
        Yii::$app->swoole->pushMsg($data);
        echo '执行成功';
    }

    public function actionTest(){
        $user = new User();
        $data = $user->find()->where(['id'=>1])->asArray()->one();
        for($i=0;$i<100;$i++){
            $user->isNewRecord = true;
            $user->id = 0;
            $user->setAttributes($data);
            if($user->save() == false){
                var_dump($user->errors);
            };
        }
        echo '执行成功';
    }

    public function actionTt(){
        $user = new User();
        $user->deleteAll(['>','id',1]);

        echo '执行成功';
    }
}

```
console/job控制器内容如下:

```
<?php
namespace console\controllers;
use common\models\MemberCard;
use common\models\Notifications;
use common\models\Sms;
use common\models\User;
use ijackwu\ssdb\Exception;
use yii\console\Controller;
use common\lib\Cache;

class JobController extends Controller
{
    public function actionInsert(){
        $user = new User();
        $data = $user->find()->where(['id'=>1])->asArray()->one();
        for($i=0;$i<1000;$i++){
            $user->isNewRecord = true;
            $user->id = 0;
            $user->setAttributes($data);
            if($user->save() == false){
                var_dump($user->errors);
            };
        }
        echo '执行成功';
    }

    public function actionCs(){
        $user = new User();
        $user->deleteAll(['>','id',1]);

        echo '执行成功';
    }

    public function actionResult(){
        echo '回调成功';
    }

    public function actionCli(){
        $data = [
            "data"=>[
                [
                    "a" => "job/insert",
                ],
                [
                    "a" => "job/insert",
                ],
                [
                    "a" => "job/insert",
                ],

            ],
        ];
        \Yii::$app->swoole->cliAsync($data);
        echo '执行成功';
    }

}
```
