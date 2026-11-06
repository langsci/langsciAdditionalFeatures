<?php

/**
 * @file plugins/generic/langsciAdditionalFeatures/classes/features/remoteChapters/RemoteChaptersFeature.php
 *
 * Copyright (c) 2021-2026 Language Science Press
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RemoteChaptersFeature
 *
 * @brief Adds remote galley URLs and chapter numbering to chapters.
 */

namespace APP\plugins\generic\langsciAdditionalFeatures\classes\features\remoteChapters;

use APP\facades\Repo;
use APP\plugins\generic\langsciAdditionalFeatures\classes\features\Feature;
use APP\publication\Publication;
use Illuminate\Support\Facades\DB;
use PKP\db\DAORegistry;
use PKP\form\validation\FormValidatorUrl;
use PKP\plugins\Hook;

class RemoteChaptersFeature extends Feature
{
    /** Chapter settings added by this feature. */
    public const FIELDS = ['urlRemote', 'urlRemoteLabel', 'excludeFromCount'];

    /** Publication setting holding the chapter number offset. */
    public const OFFSET_FIELD = 'chapterNumberOffset';

    /** Publication setting holding the default chapter label. */
    public const LABEL_FIELD = 'chapterLabelDefault';

    /** Publication setting set once the numbers have been written into the labels. */
    public const NUMBERED_FIELD = 'chapterLabelsNumbered';

    public function getId(): string
    {
        return 'remoteChapters';
    }

    public function getLabelKey(): string
    {
        return 'plugins.generic.langsciAdditionalFeatures.remoteChapters.settingsLabel';
    }

    public function register(): void
    {
        Hook::add('TemplateResource::getFilename', [$this, 'overrideChapterFormTemplate']);
        Hook::add('chapterform::Constructor', [$this, 'constructForm']);
        Hook::add('chapterform::display', [$this, 'initFormData']);
        Hook::add('chapterform::readuservars', [$this, 'readInputData']);
        Hook::add('chapterform::execute', [$this, 'executeForm']);
        Hook::add('chapterdao::getAdditionalFieldNames', [$this, 'getAdditionalFieldNames']);
        // Remote chapters are now displayed by langsciDefaultChildTheme plugin
        // Hook::add('Templates::Catalog::Book::Main', [$this, 'displayRemoteChapters']);
        Hook::add('Publication::publish', [$this, 'numberLabelsOnPublish']);
        Hook::add('Schema::get::publication', [$this, 'addPublicationSchema']);
        Hook::add('LoadComponentHandler', [$this, 'loadChapterGridHandler']);
    }

    /**
     * Add the chapter number offset to the publication schema.
     *
     * @param array $args [&$schema]
     */
    public function addPublicationSchema($hookName, $args)
    {
        $schema = &$args[0];
        $schema->properties->{self::OFFSET_FIELD} = (object) [
            'type' => 'integer',
            'description' => 'The number that is added to the position of a chapter to get its displayed chapter number.',
            'validation' => ['nullable'],
        ];
        $schema->properties->{self::LABEL_FIELD} = (object) [
            'type' => 'string',
            'description' => 'The default label of the remote chapter galleys of the publication.',
            'validation' => ['nullable'],
        ];
        $schema->properties->{self::NUMBERED_FIELD} = (object) [
            'type' => 'boolean',
            'description' => 'Whether the chapter numbers have been permanently written into the remote chapter labels.',
            'validation' => ['nullable'],
        ];
        return false;
    }

    /**
     * Permanently write the chapter numbers into the remote chapter labels
     * and stop counting dynamically.
     *
     * @param array $args [&$publication, $oldPublication, $submission]
     */
    public function numberLabelsOnPublish($hookName, $args)
    {
        $publication = $args[0];
        if ($publication->getData(self::NUMBERED_FIELD)) {
            return false;
        }
        $offset = $this->getChapterOffset($publication);
        $defaultLabel = $this->getDefaultLabel($publication);

        $position = 0;
        foreach ($this->getOrderedChapters($publication->getId()) as $chapter) {
            if ($chapter->getData('excludeFromCount')) {
                continue;
            }
            $position++;
            $label = (string) $chapter->getData('urlRemoteLabel');
            if (!preg_match('#^https?://#i', (string) $chapter->getData('urlRemote'))
                || preg_match('/\d/', $label)) {
                continue;
            }
            DB::table('submission_chapter_settings')->updateOrInsert(
                ['chapter_id' => (int) $chapter->getId(), 'locale' => '', 'setting_name' => 'urlRemoteLabel'],
                ['setting_value' => $label . ' ' . ($offset + $position), 'setting_type' => 'string']
            );
        }
        Repo::publication()->edit($publication, [self::NUMBERED_FIELD => true]);
        return false;
    }

    /**
     * Replace the chapter grid handler with the subclass that offers the offset action.
     *
     * @param array $args [&$component, &$op, &$componentInstance]
     */
    public function loadChapterGridHandler($hookName, $args)
    {
        if ($args[0] !== 'grid.users.chapter.ChapterGridHandler') {
            return false;
        }
        $args[2] = new ChapterGridHandler($this);
        return true;
    }

