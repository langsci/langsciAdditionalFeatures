<?php

/**
 * @file plugins/generic/langsciAdditionalFeatures/classes/features/Feature.php
 *
 * Copyright (c) 2026 Language Science Press
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Feature
 *
 * @brief Base class for a feature of the langsciAdditionalFeatures plugin.
 */

namespace APP\plugins\generic\langsciAdditionalFeatures\classes\features;

use APP\plugins\generic\langsciAdditionalFeatures\LangsciAdditionalFeaturesPlugin;

abstract class Feature
{
    public function __construct(protected LangsciAdditionalFeaturesPlugin $plugin)
    {
    }

    public function getPlugin(): LangsciAdditionalFeaturesPlugin
    {
        return $this->plugin;
    }

    /** Unique id, stored in the press setting of enabled features. */
    abstract public function getId(): string;

    /** Locale key of the label shown in the settings form. */
    abstract public function getLabelKey(): string;

    /** Register all hooks of this feature. Only called if the feature is enabled. */
    abstract public function register(): void;
}
