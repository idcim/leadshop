<?php
/**
 * 商品管理
 * @link http://www.heshop.com/
 * @copyright Copyright (c) 2020 HeShop Software LLC
 * @license http://www.heshop.com/license/
 */
namespace leadmall\app;

use basics\app\BasicsController as BasicsModules;
use gallery\models\Upload;
use leadmall\Map;
use Yii;

class UploadController extends BasicsModules implements Map
{
    /**
     * 上传
     * @return [type] [description]
     */
    public function actionCreate()
    {
        $upload = new Upload();
        $type   = Yii::$app->request->post('type', 1);

        if ($type == 1) {
            $content = Yii::$app->request->post('content', false);

            if (empty($content)) {
                Error('图片不能为空');
            }

            $file      = $upload->image_base64($content);
            $url       = $file['url'];
            $thumbnail = $upload->image_compress($url);

            if ($url !== $thumbnail) {
                $path = Yii::$app->basePath;
                unlink($path . '/web' . $url);
                $url = $thumbnail;
            }

        } elseif ($type == 2) {

            $content = $_FILES['content'];

            if (empty($content)) {
                Error('视频不能为空');
            }

            $file = $upload->video($content);
            $url  = $file['url'];

        } else {
            Error('未定义操作');
        }

        $UID         = Yii::$app->user->identity->id ?? null;
        $AppID       = Yii::$app->params['AppID'];
        $merchant_id = 1;

        $this->module->event->user_upload = ['url' => $file['url'], 'size' => $file['size'], 'AppID' => $AppID, 'merchant_id' => $merchant_id, 'UID' => $UID];
        $this->module->trigger('user_upload');

        $host = Yii::$app->request->hostInfo;
        return $host . $url;

    }
}
