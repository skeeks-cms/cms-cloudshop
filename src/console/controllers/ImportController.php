<?php
/**
 * @link https://cms.skeeks.com/
 * @copyright Copyright (c) 2010 SkeekS
 * @license https://cms.skeeks.com/license/
 * @author Semenov Alexander <semenov@skeeks.com>
 */

namespace skeeks\cms\cloudshop\console\controllers;

use skeeks\cms\shop\models\ShopCmsContentElement;
use skeeks\cms\shop\models\ShopProduct;
use skeeks\cms\shop\models\ShopProductPrice;
use skeeks\cms\shop\models\ShopStore;
use skeeks\cms\shop\models\ShopStoreProduct;
use skeeks\cms\shop\models\ShopTypePrice;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\console\Controller;
use yii\helpers\ArrayHelper;
use yii\helpers\Console;

/**
 * @author Semenov Alexander <semenov@skeeks.com>
 */
class ImportController extends Controller
{
    public function init()
    {
        if (!\Yii::$app->skeeks->site) {
            throw new InvalidConfigException("Не указан сайт");
        }

        parent::init();
    }
    /**
     * Импорт складов из clodushop в cms
     * @return bool
     * @throws \Exception
     */
    public function actionImportStories()
    {
        try {
            $data = \Yii::$app->cloudshopApiClient->getStoresApiMethod();
            $data = ArrayHelper::getValue($data, 'data');

            if (is_array($data)) {
                $this->stdout("Найдено складов в cloudshop: ".count($data)."\n");

                foreach ($data as $storeData) {
                    $cloudShopStoreId = ArrayHelper::getValue($storeData, '_id');
                    $cloudShopStoreName = ArrayHelper::getValue($storeData, 'name');
                    $q = ShopStore::find()->where(['cms_site_id' => \Yii::$app->skeeks->site->id]);
                    if (!$q->andWhere(['external_id' => $cloudShopStoreId])->exists()) {
                        $shopStore = new ShopStore();
                        $shopStore->name = $cloudShopStoreName;
                        $shopStore->external_id = $cloudShopStoreId;
                        $shopStore->cms_site_id = \Yii::$app->skeeks->site->id;
                        if (!$shopStore->save()) {
                            throw new Exception("Не создан склад: ".print_r($shopStore->errors, true));
                        }

                        $this->stdout("Склад создан: ".$shopStore->name."\n", Console::FG_GREEN);
                    }

                }
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function actionImportPrices()
    {
        /*if (!\Yii::$app->cloudshop->shopSupplier) {
            $this->stdout("Для начала задайте поставщика в настройках компонента\n", Console::FG_RED);
            return false;
        }*/

        $q = ShopTypePrice::find()->where(['cms_site_id' => \Yii::$app->skeeks->site->id]);
        if (!$q->andWhere(['external_id' => 'purchase'])->exists()) {
            $shopTypePrice = new ShopTypePrice();
            $shopTypePrice->name = "Закупочная";
            $shopTypePrice->external_id = "purchase";
            $shopTypePrice->cms_site_id = \Yii::$app->skeeks->site->id;

            if (!$shopTypePrice->save()) {
                throw new Exception("Цена purchase не создана!".print_r($shopTypePrice->errors));
            }
        }

        $q = ShopTypePrice::find()->where(['cms_site_id' => \Yii::$app->skeeks->site->id]);
        if (!$q->andWhere(['external_id' => 'price'])->exists()) {
            $shopTypePrice = new ShopTypePrice();
            $shopTypePrice->name = "Цена продажи";
            $shopTypePrice->external_id = "price";
            $shopTypePrice->cms_site_id = \Yii::$app->skeeks->site->id;

            if (!$shopTypePrice->save()) {
                throw new Exception("Цена price не создана!".print_r($shopTypePrice->errors));
            }
        }

        /*$q = ShopTypePrice::find()->where(['cms_site_id' => \Yii::$app->skeeks->site->id]);
        if (!$q->andWhere(['external_id' => 'cost'])->exists()) {
            $shopTypePrice = new ShopTypePrice();
            $shopTypePrice->name = "Себестоимость";
            $shopTypePrice->external_id = "cost";
            $shopTypePrice->cms_site_id = \Yii::$app->skeeks->site->id;

            if (!$shopTypePrice->save()) {
                throw new Exception("Цена cost не создана!" . print_r($shopTypePrice->errors));
            }
        }*/
    }


    protected $_content_id = '';

    /**
     * @return bool
     * @throws \Exception
     */
    public function actionImportProducts()
    {
        $qStore = ShopStore::find()
            ->cmsSite();

        //Склады
        $data = \Yii::$app->cloudshopApiClient->getStoresApiMethod();
        $data = ArrayHelper::getValue($data, 'data');
        $storeExternalIds = [];
        if (is_array($data)) {
            $storeExternalIds = ArrayHelper::map($data, "_id", "_id");
        }

        $qStore->andWhere(['external_id' => $storeExternalIds]);

        if (!$qStore->exists()) {
            $this->stdout("Для начала импортируйте склады\n", Console::FG_RED);
            return false;
        }

        $qPrice = ShopTypePrice::find()->where(['cms_site_id' => \Yii::$app->skeeks->site->id]);
        if ($qPrice->andWhere([
                'in',
                'external_id',
                [
                    'purchase',
                    //'cost',
                    'price',
                ],
            ])->count() != 2) {
            $this->stdout("Для начала импортируйте цены\n", Console::FG_RED);
            return false;
        }

        if (!\Yii::$app->shop->contentProducts) {
            $this->stdout("Магазин не настроен, нет продаваемого контента\n", Console::FG_RED);

            return false;
        }

        //Обнулить количество по всем товарам
        /*if ($updated = ShopStoreProduct::updateAll(['quantity' => 0], ['in', 'shop_store_id', $qStore->select([ShopStore::tableName() . '.id'])])) {
            $this->stdout("Обнулено: " . $updated . "\n", Console::FG_YELLOW);
            sleep(5);
        }*/


        $this->_content_id = \Yii::$app->shop->contentProducts->id;

        try {
            $data = \Yii::$app->cloudshopApiClient->getCatalogApiMethod([
                'types' => [
                    'inventory',
                    //'group'
                ],
            ]);

            $data = ArrayHelper::getValue($data, 'data');

            if (is_array($data)) {
                $this->stdout("Найдено товаров в cloudshop: ".count($data)."\n");

                foreach ($data as $productData) {
                    $this->_addProduct($productData);
                }
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }

    protected function _addProduct($data)
    {
        $cloudShopProductId = ArrayHelper::getValue($data, '_id');
        $cloudShopProductName = ArrayHelper::getValue($data, 'name');
        $cloudShopStoreUnit = ArrayHelper::getValue($data, 'unit');
        $cloudShopBarcode = explode(",", ArrayHelper::getValue($data, 'barcode'));
        $stock = ArrayHelper::getValue($data, 'stock');

        $this->stdout("----------\n");
        $this->stdout("Product: $cloudShopProductId\n");

        /**
         * @var $shopElement ShopCmsContentElement
         */
        $shopElement = ShopCmsContentElement::find()
            //->joinWith('shopProduct as shopProduct')
            ->andWhere([
                'cms_site_id' => \Yii::$app->skeeks->site->id,
                'external_id' => $cloudShopProductId,
            ])->one();

        $t = \Yii::$app->db->beginTransaction();

        try {


            if ($shopElement) {
                $this->stdout("Exist {$shopElement->id}\n", Console::FG_YELLOW);
                $shopProduct = $shopElement->shopProduct;
                $shopElement->content_id = $this->_content_id;
                $shopElement->save();

            } else {


                $this->stdout("Проверка товара по штрихкоду ...\n");

                $isCreateNew = true;
                //Если указан штрихкод
                if ($cloudShopBarcode) {

                    //Ищем уже созданный товар с таким штрихкодом
                    $queryFindByBarcode = ShopCmsContentElement::find()
                        ->joinWith("shopProduct as shopProduct")
                        ->joinWith("shopProduct.shopProductBarcodes as shopProductBarcodes")
                        ->andWhere([
                            'shopProductBarcodes.value' => $cloudShopBarcode,
                        ])
                        ->andWhere([
                            "or",
                            [ShopCmsContentElement::tableName().'.external_id' => null],
                            [ShopCmsContentElement::tableName().'.external_id' => ""],
                        ]);

                    //Елси такой товар найден один то нужно с ним связать клаудшоп
                    if ($queryFindByBarcode->count() == 1) {
                        $isCreateNew = false;
                        $shopElement = $queryFindByBarcode->one();
                        $shopElement->external_id = (string)$cloudShopProductId;
                        if (!$shopElement->save()) {
                            throw new Exception("Не создан элемент: ".print_r($shopElement->errors, true));
                        }

                        $shopProduct = $shopElement->shopProduct;

                        $this->stdout("\tТовар обновлен {$shopElement->id}\n", Console::FG_GREEN);
                    }
                }


                if ($isCreateNew) {
                    $this->stdout("Creating...\n");

                    $shopElement = new ShopCmsContentElement();
                    $shopElement->content_id = $this->_content_id;
                    $shopElement->name = $cloudShopProductName;
                    $shopElement->external_id = (string)$cloudShopProductId;
                    $shopElement->active = "N";
                    if (!$shopElement->save()) {
                        throw new Exception("Не создан элемент: ".print_r($shopElement->errors, true));
                    }

                    $shopProduct = new ShopProduct();
                    $shopProduct->id = $shopElement->id;

                    if (!$shopProduct->save()) {
                        throw new Exception("Не создан товар: ".print_r($shopProduct->errors, true));
                    }

                    $this->stdout("\tТовар создан {$shopProduct->id}\n", Console::FG_GREEN);
                }

            }

            $shopProduct->quantity = 0;
            $shopProduct->supplier_external_jsondata = $data;
            $shopProduct->barcodes = $cloudShopBarcode;

            if (!$shopProduct->save()) {
                throw new Exception("Данные по товару не обновлены: ".print_r($shopProduct->errors, true), Console::FG_GREEN);
            }


            //Оновление наличия
            if ($stock) {
                $this->stdout("\tОбновление наличия: ".print_r($stock, true)."\n\r");
                $this->_updateStock($stock, $shopProduct);
            } else {
                $this->stdout("\tТовар без наличия\n");
            }


            $this->stdout("\tОбновление цен\n");
            $this->_updatePrices([
                'purchase' => ArrayHelper::getValue($data, 'purchase'),
                'price'    => ArrayHelper::getValue($data, 'price'),
                'cost'     => ArrayHelper::getValue($data, 'cost'),
            ], $shopProduct);

            $t->commit();

            $this->stdout("\tУспех\n", Console::FG_GREEN);

        } catch (\Exception $e) {
            $t->rollBack();
            $this->stdout("Ошибка: {$e->getMessage()}\n", Console::FG_RED);
            throw $e;
        }

    }

    protected function _updateStock($restData, ShopProduct $shopProduct)
    {
        foreach ($restData as $id => $count) {

            $qStore = ShopStore::find()->where(['cms_site_id' => \Yii::$app->skeeks->site->id]);

            if (!$shopStore = $qStore->andWhere(['external_id' => $id])->one()) {
                continue;
                //throw new Exception("Склада нет!");
            }

            $shopStoreProduct = $shopProduct->getShopStoreProducts()->joinWith('shopStore as shopStore')->andWhere(['shopStore.id' => $shopStore->id])->one();
            if (!$shopStoreProduct) {
                $shopStoreProduct = new ShopStoreProduct();
                $shopStoreProduct->shop_store_id = $shopStore->id;
                $shopStoreProduct->shop_product_id = $shopProduct->id;
            }

            $shopStoreProduct->quantity = $count;

            if (!$shopStoreProduct->save()) {
                throw new Exception("Не создан наличие на складе: ".print_r($shopStoreProduct->errors, true));
            }
        }

        return true;
    }


    protected function _updatePrices($data, ShopProduct $shopProduct)
    {
        foreach ($data as $priceCode => $value) {
            $qPrice = ShopTypePrice::find()->where(['cms_site_id' => \Yii::$app->skeeks->site->id]);

            if (!$typePrice = $qPrice->andWhere(['external_id' => $priceCode])->one()) {
                continue;
                /*
                $typePrice = new ShopTypePrice();
                $typePrice->cms_site_id = \Yii::$app->skeeks->site->id;
                $typePrice->external_id = $priceCode;
                $typePrice->name = $priceCode;

                if (!$typePrice->save()) {
                    throw new Exception("Не сохранен тип цены: ".print_r($typePrice->errors, true));
                }

                $this->stdout("\tСоздана цена: {$priceCode}\n", Console::FG_GREEN);*/
            }

            $value = (float)$value;

            $price = $shopProduct->getShopProductPrices()->joinWith('typePrice as typePrice')->andWhere(['typePrice.id' => $typePrice->id])->one();
            if (!$price) {
                $price = new ShopProductPrice();
                $price->type_price_id = $typePrice->id;
                $price->product_id = $shopProduct->id;
            }

            $price->is_fixed = 1;
            $price->price = $value;
            $price->currency_code = "RUB";

            if (!$price->save()) {
                throw new Exception("Цена {$priceCode} не сохранена: ".print_r($price->errors, true));
            }
        }

        return true;
    }
}