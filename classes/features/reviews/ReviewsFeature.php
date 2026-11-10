<?php

/**
 * @file plugins/generic/langsciAdditionalFeatures/classes/features/reviews/ReviewsFeature.php
 *
 * Copyright (c) 2026 Language Science Press
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Expose existing submission reviews to the book page.
 */

namespace APP\plugins\generic\langsciAdditionalFeatures\classes\features\reviews;

use APP\core\Application;
use APP\plugins\generic\langsciAdditionalFeatures\classes\features\Feature;
use APP\template\TemplateManager;
use DateTimeImmutable;
use PKP\components\forms\FieldText;
use PKP\components\forms\FieldTextarea;
use PKP\components\forms\FormComponent;
use PKP\core\PKPApplication;
use PKP\plugins\Hook;
use PKP\security\Role;

class ReviewsFeature extends Feature
{
    public function getId(): string
    {
        return 'reviews';
    }

    public function getLabelKey(): string
    {
        return 'plugins.generic.langsciAdditionalFeatures.reviews.settingsLabel';
    }

    public function register(): void
    {
        Hook::add('TemplateManager::display', $this->assignReviews(...));
        Hook::add('TemplateManager::display', $this->addWorkflowEditor(...));
        Hook::add('LoadHandler', $this->loadReviewsHandler(...), Hook::SEQUENCE_CORE);
    }

    public function loadReviewsHandler(string $hookName, array $args): bool
    {
        $handler = &$args[3];
        if ($args[0] !== 'langsciReviews' || !in_array($args[1], ['fetch', 'save', 'delete'], true)) {
            return Hook::CONTINUE;
        }
        $handler = new ReviewsHandler($this->plugin);
        return Hook::ABORT;
    }

    public function addWorkflowEditor(string $hookName, array $args): bool
    {
        [$templateMgr, $template] = $args;
        if ($template !== 'dashboard/editors.tpl') {
            return Hook::CONTINUE;
        }

        $request = Application::get()->getRequest();
        $roles = (array) $request->getRouter()->getHandler()->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
        if (!array_intersect([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN], $roles)) {
            return Hook::CONTINUE;
        }

        $labels = [];
        foreach (['menu', 'manage', 'add', 'edit', 'delete', 'cancel', 'save', 'confirmDelete', 'empty', 'loading', 'name', 'url', 'reviewer', 'date', 'quotation', 'requestFailed'] as $name) {
            $labels[$name] = __('plugins.generic.langsciAdditionalFeatures.reviews.' . $name);
        }
        $form = new FormComponent('langsciReviewsForm', 'POST', FormComponent::ACTION_EMIT, []);
        foreach (['link_name' => 'name', 'link' => 'url', 'reviewer' => 'reviewer', 'date' => 'date'] as $name => $label) {
            $form->addField(new FieldText($name, ['label' => $labels[$label]]));
        }
        $form->addField(new FieldTextarea('money_quote', ['label' => $labels['quotation']]));
        $formConfig = $form->getConfig();
        $formConfig['pages'][0]['submitButton'] = ['label' => $labels['save'], 'isPrimary' => true];
        $formConfig['pages'][0]['cancelButton'] = ['label' => $labels['cancel']];
        $config = [
            'labels' => $labels,
            'form' => $formConfig,
            'urls' => array_combine(
                ['fetch', 'save', 'delete'],
                array_map(
                    fn ($op) => $request->getDispatcher()->url($request, PKPApplication::ROUTE_PAGE, null, 'langsciReviews', $op),
                    ['fetch', 'save', 'delete']
                )
            ),
        ];
        $templateMgr->addJavaScript(
            'langsciReviewsConfig',
            'window.langsciReviewsConfig = ' . json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) . ';',
            ['contexts' => ['backend'], 'priority' => TemplateManager::STYLE_SEQUENCE_LAST, 'inline' => true]
        );
        $templateMgr->addJavaScript(
            'langsciReviewsWorkflow',
            $request->getBaseUrl() . '/' . $this->plugin->getPluginPath() . '/js/reviewsWorkflow.js',
            ['contexts' => ['backend'], 'priority' => TemplateManager::STYLE_SEQUENCE_LAST]
        );

        return Hook::CONTINUE;
    }

    public function assignReviews(string $hookName, array $args): bool
    {
        [$templateMgr, $template] = $args;
        if ($template !== 'frontend/pages/book.tpl' || $templateMgr->getTemplateVars('isChapterRequest')) {
            return Hook::CONTINUE;
        }

        $submission = $templateMgr->getTemplateVars('publishedSubmission');
        if (!$submission) {
            return Hook::CONTINUE;
        }

        $reviews = (new ReviewsRepository())->listForSubmission($submission->getId())
            ->map(function ($review) {
                $date = (string) ($review->date ?? '');
                $dateIsValid = ReviewsRepository::validDate($date);
                $parsedDate = $dateIsValid ? DateTimeImmutable::createFromFormat('!Ymd', $date) : false;
                $link = (string) ($review->link ?? '');

                return [
                    'link' => $link,
                    'linkIsValid' => ReviewsRepository::validUrl($link),
                    'name' => $review->link_name,
                    'reviewer' => $review->reviewer,
                    'date' => $dateIsValid ? $parsedDate->format('Y-m-d') : $date,
                    'dateIsValid' => (bool) $dateIsValid,
                    'money_quote' => $review->money_quote,
                ];
            })
            ->all();

        $templateMgr->assign('reviewsBySubmission', $reviews);
        return Hook::CONTINUE;
    }
}