    public function getChapterOffset(Publication $publication): int
    {
        return (int) $publication->getData(self::OFFSET_FIELD);
    }

    public function getDefaultLabel(Publication $publication): string
    {
        $label = trim((string) $publication->getData(self::LABEL_FIELD));
        return $label !== '' ? $label : __('plugins.generic.langsciAdditionalFeatures.remoteChapters.chapterLabel');
    }

    protected function getOrderedChapters(int $publicationId): array
    {
        $chapterDao = DAORegistry::getDAO('ChapterDAO'); /** @var \APP\monograph\ChapterDAO $chapterDao */
        return array_values($chapterDao->getByPublicationId($publicationId)->toArray());
    }

    /**
     * Override the chapter form template, and nothing else.
     */
    public function overrideChapterFormTemplate($hookName, $args)
    {
        if (!str_ends_with((string) $args[1], 'chapter/form/chapterForm.tpl')) {
            return false;
        }
        return $this->plugin->_overridePluginTemplates($hookName, $args);
    }

    public function constructForm($hookName, $args)
    {
        $form = $args[0];
        $form->addCheck(new FormValidatorUrl($form, 'urlRemote', 'optional', 'user.profile.form.urlInvalid'));
        return false;
    }

    /**
     * Load the chapter's remote settings into the form, pre-fill the label of
     * a new chapter and provide the read-only chapter number.
     */
    public function initFormData($hookName, $args)
    {
        $form = $args[0];
        $chapter = $form->getChapter();
        $publication = $form->getPublication();
        $chapters = $this->getOrderedChapters($publication->getId());

        $position = 0;
        $found = false;
        foreach ($chapters as $other) {
            if ($chapter && $other->getId() == $chapter->getId()) {
                $found = true;
                break;
            }
            if (!$other->getData('excludeFromCount')) {
                $position++;
            }
        }

        if ($chapter) {
            foreach (self::FIELDS as $field) {
                if ($form->getData($field) === null) {
                    $form->setData($field, $chapter->getData($field));
                }
            }
        } elseif ($form->getData('urlRemoteLabel') === null) {
            $form->setData('urlRemoteLabel', $this->getDefaultLabel($publication));
        }
        $excluded = $publication->getData(self::NUMBERED_FIELD) || ($chapter && $found && $chapter->getData('excludeFromCount'));
        $form->setData('remoteChapterNumber', $excluded ? '' : $this->getChapterOffset($publication) + $position + 1);
        return false;
    }

    public function readInputData($hookName, $args)
    {
        $args[1] = array_merge($args[1], self::FIELDS);
        return false;
    }

    /**
     * There is no chapter schema in OMP 3.5, so this deprecated hook is still required.
     * DAO hooks are fired with Hook::run, so the arguments are spread, not wrapped in an array.
     */
    public function getAdditionalFieldNames($hookName, $dao, &$fields)
    {
        $fields = array_merge($fields, self::FIELDS);
        return false;
    }

    /**
     * A new chapter does not exist until the form's execute() method finishes,
     * so its settings are persisted by a shutdown function.
     */
    public function executeForm($hookName, $args)
    {
        $form = $args[0];
        $values = [];
        foreach (self::FIELDS as $field) {
            $value = trim((string) $form->getData($field));
            $values[$field] = $value === '' ? null : $value;
        }
        if ($values['urlRemote'] === null) {
            $values['urlRemoteLabel'] = null;
            $values['excludeFromCount'] = null;
        }

        $chapter = $form->getChapter();
        if ($chapter) {
            foreach ($values as $field => $value) {
                $chapter->setData($field, $value);
            }
        } else {
            register_shutdown_function([$this, 'saveNewChapterSettings'], $form, $values);
        }
        return false;
    }

    /**
     * Store the remote settings of a chapter created by the form.
     *
     * @param \APP\controllers\grid\users\chapter\form\ChapterForm $form
     */
    public function saveNewChapterSettings($form, array $values): void
    {
        $chapter = $form->getChapter();
        if (!$chapter || !$chapter->getId()) {
            return;
        }
        foreach ($values as $field => $value) {
            $key = ['chapter_id' => (int) $chapter->getId(), 'locale' => '', 'setting_name' => $field];
            if ($value === null) {
                DB::table('submission_chapter_settings')->where($key)->delete();
            } else {
                DB::table('submission_chapter_settings')->updateOrInsert($key, ['setting_value' => $value, 'setting_type' => 'string']);
            }
        }
    }

    public function displayRemoteChapters($hookName, $args)
    {
        $templateMgr = $args[1];
        $output = &$args[2];

        $remoteChapters = [];
        foreach ((array) $templateMgr->getTemplateVars('chapters') as $chapter) {
            if (preg_match('#^https?://#i', (string) $chapter->getData('urlRemote'))) {
                $remoteChapters[] = $chapter;
            }
        }
        if (!$remoteChapters) {
            return false;
        }

        $templateMgr->assign('remoteChapters', $remoteChapters);
        $output .= $templateMgr->fetch($this->plugin->getTemplateResource('remoteChapters/remoteChapters.tpl'));
        return false;
    }
}
