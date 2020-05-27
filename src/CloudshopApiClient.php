<?php
/**
 * @link https://cms.skeeks.com/
 * @copyright Copyright (c) 2010 SkeekS
 * @license https://cms.skeeks.com/license/
 * @author Semenov Alexander <semenov@skeeks.com>
 */
namespace skeeks\cms\cloudshop;

use skeeks\cms\cloudshop\assets\CloudshopAsset;
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 */
class CloudshopApiClient extends \skeeks\yii2\cloudshopApiClient\CloudshopApiClient {

    /**
     * @throws \yii\base\InvalidConfigException
     */
    public function init()
    {
        $this->cache_key = 'sx-cloudshop-key-' . \Yii::$app->skeeks->site->id;

        if (isset(\Yii::$app->cloudshop)) {
            foreach (\Yii::$app->cloudshop->toArray() as $key => $value) {
                if ($this->hasProperty($key) && $this->canSetProperty($key)) {
                    $this->{$key} = $value;
                }
            }
        }

        parent::init();
    }
}