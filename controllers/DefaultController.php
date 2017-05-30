<?php
/**
 * Created by PhpStorm.
 * Author: houpeng
 * DateTime: 2017/04/15 11:30
 * Description:
 */
namespace xiaochengfu\swoole\controllers;

use xiaochengfu\swoole\models\Test;
use yii\web\Controller;
use Yii;


class DefaultController extends Controller
{
    /**
     * Displays homepage.
     *
     * @return mixed
     */
    public function actionIndex()
    {
        $data = [
            "data"=>[
                [
                    "a" => "http://yii.phpsy.cn/swoole/default/test",
                ],
                [
                    "a" => "http://yii.phpsy.cn/swoole/default/test",
                ],
                [
                    "a" => "http://yii.phpsy.cn/swoole/default/test",
                ],

            ],
        ];
        Yii::$app->swoole->webTask($data);
        echo '执行成功';
    }

    public function actionMongo(){
        $data = [
            "data"=>[
                [
                    "a" => "test/insert",
                ],
                [
                    "a" => "test/insert",
                ],
                [
                    "a" => "test/insert",
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
                    "a" => "test/del",
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
        $test = new Test();
        $testInfo = $test::findOne(['a'=>'hello']);
        $collection = Yii::$app->mongodb->getCollection ('test');
        if(empty($testInfo)){
            $collection->insert ( [
                'a'=>'hello',
                'b'=>'wrold',
                'status'=>$test::NORMAL,
                'addtime'=>date('Y-m-d h:i:s',time())
            ] );
        }else{
            for($i=0;$i<1000;$i++){
                $collection->insert ( [
                    'a'=>'hi',
                    'b'=>'wrold',
                    'status'=>$test::NORMAL,
                    'addtime'=>date('Y-m-d h:i:s',time())
                ] );
            }
        }

        echo '执行成功';
    }
    
}
