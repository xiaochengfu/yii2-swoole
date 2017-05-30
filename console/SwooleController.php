<?php
/**
 * Created by PhpStorm.
 * Author: houpeng
 * DateTime: 2017/04/15 11:30
 * Description:
 */

namespace xiaochengfu\swoole\console;

use yii\base\ErrorException;
use xiaochengfu\swoole\src\SwooleService;
use yii\helpers\ArrayHelper;
use Yii;

use yii\console\Controller;



class SwooleController extends Controller
{

    /**
     * 存储swooleAsync配置中的所有配置项
     * @var array
     */
    private $settings = [];
    /**
     * 默认controller
     * @var string
     */
    public $defaultAction = 'run';

    /**
     * 初始化
     * @return [type] [description]
     */
    public function init() {

        parent::init();
        $this->prepareSettings();

    }

    /**
     * 初始化配置信息
     * @return [type] [description]
     */
    protected function prepareSettings()
    {
//        $runtimePath = Yii::$app->getRuntimePath();
        $this->settings = [
//            'host'              => '127.0.0.1',
//            'port'              => '9512',
        ];
        try {
            $settings = Yii::$app->params['swooleAsync'];
        }catch (ErrorException $e) {
            throw new ErrorException('Empty param swooleAsync in params. ',8);
        }

        $this->settings = ArrayHelper::merge(
            $this->settings,
            $settings
        );
    }

    /**
     * 启动服务action
     * @param  array  $args [description]
     * @return [type]       [description]
     */
    public function actionRun($mode='start'){
        $swooleService = new SwooleService($this->settings,Yii::$app);
        switch ($mode) {
            case 'start':
                $swooleService->serviceStart();
                break;
            case 'restart':
                $swooleService->serviceStop();
                $swooleService->serviceStart();
                break;
            case 'stop':
                $swooleService->serviceStop();
                break;
            case 'status':
                $swooleService->serviceStats();
                break;
            case 'list':
                $swooleService->serviceList();
                break;
            default:
                exit('error:参数错误');
                break;
        }
    }
   
}