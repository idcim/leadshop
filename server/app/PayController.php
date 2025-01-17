<?php
/**
 * 商品管理
 * @link http://www.heshop.com/
 * @copyright Copyright (c) 2020 HeShop Software LLC
 * @license http://www.heshop.com/license/
 */
namespace leadmall\app;

use app\components\PaymentOrder;
use basics\app\BasicsController as BasicsModules;
use leadmall\Map;
use Yii;

class PayController extends BasicsModules implements Map
{
    /**
     * 调起支付
     * @return [type] [description]
     */
    public function actionIndex()
    {

        $order_sn = Yii::$app->request->get('order_sn', false);
        $host     = Yii::$app->request->hostInfo;
        $model    = M('order', 'Order')::find()->where(['order_sn' => $order_sn])->one();
        if (empty($model)) {
            Error('订单不存在');
        }
        $setting_data = M('setting', 'Setting')::find()->where(['keyword' => 'setting_collection', 'merchant_id' => $model->merchant_id, 'AppID' => $model->AppID])->select('content')->asArray()->one();
        if ($setting_data) {
            $setting_data['content'] = to_array($setting_data['content']);
            if ($setting_data['content']['basic_setting']['run_status'] != 1) {
                Error('店铺打烊中');
            }
        }
        if ($model->status !== 100) {
            Error('该订单不可支付');
        }

        if ($model->pay_amount <= 0) {
            return $this->paid($model->order_sn);
        }

        $goods_name_list = M('order','OrderGoods')::find()->where(['order_sn'=>$order_sn])->select('goods_name')->asArray()->all();

        $goods_name_str = '';
        foreach ($goods_name_list as $v) {
            $goods_name_str .= $v['goods_name'].',';
        }

        if (mb_strlen($goods_name_str)>30) {
            $goods_name = mb_substr($goods_name_str, 0,30);
            $goods_name = rtrim($goods_name,',');
            $goods_name .= '...';
        } else {
            $goods_name = $goods_name_str;
        }

        $res = Yii::$app->payment->unifiedOrder(new PaymentOrder([
            'openid'  => Yii::$app->user->identity->oauth->oauthID,
            'orderNo' => time() . $model->order_sn, //拼接时间戳,防止后期调用订单编号重复
            'amount'  => (float) $model->pay_amount,
            'title'   => $goods_name,
            'notify'  => $host . '/app/leadmall/pay/' . $model->AppID . '/' . \Yii::$app->params['AppType'],
        ]));
        return $res;
    }

    public function actionCreate()
    {
        $AppID                     = Yii::$app->request->get('appid', false);
        $AppType                   = Yii::$app->request->get('apptype', false);
        Yii::$app->params['AppID'] = $AppID;
        $file                      = __DIR__ . "/../stores/{$AppID}.json";
        if (!file_exists($file)) {
            Error('店铺不存在');
        }
        Yii::$app->params            = json_decode(file_get_contents($file), true);
        Yii::$app->params['AppType'] = $AppType;
        Yii::$app->payment->notify($this, $AppID);
    }

    public function paid($value)
    {
        if (isset($value['out_trade_no'])) {
            $pay_number = $value['out_trade_no'];
            $order_sn   = substr($pay_number, 10);
            $pay_type   = 'wechat';
        } else {
            $pay_number = '';
            $pay_type   = '';
            $order_sn   = $value;
        }

        $model = M('order', 'Order')::find()->where(['order_sn' => $order_sn])->one();

        if ($model && $model->status < 201) {
            $model->status     = 201;
            $model->pay_number = $pay_number;
            $model->pay_type   = 'wechat';
            $model->pay_time   = time();
            if ($model->save()) {
                $this->module->event->order_info = $model->toArray();
                $this->module->trigger('pay_order');
                // $this->module->trigger('statistical_order');
                $this->module->event->user_statistical = ['UID' => $model->UID, 'buy_number' => 1, 'buy_amount' => $model->pay_amount, 'last_buy_time' => time()];
                $this->module->trigger('user_statistical');
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
}
