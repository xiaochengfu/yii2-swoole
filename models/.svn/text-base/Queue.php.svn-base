<?php
/**
 * Created by PhpStorm.
 * Author: houpeng
 * DateTime: 2017/04/15 11:30
 * Description:
 */
namespace xiaochengfu\swoole\models;

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
