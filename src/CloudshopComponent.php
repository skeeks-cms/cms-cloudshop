<?php
/**
 * @link https://cms.skeeks.com/
 * @copyright Copyright (c) 2010 SkeekS
 * @license https://cms.skeeks.com/license/
 * @author Semenov Alexander <semenov@skeeks.com>
 */

namespace skeeks\cms\cloudshop;

use skeeks\cms\backend\widgets\ActiveFormBackend;
use skeeks\cms\base\Component;
use skeeks\cms\cloudshop\assets\CloudshopAsset;
use skeeks\cms\shop\models\ShopStore;
use skeeks\cms\shop\models\ShopSupplier;
use skeeks\yii2\form\fields\FieldSet;
use skeeks\yii2\form\fields\SelectField;
use yii\base\Exception;
use yii\helpers\ArrayHelper;
/**
 * @property ShopSupplier $shopSupplier
 *
 * @author Semenov Alexander <semenov@skeeks.com>
 */
class CloudshopComponent extends Component
{
    public $email;
    public $password;

    static public function descriptorConfig()
    {
        return array_merge(parent::descriptorConfig(), [
            'name'  => "Cloud Shop",
            'image' => [
                CloudshopAsset::class,
                'icons/cloudshop.png',
            ],
        ]);
    }

    /**
     * @return ActiveForm
     */
    public function beginConfigForm()
    {
        return ActiveFormBackend::begin();
    }

    public function rules()
    {
        return [
            [
                [
                    'email',
                    'password',
                ],
                'string',
            ],
        ];
    }

    public function getConfigFormFields()
    {
        return [
            'catalog' => [
                'class' => FieldSet::class,
                'name'  => \Yii::t('skeeks/shop/app', 'Авторизация в Cloud Shop'),

                'fields' => [
                    'email',
                    'password',
                ],
            ],
            
        ];
    }

    public function attributeLabels()
    {
        return [
            'password'         => 'Пароль',
        ];
    }

    public function attributeHints()
    {
        return [
        ];
    }

    /**
     * Создает необходимые склады в CMS
     *
     * @return $this
     * @throws Exception
     */
    public function createStories()
    {
        if (!$this->shopSupplier) {
            throw new Exception("Для начала задайте поставщика");
        }

        try {
            $data = \Yii::$app->cloudshopApiClient->getStoresApiMethod();
            $data = ArrayHelper::getValue($data, 'data');

            if (is_array($data)) {
                foreach ($data as $storeData) {
                    $cloudShopStoreId = ArrayHelper::getValue($storeData, '_id');
                    $cloudShopStoreName = ArrayHelper::getValue($storeData, 'name');
                    if (!$this->shopSupplier->getShopStores()->andWhere(['external_id' => $cloudShopStoreId])->exists()) {
                        $shopStore = new ShopStore();
                        $shopStore->name = $cloudShopStoreName;
                        $shopStore->external_id = $cloudShopStoreId;
                        $shopStore->cms_site_id = \Yii::$app->skeeks->site->id;
                        if (!$shopStore->save()) {
                            throw new Exception("Не создан склад: ".print_r($shopStore->errors, true));
                        }
                    }

                }
            }
        } catch (\Exception $e) {
            throw $e;
        }

        return $this;
    }

}