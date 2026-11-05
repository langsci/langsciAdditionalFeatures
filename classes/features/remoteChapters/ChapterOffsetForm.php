<?php

/**
 * @file plugins/generic/langsciAdditionalFeatures/classes/features/remoteChapters/ChapterOffsetForm.php
 *
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ChapterOffsetForm
 *
 * @brief Form to set the chapter number offset of a publication.
 */

namespace APP\plugins\generic\langsciAdditionalFeatures\classes\features\remoteChapters;

use APP\facades\Repo;
use APP\publication\Publication;
use PKP\form\Form;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorPost;
use PKP\form\validation\FormValidatorRegExp;

class ChapterOffsetForm extends Form
{
    public function __construct(protected RemoteChaptersFeature $feature, protected Publication $publication)
    {
        parent::__construct($feature->getPlugin()->getTemplateResource('remoteChapters/chapterOffsetForm.tpl'));
        $this->addCheck(new FormValidatorRegExp($this, RemoteChaptersFeature::OFFSET_FIELD, 'required', 'plugins.generic.langsciAdditionalFeatures.remoteChapters.offset.invalid', '/^-?\d{1,4}$/'));
        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    /**
     * @copydoc Form::initData()
     */
    public function initData()
    {
        $this->setData(RemoteChaptersFeature::OFFSET_FIELD, $this->feature->getChapterOffset($this->publication));
        $this->setData(RemoteChaptersFeature::LABEL_FIELD, $this->publication->getData(RemoteChaptersFeature::LABEL_FIELD));
        $this->setData('submissionId', $this->publication->getData('submissionId'));
        $this->setData('publicationId', $this->publication->getId());
    }

    /**
     * @copydoc Form::readInputData()
     */
    public function readInputData()
    {
        $this->readUserVars([RemoteChaptersFeature::OFFSET_FIELD, RemoteChaptersFeature::LABEL_FIELD, 'submissionId', 'publicationId']);
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        parent::execute(...$functionArgs);
        Repo::publication()->edit($this->publication, [
            RemoteChaptersFeature::OFFSET_FIELD => (int) $this->getData(RemoteChaptersFeature::OFFSET_FIELD),
            RemoteChaptersFeature::LABEL_FIELD => trim((string) $this->getData(RemoteChaptersFeature::LABEL_FIELD)) ?: null,
        ]);
    }
}
