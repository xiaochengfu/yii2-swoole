<?php
/**
 * Created by PhpStorm.
 * Author: houpeng
 * DateTime: 2017/04/15 11:30
 * Description:
 */
namespace xiaochengfu\swoole\models;

use yii\mongodb\ActiveRecord;

class Test extends ActiveRecord
{

    const NORMAL = 1;
    public static function collectionName()
    {
        return 'test';
    }

    public function rules()
    {
        return [
            [['_id','a','b','status'],'required'],
        ];
    }

    public function attributes()
    {
        return [
            '_id',
            'a',
            'b',
            'status',
            'addtime',
        ];
    }

}
