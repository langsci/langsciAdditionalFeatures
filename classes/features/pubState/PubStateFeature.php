<?php

/**
 * @file plugins/generic/langsciAdditionalFeatures/classes/features/pubState/PubStateFeature.php
 *
 * Copyright (c) 2026 Language Science Press
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PubStateFeature
 *
 * @brief Adds a "Publication State" (forthcoming, published, superseded)
 *   to publications. Display on the frontend is up to the theme.
 */

namespace APP\plugins\generic\langsciAdditionalFeatures\classes\features\pubState;

use APP\plugins\generic\langsciAdditionalFeatures\classes\features\Feature;
use PKP\components\forms\FieldSelect;
use PKP\components\forms\publication\TitleAbstractForm;
use PKP\plugins\Hook;

class PubStateFeature extends Feature
{
    public const FORTHCOMING = 1;
    public const PUBLISHED = 2;
    public const SUPERSEDED = 3;

    public const SETTING_NAME = 'pubState';

    public function getId(): string
    {
        return 'pubState';
    }

    public function getLabelKey(): string
    {
        return 'plugins.generic.langsciAdditionalFeatures.pubState.settingsLabel';
    }

    public function register(): void
    {
        Hook::add('Schema::get::publication', $this->addToPublicationSchema(...));
        Hook::add('Form::config::before', $this->addFormField(...));
        Hook::add('Publication::edit', $this->handlePublicationEdit(...));
    }

    public function addToPublicationSchema(string $hookName, array $args): bool
    {
        $schema = $args[0];
        $schema->properties->{self::SETTING_NAME} = (object) [
            'type' => 'integer',
            'apiSummary' => true,
            'multilingual' => false,
            'validation' => ['in:' . implode(',', [self::FORTHCOMING, self::PUBLISHED, self::SUPERSEDED])],
            'default' => self::FORTHCOMING,
        ];
        return Hook::CONTINUE;
    }

    public function addFormField(string $hookName, $form): bool
    {
        if (!$form instanceof TitleAbstractForm) {
            return Hook::CONTINUE;
        }

        $form->addField(new FieldSelect(self::SETTING_NAME, [
            'label' => __('plugins.generic.langsciAdditionalFeatures.pubState.fieldLabel'),
            'description' => __('plugins.generic.langsciAdditionalFeatures.pubState.fieldDescription'),
            'value' => $this->getPubState($form->publication),
            'options' => [
                ['value' => self::FORTHCOMING, 'label' => __('plugins.generic.langsciAdditionalFeatures.pubState.label.forthcoming')],
                ['value' => self::PUBLISHED, 'label' => __('plugins.generic.langsciAdditionalFeatures.pubState.label.published')],
                ['value' => self::SUPERSEDED, 'label' => __('plugins.generic.langsciAdditionalFeatures.pubState.label.superseded')],
            ],
        ]), [FIELD_POSITION_BEFORE, 'prefix']);

        return Hook::CONTINUE;
    }

    /**
     * Get the state of a publication (defaults to forthcoming).
     */
    public function getPubState($publication): int
    {
        return (int) ($publication->getData(self::SETTING_NAME) ?: self::FORTHCOMING);
    }

    /**
     * Get the label prefix for a publication ("Forthcoming: ", "Superseded: ", or empty).
     */
    public function getPubStateLabel($publication): string
    {
        return match ($this->getPubState($publication)) {
            self::FORTHCOMING => __('plugins.generic.langsciAdditionalFeatures.pubState.label.forthcoming') . ': ',
            self::SUPERSEDED => __('plugins.generic.langsciAdditionalFeatures.pubState.label.superseded') . ': ',
            default => '',
        };
    }
    public function handlePublicationEdit(string $hookName, $args): bool
    {
        $publication = $args[0];
        if ($this->getPubState($publication) === self::FORTHCOMING) {
            $publication->setData('datePublished', null);
        }
        return Hook::CONTINUE;
    }
}
