<?php

/**
 * @file plugins/generic/langsciAdditionalFeatures/classes/LangsciAdditionalFeaturesSettingsForm.php
 *
 * Copyright (c) 2026 Language Science Press
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LangsciAdditionalFeaturesSettingsForm
 *
 * @brief Form to enable or disable features for a press.
 */

namespace APP\plugins\generic\langsciAdditionalFeatures\classes;

use APP\plugins\generic\langsciAdditionalFeatures\LangsciAdditionalFeaturesPlugin;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FormComponent;

class LangsciAdditionalFeaturesSettingsForm extends FormComponent
{
    public const FORM_ID = 'langsciAdditionalFeaturesSettings';

    public $id = self::FORM_ID;
    public $method = 'PUT';

    public function __construct(string $action, array $locales, $context, LangsciAdditionalFeaturesPlugin $plugin)
    {
        $this->action = $action;
        $this->locales = $locales;

        $options = [];
        foreach (FeatureRegistry::getFeatureClasses() as $featureClass) {
            $feature = new $featureClass($plugin);
            $options[] = ['value' => $feature->getId(), 'label' => __($feature->getLabelKey())];
        }

        $this->addGroup([
            'id' => 'langsciFeatures',
            'label' => __('plugins.generic.langsciAdditionalFeatures.settings.groupLabel'),
            'description' => __('plugins.generic.langsciAdditionalFeatures.settings.groupDescription'),
        ])
            ->addField(new FieldOptions(LangsciAdditionalFeaturesPlugin::SETTING_ENABLED_FEATURES, [
                'label' => __('plugins.generic.langsciAdditionalFeatures.settings.enabledFeatures'),
                'options' => $options,
                'value' => $plugin->getEnabledFeatureIds($context),
                'groupId' => 'langsciFeatures',
            ]));
    }
}
