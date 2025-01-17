<?php
/**
 * @Author: qinuoyun
 * @Date:   2020-08-20 13:46:09
 * @Last Modified by:   qinuoyun
 * @Last Modified time: 2021-01-05 10:17:04
 */
namespace framework\common;

use app\core\response\ApiCode;
use app\datamodel\BaseActiveQuery;
use Yii;

class CommonModels extends \yii\db\ActiveRecord
{

    public static $automaticTable = true;

    public static function getDb()
    {
        //判断是否要创建表结构
        if (self::$automaticTable) {
            //实时监测创建表结构
            self::creatingDatabase();
        }
        return Yii::$app->getDb();
    }

    /**
     * 自动创建表 array_flip
     * @return [type] [description]
     */
    public static function creatingDatabase()
    {
        $self     = new self;
        $class    = get_called_class();
        $objClass = new \ReflectionClass(new $class());
        $arrConst = $objClass->getConstants();
        $fields   = array_filter($arrConst, function ($value) {
            if (is_array($value)) {
                return true;
            }
            return false;
        });
        $table       = static::tableName();
        $tableSchema = Yii::$app->db->schema->getTableSchema($table);
        //判断如果表结构是否存在
        if ($tableSchema) {
            //字段判断对比
            $screen = array_diff_key($fields, $tableSchema->columns);
            if ($screen) {
                //新增字段
                (new DatabaseMigration([
                    "tableName"   => $table,
                    "tableFidlds" => $screen,
                ]))->addFidld();
            }
        }
        //表结构不存在自动创建表
        else {
            $fields['is_deleted'] = [
                "tinyint"     => 100,
                "default"     => 0,
                "description" => "是否删除",
            ];
            $fields['created_time'] = [
                "int"         => 10,
                "default"     => 0,
                "description" => "创建时间",
            ];
            $fields['updated_time'] = [
                "int"         => 10,
                "default"     => 0,
                "description" => "更新时间",
            ];
            $fields['deleted_time'] = [
                "int"         => 10,
                "default"     => 0,
                "description" => "删除时间",
            ];
            (new DatabaseMigration([
                "tableName"   => $table,
                "tableFidlds" => $fields,
            ]))->up();
        }
    }

    /**
     * 在保存和修改时写入事件
     * @param  [type] $insert [description]
     * @return [type]         [description]
     */
    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            if ($insert) {
                $this->created_time = $this->updated_time = time();
            } else {
                $this->updated_time = time();
            }
            return true;
        } else {
            return false;
        }
    }

    public function afterSave($insert, $changeAttributes)
    {
        $afterSave = parent::afterSave($insert, $changeAttributes);
        return $afterSave;
    }

    /**
     * ActiveRecord 数据验证，返回第一条错误验证
     * @param array $model
     * @return array
     */
    public function getErrorResponse($model = [])
    {
        if (!$model) {
            $model = $this;
        }

        $msg = isset($model->errors) ? current($model->errors)[0] : '数据异常！';

        return [
            'code' => 1,
            'msg' => $msg
        ];
    }

    /**
     * ActiveRecord 数据验证，返回第一条错误验证
     * @param array $model
     * @return string
     */
    public function getErrorMsg($model = null)
    {
        if (!$model) {
            $model = $this;
        }
        $msg = isset($model->errors) ? current($model->errors)[0] : '数据异常！';
        return $msg;
    }
}
