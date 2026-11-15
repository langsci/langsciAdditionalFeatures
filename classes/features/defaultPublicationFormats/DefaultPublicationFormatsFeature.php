<?php

/**
 * @file plugins/generic/langsciAdditionalFeatures/classes/features/defaultPublicationFormats/DefaultPublicationFormatsFeature.php
 *
 * Copyright (c) 2026 Language Science Press
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 */

namespace APP\plugins\generic\langsciAdditionalFeatures\classes\features\defaultPublicationFormats;

use APP\core\Application;
use APP\facades\Repo;
use APP\log\event\SubmissionEventLogEntry;
use APP\plugins\generic\langsciAdditionalFeatures\classes\features\Feature;
use APP\submission\Submission;
use Illuminate\Support\Facades\DB;
use PKP\core\Core;
use PKP\core\PKPApplication;
use PKP\plugins\Hook;
use PKP\security\Role;

class DefaultPublicationFormatsFeature extends Feature
{
    public const INITIALIZED = 'langsciDefaultFormatsInitialized';
    public const LEGACY_CREATED = 'defaultPubFormatsCreated';

    private const FORMATS = [
        'Datasets' => 'EA',
        'PDF' => 'DA',
        'Hardcover' => 'BB',
        'Buy from Amazon.de' => 'BC',
        'Buy from Amazon.co.uk' => 'BC',
        'Buy from Amazon.com' => 'BC',
        'Collaborative reading on Paperhive' => 'DA',
    ];

    public function getId(): string
    {
        return 'defaultPublicationFormats';
    }

    public function getLabelKey(): string
    {
        return 'plugins.generic.langsciAdditionalFeatures.defaultPublicationFormats.settingsLabel';
    }

    public static function addToPublicationSchema(string $hookName, array $args): bool
    {
        $schema = $args[0];
        $schema->properties->{self::INITIALIZED} = (object) [
            'type' => 'boolean',
            'writeOnly' => true,
            'validation' => ['prohibited'],
        ];
        $schema->properties->{self::LEGACY_CREATED} ??= (object) [
            'type' => 'string',
            'writeOnly' => true,
            'validation' => ['prohibited'],
        ];
        return Hook::CONTINUE;
    }

    public function register(): void
    {
        Hook::add('publicationformatgridhandler::initfeatures', $this->initializeGrid(...));
    }

    public function initializeGrid(string $hookName, array $args): bool
    {
        [$grid, $request] = $args;
        if (!$request->isGet() || $request->getRouter()->getRequestedOp($request) !== 'fetchGrid') {
            return Hook::CONTINUE;
        }

        $submission = $grid->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $publication = $grid->getAuthorizedContextObject(Application::ASSOC_TYPE_PUBLICATION);
        $roles = (array) $grid->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
        $user = $request->getUser();
        $context = $request->getContext();
        if (!$submission || !$publication || !$user || !$context
            || $submission->getData('contextId') !== $context->getId()
            || $publication->getData('submissionId') !== $submission->getId()
            || !array_intersect($roles, [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT])
            || (!in_array(Role::ROLE_ID_SITE_ADMIN, $roles, true) && !Repo::submission()->canEditPublication($submission->getId(), $user->getId()))) {
            return Hook::CONTINUE;
        }

        DB::transaction(function () use ($submission, $publication, $user) {
            $row = DB::table('publications')
                ->where('publication_id', $publication->getId())
                ->lockForUpdate()
                ->first();
            if (!$row) {
                throw new \RuntimeException('Publication disappeared while initializing default formats.');
            }
            if ((int) $row->status === Submission::STATUS_PUBLISHED) {
                return;
            }

            $publication = Repo::publication()->get($publication->getId());
            if (!$publication) {
                throw new \RuntimeException('Publication could not be loaded while initializing default formats.');
            }
            if ($publication->getData(self::INITIALIZED)) {
                return;
            }

            if (!$publication->getData(self::LEGACY_CREATED)) {
                $this->createMissingFormats($publication, $submission, $user->getId());
            }
            DB::table('publication_settings')->updateOrInsert(
                ['publication_id' => $publication->getId(), 'locale' => '', 'setting_name' => self::INITIALIZED],
                ['setting_value' => '1']
            );
        });

        return Hook::CONTINUE;
    }

    private function createMissingFormats($publication, $submission, int $userId): void
    {
        $formatDao = Application::getRepresentationDAO();
        $formats = $formatDao->getByPublicationId($publication->getId());
        $names = [];
        $sequence = 0;
        foreach ($formats as $format) {
            $names = array_merge($names, array_values((array) $format->getName()));
            $sequence = max($sequence, $format->getSequence());
        }

        $locale = $publication->getData('locale') ?: $submission->getData('locale');
        if (!$locale) {
            throw new \RuntimeException('Cannot initialize default formats without a publication or submission locale.');
        }

        foreach (self::FORMATS as $name => $entryKey) {
            if (in_array($name, $names, true)) {
                continue;
            }
            $format = $formatDao->newDataObject();
            $format->setData('publicationId', $publication->getId());
            $format->setSequence(++$sequence);
            $format->setName([$locale => $name]);
            $format->setEntryKey($entryKey);
            $format->setPhysicalFormat($name === 'Hardcover');
            if ($name === 'Hardcover') {
                $format->setWidth(180);
                $format->setHeight(245);
            }
            $formatDao->insertObject($format);

            $event = Repo::eventLog()->newDataObject([
                'assocType' => PKPApplication::ASSOC_TYPE_SUBMISSION,
                'assocId' => $submission->getId(),
                'eventType' => SubmissionEventLogEntry::SUBMISSION_LOG_PUBLICATION_FORMAT_CREATE,
                'userId' => $userId,
                'message' => __('submission.event.publicationFormatCreated', ['formatName' => $name]),
                'isTranslated' => true,
                'dateLogged' => Core::getCurrentDate(),
            ]);
            Repo::eventLog()->add($event);
        }
    }
}
