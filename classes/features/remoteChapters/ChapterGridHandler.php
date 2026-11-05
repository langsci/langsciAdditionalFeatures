<?php

/**
 * @file plugins/generic/langsciAdditionalFeatures/classes/features/remoteChapters/ChapterGridHandler.php
 *
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ChapterGridHandler
 *
 * @brief Chapter grid with an action to set the chapter number offset.
 */

namespace APP\plugins\generic\langsciAdditionalFeatures\classes\features\remoteChapters;

use APP\controllers\grid\users\chapter\ChapterGridHandler as CoreChapterGridHandler;
use PKP\core\JSONMessage;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\security\Role;

class ChapterGridHandler extends CoreChapterGridHandler
{
    public function __construct(protected RemoteChaptersFeature $feature)
    {
        parent::__construct();
        $this->addRoleAssignment(
            [Role::ROLE_ID_AUTHOR, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_MANAGER, Role::ROLE_ID_ASSISTANT],
            ['editChapterOffset', 'updateChapterOffset']
        );
    }

    /**
     * @copydoc CoreChapterGridHandler::initialize()
     *
     * @param null|mixed $args
     */
    public function initialize($request, $args = null)
    {
        parent::initialize($request, $args);

        if (!$this->getReadOnly() && $this->canAdminister($request->getUser())) {
            $this->addAction(
                new LinkAction(
                    'editChapterOffset',
                    new AjaxModal(
                        $request->getRouter()->url($request, null, null, 'editChapterOffset', null, $this->getRequestArgs()),
                        __('plugins.generic.langsciAdditionalFeatures.remoteChapters.offset.title')
                    ),
                    __('plugins.generic.langsciAdditionalFeatures.remoteChapters.offset.title'),
                    'edit'
                )
            );
        }
    }

    /**
     * Display the chapter number offset form.
     *
     * @param array $args
     * @param \APP\core\Request $request
     */
    public function editChapterOffset($args, $request)
    {
        if (!$this->canAdminister($request->getUser())) {
            return new JSONMessage(false);
        }
        $form = new ChapterOffsetForm($this->feature, $this->getPublication());
        $form->initData();
        return new JSONMessage(true, $form->fetch($request));
    }

    /**
     * Save the chapter number offset.
     *
     * @param array $args
     * @param \APP\core\Request $request
     */
    public function updateChapterOffset($args, $request)
    {
        if (!$this->canAdminister($request->getUser())) {
            return new JSONMessage(false);
        }
        $form = new ChapterOffsetForm($this->feature, $this->getPublication());
        $form->readInputData();
        if (!$form->validate()) {
            return new JSONMessage(true, $form->fetch($request));
        }
        $form->execute();
        return new JSONMessage(true);
    }
}
