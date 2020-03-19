<?php
/**
 * @link https://cms.skeeks.com/
 * @copyright Copyright (c) 2010 SkeekS
 * @license https://cms.skeeks.com/license/
 * @author Semenov Alexander <semenov@skeeks.com>
 */

namespace skeeks\cms\cloudshop\console\controllers;

use skeeks\cms\shop\models\ShopCmsContentElement;
use skeeks\cms\shop\models\ShopContent;
use skeeks\cms\shop\models\ShopProduct;
use skeeks\cms\shop\models\ShopProductPrice;
use skeeks\cms\shop\models\ShopStore;
use skeeks\cms\shop\models\ShopStoreProduct;
use skeeks\cms\shop\models\ShopTypePrice;
use yii\base\Exception;
use yii\console\Controller;
use yii\helpers\ArrayHelper;
use yii\helpers\Console;

/**
 * @author Semenov Alexander <semenov@skeeks.com>
 */
class ImportController extends Controller
{
    /**
     * Импорт складов из clodushop в cms
     * @return bool
     * @throws \Exception
     */
    public function actionImportStories()
    {
        if (!\Yii::$app->cloudshop->shopSupplier) {
            $this->stdout("Для начала задайте поставщика в настройках компонента\n", Console::FG_RED);
            return false;
        }

        try {
            $data = \Yii::$app->cloudshopApiClient->getStoresApiMethod();
            $data = ArrayHelper::getValue($data, 'data');

            if (is_array($data)) {
                $this->stdout("Найдено складов в cloudshop: " . count($data). "\n");

                foreach ($data as $storeData) {
                    $cloudShopStoreId = ArrayHelper::getValue($storeData, '_id');
                    $cloudShopStoreName = ArrayHelper::getValue($storeData, 'name');
                    if (!\Yii::$app->cloudshop->shopSupplier->getShopStores()->andWhere(['external_id' => $cloudShopStoreId])->exists()) {
                        $shopStore = new ShopStore();
                        $shopStore->name = $cloudShopStoreName;
                        $shopStore->external_id = $cloudShopStoreId;
                        $shopStore->shop_supplier_id = \Yii::$app->cloudshop->shopSupplier->id;
                        if (!$shopStore->save()) {
                            throw new Exception("Не создан склад: ".print_r($shopStore->errors, true));
                        }

                        $this->stdout("Склад создан: " . $shopStore->name . "\n", Console::FG_GREEN);
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
        if (!\Yii::$app->cloudshop->shopSupplier) {
            $this->stdout("Для начала задайте поставщика в настройках компонента\n", Console::FG_RED);
            return false;
        }

        if (!\Yii::$app->cloudshop->shopSupplier->getShopTypePrices()->andWhere(['external_id' => 'purchase'])->exists()) {
            $shopTypePrice = new ShopTypePrice();
            $shopTypePrice->name = "Закупочная";
            $shopTypePrice->external_id = "purchase";
            $shopTypePrice->shop_supplier_id = \Yii::$app->cloudshop->shopSupplier->id;

            if (!$shopTypePrice->save()) {
                throw new Exception("Цена purchase не создана!" . print_r($shopTypePrice->errors));
            }
        }

        if (!\Yii::$app->cloudshop->shopSupplier->getShopTypePrices()->andWhere(['external_id' => 'price'])->exists()) {
            $shopTypePrice = new ShopTypePrice();
            $shopTypePrice->name = "Цена продажи";
            $shopTypePrice->external_id = "price";
            $shopTypePrice->shop_supplier_id = \Yii::$app->cloudshop->shopSupplier->id;

            if (!$shopTypePrice->save()) {
                throw new Exception("Цена price не создана!" . print_r($shopTypePrice->errors));
            }
        }

        if (!\Yii::$app->cloudshop->shopSupplier->getShopTypePrices()->andWhere(['external_id' => 'cost'])->exists()) {
            $shopTypePrice = new ShopTypePrice();
            $shopTypePrice->name = "Себестоимость";
            $shopTypePrice->external_id = "cost";
            $shopTypePrice->shop_supplier_id = \Yii::$app->cloudshop->shopSupplier->id;

            if (!$shopTypePrice->save()) {
                throw new Exception("Цена cost не создана!" . print_r($shopTypePrice->errors));
            }
        }
    }


    protected $_content_id = '';
    /**
     * @return bool
     * @throws \Exception
     */
    public function actionImportProducts()
    {
        if (!\Yii::$app->cloudshop->shopSupplier) {
            $this->stdout("Для начала задайте поставщика в настройках компонента\n", Console::FG_RED);
            return false;
        }

        if (!\Yii::$app->cloudshop->shopSupplier->shopStores) {
            $this->stdout("Для начала импортируйте склады\n", Console::FG_RED);
            return false;
        }

        if (\Yii::$app->cloudshop->shopSupplier->getShopTypePrices()->andWhere([
            'in', 'external_id', ['purchase', 'cost', 'price']
        ])->count() != 3) {
            $this->stdout("Для начала импортируйте цены\n", Console::FG_RED);
            return false;
        }

        $shopContent = ShopContent::find()->one();
        if (!$shopContent && !$shopContent->content) {
            $this->stdout("Магазин не настроен, нет продаваемого контента\n", Console::FG_RED);

            return false;
        }

        //Обнулить количество по всем товарам
        if ($updated = ShopStoreProduct::updateAll(['quantity' => 0], ['in', 'shop_store_id', \Yii::$app->cloudshop->shopSupplier->getShopStores()->select([ShopStore::tableName() . '.id'])])) {
            $this->stdout("Обнулено: " . $updated . "\n", Console::FG_YELLOW);
        }


        $this->_content_id = $shopContent->content->id;

        try {
            $data = \Yii::$app->cloudshopApiClient->getCatalogApiMethod([
                'types' => [
                    'inventory',
                    //'group'
                ]
            ]);

            $data = ArrayHelper::getValue($data, 'data');

            if (is_array($data)) {
                $this->stdout("Найдено товаров в cloudshop: " . count($data). "\n");

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
        $stock = ArrayHelper::getValue($data, 'stock');

        $this->stdout("Product: $cloudShopProductId\n");

        /**
         * @var $shopElement ShopCmsContentElement
         */
        $shopElement = ShopCmsContentElement::find()->joinWith('shopProduct as shopProduct')->andWhere([
            'shopProduct.shop_supplier_id'     => \Yii::$app->cloudshop->shopSupplier->id,
            'shopProduct.supplier_external_id' => $cloudShopProductId,
        ])->one();

        $t = \Yii::$app->db->beginTransaction();

        try {


            if ($shopElement) {
                $this->stdout("Exist\n", Console::FG_YELLOW);
                $shopProduct = $shopElement->shopProduct;
                $shopElement->content_id = $this->_content_id;
                $shopElement->save();

            } else {

                $this->stdout("Creating...\n");

                $shopElement = new ShopCmsContentElement();
                $shopElement->content_id = $this->_content_id;
                $shopElement->name = $cloudShopProductName;
                if (!$shopElement->save()) {
                    throw new Exception("Не создан элемент: ".print_r($shopElement->errors, true));
                }

                $shopProduct = new ShopProduct();
                $shopProduct->id = $shopElement->id;
                $shopProduct->shop_supplier_id = \Yii::$app->cloudshop->shopSupplier->id;
                $shopProduct->supplier_external_id = (string)$cloudShopProductId;

                if (!$shopProduct->save()) {
                    throw new Exception("Не создан товар: ".print_r($shopProduct->errors, true));
                }

                $this->stdout("\tТовар создан {$shopProduct->id}\n", Console::FG_GREEN);
            }

            $shopProduct->supplier_external_jsondata = $data;

            if (!$shopProduct->save()) {
                throw new Exception("Данные по товару не обновлены: ".print_r($shopProduct->errors, true), Console::FG_GREEN);
            }


            //Оновление наличия
            $this->stdout("\tОбновление наличия\n");
            $this->_updateStock($stock, $shopProduct);


            $this->stdout("\tОбновление цен\n");
            $this->_updatePrices([
                'purchase' =>  ArrayHelper::getValue($data, 'purchase'),
                'price' =>  ArrayHelper::getValue($data, 'price'),
                'cost' =>  ArrayHelper::getValue($data, 'cost'),
            ], $shopProduct);

            $t->commit();

            $this->stdout("\tУспех\n", Console::FG_GREEN);

        } catch (\Exception $e) {
            $t->rollBack();
            $this->stdout("Ошибка: {$e->getMessage()}\n", Console::FG_RED);
            //throw $e;
        }

    }

    protected function _updateStock($restData, ShopProduct $shopProduct)
    {
        foreach ($restData as $id => $count) {
            if (!$shopStore = \Yii::$app->cloudshop->shopSupplier->getShopStores()->andWhere(['external_id' => $id])->one()) {
                throw new Exception("Склада нет!");
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
            if (!$typePrice = \Yii::$app->cloudshop->shopSupplier->getShopTypePrices()->andWhere(['external_id' => $priceCode])->one()) {
                $typePrice = new ShopTypePrice();
                $typePrice->shop_supplier_id = \Yii::$app->cloudshop->shopSupplier->id;
                $typePrice->external_id = $priceCode;
                $typePrice->name = $priceCode;

                if (!$typePrice->save()) {
                    throw new Exception("Не сохранен тип цены: ".print_r($typePrice->errors, true));
                }

                $this->stdout("\tСоздана цена: {$priceCode}\n", Console::FG_GREEN);
            }

            $value = (float)$value;

            $price = $shopProduct->getShopProductPrices()->joinWith('typePrice as typePrice')->andWhere(['typePrice.id' => $typePrice->id])->one();
            if (!$price) {
                $price = new ShopProductPrice();
                $price->type_price_id = $typePrice->id;
                $price->product_id = $shopProduct->id;
            }

            $price->price = $value;
            $price->currency_code = "RUB";

            if (!$price->save()) {
                throw new Exception("Цена {$priceCode} не сохранена: ".print_r($price->errors, true));
            }
        }

        return true;
    }
}