<?php

/**
 * @file plugins/generic/langsciAdditionalFeatures/LangsciAdditionalFeaturesPlugin.php
 *
 * Copyright (c) 2026 Language Science Press
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LangsciAdditionalFeaturesPlugin
 *
 * @brief Container plugin for Language Science Press additional features.
 *   Each feature lives in its own class and can be enabled per press
 *   via Settings -> Workflow.
 */

namespace APP\plugins\generic\langsciAdditionalFeatures;

use APP\core\Application;
use APP\plugins\generic\langsciAdditionalFeatures\classes\features\Feature;
use APP\plugins\generic\langsciAdditionalFeatures\classes\FeatureRegistry;
use APP\plugins\generic\langsciAdditionalFeatures\classes\LangsciAdditionalFeaturesSettingsForm;
use Illuminate\Support\Facades\DB;
use PKP\core\PKPApplication;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;

class LangsciAdditionalFeaturesPlugin extends GenericPlugin
{
    public const SETTING_ENABLED_FEATURES = 'langsciEnabledFeatures';

    /** @var Feature[] Loaded feature instances, keyed by feature id */
    private array $features = [];

    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
        if (!$success || !$this->getEnabled($mainContextId)) {
            return $success;
        }

        // Features are configured per press only
        $context = Application::get()->getRequest()->getContext();
        if (!$context) {
            return $success;
        }

        Hook::add('Schema::get::context', $this->addSettingsToContextSchema(...));
        Hook::add('Template::Settings::workflow::submission', $this->addSettingsTab(...));

        foreach (FeatureRegistry::getFeatureClasses() as $featureClass) {
            $feature = new $featureClass($this);
            if ($this->isFeatureEnabled($feature->getId(), $context)) {
                $this->features[$feature->getId()] = $feature;
                $feature->register();
            }
        }

        return $success;
    }

    public function isFeatureEnabled(string $featureId, $context = null): bool
    {
        $context ??= Application::get()->getRequest()->getContext();
        if (!$context) {
            return false;
        }
        return in_array($featureId, $this->getEnabledFeatureIds($context), true);
    }

    /**
     * The context object is loaded before plugins register, i.e. before the
     * setting is part of the context schema, so getData() can't be trusted here.
     * Read the setting directly.
     */
    public function getEnabledFeatureIds($context): array
    {
        $value = DB::table('press_settings')
            ->where('press_id', $context->getId())
            ->where('setting_name', self::SETTING_ENABLED_FEATURES)
            ->value('setting_value');
        $ids = $value ? json_decode($value, true) : [];
        return is_array($ids) ? $ids : [];
    }

    /**
     * Get a loaded (i.e. enabled for the current press) feature, or null.
     */
    public function getFeature(string $featureId): ?Feature
    {
        return $this->features[$featureId] ?? null;
    }

    public function addSettingsToContextSchema(string $hookName, array $args): bool
    {
        $schema = $args[0];
        $schema->properties->{self::SETTING_ENABLED_FEATURES} = (object) [
            'type' => 'array',
            'apiSummary' => true,
            'validation' => ['nullable'],
            'items' => (object) ['type' => 'string'],
        ];
        return Hook::CONTINUE;
    }

    /**
     * Add the feature settings tab to Settings -> Workflow -> Submission.
     */
    public function addSettingsTab(string $hookName, array $args): bool
    {
        $templateMgr = $args[1];
        $output = &$args[2];
        $request = Application::get()->getRequest();
        $context = $request->getContext();

        $contextApiUrl = $request->getDispatcher()->url(
            $request,
            PKPApplication::ROUTE_API,
            $context->getPath(),
            'contexts/' . $context->getId()
        );

        $form = new LangsciAdditionalFeaturesSettingsForm(
            $contextApiUrl,
            $context->getSupportedLocaleNames(),
            $context,
            $this
        );

        $state = $templateMgr->getTemplateVars('state');
        $state['components'][LangsciAdditionalFeaturesSettingsForm::FORM_ID] = $form->getConfig();
        $templateMgr->assign('state', $state);
        $templateMgr->assign('langsciFormId', LangsciAdditionalFeaturesSettingsForm::FORM_ID);

        $output .= $templateMgr->fetch($this->getTemplateResource('settingsForm.tpl'));

        return Hook::CONTINUE;
    }

    public function getDisplayName()
    {
        return __('plugins.generic.langsciAdditionalFeatures.displayName');
    }

    public function getDescription()
    {
        return __('plugins.generic.langsciAdditionalFeatures.description');
    }
}
