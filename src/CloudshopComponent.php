<?php
/**
 * @link https://cms.skeeks.com/
 * @copyright Copyright (c) 2010 SkeekS
 * @license https://cms.skeeks.com/license/
 * @author Semenov Alexander <semenov@skeeks.com>
 */

namespace skeeks\cms\cloudshop;

use skeeks\cms\base\Component;
use skeeks\cms\cloudshop\assets\CloudshopAsset;
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 */
class CloudshopComponent extends Component
{
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
}