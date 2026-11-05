<?php

/**
 * @file plugins/generic/langsciAdditionalFeatures/classes/FeatureRegistry.php
 *
 * Copyright (c) 2026 Language Science Press
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FeatureRegistry
 *
 * @brief Lists all features available in this plugin.
 */

namespace APP\plugins\generic\langsciAdditionalFeatures\classes;

use APP\plugins\generic\langsciAdditionalFeatures\classes\features\Feature;
use APP\plugins\generic\langsciAdditionalFeatures\classes\features\pubState\PubStateFeature;
use APP\plugins\generic\langsciAdditionalFeatures\classes\features\remoteChapters\RemoteChaptersFeature;

class FeatureRegistry
{
    /**
     * @return class-string<Feature>[]
     */
    public static function getFeatureClasses(): array
    {
        return [
            PubStateFeature::class,
            RemoteChaptersFeature::class,
        ];
    }
}
