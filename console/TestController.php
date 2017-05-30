<?php
/**
 * Created by PhpStorm.
 * Author: houpeng
 * DateTime: 2017/04/15 11:30
 * Description:
 */

namespace xiaochengfu\swoole\console;

use xiaochengfu\swoole\models\Test;
use Yii;

use yii\console\Controller;



class TestController extends Controller
{

    public function actionInsert(){
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

    public function actionDel(){
        $user = new Test();
        $user->deleteAll(['<>','a','hello']);

        echo '执行成功';
    }


    public function actionCli(){
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
        \Yii::$app->swoole->cliAsync($data);
        echo '执行成功';
    }
   
}