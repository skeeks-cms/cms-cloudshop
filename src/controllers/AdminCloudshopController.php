<?php
/**
 * @link https://cms.skeeks.com/
 * @copyright Copyright (c) 2010 SkeekS
 * @license https://cms.skeeks.com/license/
 * @author Semenov Alexander <semenov@skeeks.com>
 */

namespace skeeks\cms\cloudshop\controllers;

use yii\console\Controller;

/**
 * @author Semenov Alexander <semenov@skeeks.com>
 */
class AdminCloudshopController extends Controller
{

    public function actionImportStories()
    {

        print_r(\Yii::$app->cloudshopApiClient->getStoresApiMethod());
die;
    }

    public function actionImportProducts()
    {

        print_r(\Yii::$app->cloudshopApiClient->getCatalogApiMethod([
            'types' => [
                //'inventory',
                //'group'
            ]
        ]));
die;
    }
}