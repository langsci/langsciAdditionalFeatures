<?php

namespace APP\plugins\generic\langsciAdditionalFeatures\classes\features\reviews;

use APP\core\Application;
use APP\handler\Handler;
use APP\plugins\generic\langsciAdditionalFeatures\LangsciAdditionalFeaturesPlugin;
use PKP\core\JSONMessage;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\Role;

class ReviewsHandler extends Handler
{
    public function __construct(private LangsciAdditionalFeaturesPlugin $plugin)
    {
        parent::__construct();
        $this->addRoleAssignment([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN], ['fetch', 'save', 'delete']);
    }

    public function authorize($request, &$args, $roleAssignments)
    {
        if (!$request->getContext() || !$this->plugin->isFeatureEnabled('reviews', $request->getContext())) {
            return false;
        }
        $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments));
        return parent::authorize($request, $args, $roleAssignments);
    }

    public function fetch($args, $request): JSONMessage
    {
        if (!$request->isGet()) {
            return new JSONMessage(false, __('plugins.generic.langsciAdditionalFeatures.reviews.error.method'));
        }
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $reviews = (new ReviewsRepository())->listForSubmission($submission->getId());
        return new JSONMessage(true, $reviews->toArray());
    }

    public function save($args, $request): JSONMessage
    {
        if (!$request->isPost() || !$request->checkCSRF()) {
            return new JSONMessage(false, __('plugins.generic.langsciAdditionalFeatures.reviews.error.request'));
        }
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $repository = new ReviewsRepository();
        $reviewId = $request->getUserVar('reviewId');
        $existing = null;
        if ($reviewId !== null && $reviewId !== '') {
            if (!is_scalar($reviewId) || !ctype_digit((string) $reviewId) || (int) $reviewId < 1) {
                return new JSONMessage(false, __('plugins.generic.langsciAdditionalFeatures.reviews.error.notFound'));
            }
            $existing = $repository->find($submission->getId(), (int) $reviewId);
            if (!$existing) {
                return new JSONMessage(false, __('plugins.generic.langsciAdditionalFeatures.reviews.error.notFound'));
            }
        }

        $fields = [];
        foreach (['link_name', 'link', 'reviewer', 'date', 'money_quote'] as $name) {
            $value = $request->getUserVar($name);
            if (!is_string($value)) {
                return new JSONMessage(false, __('plugins.generic.langsciAdditionalFeatures.reviews.error.invalid'));
            }
            $fields[$name] = $value;
        }

        foreach (['link_name', 'link', 'reviewer', 'date'] as $name) {
            if (mb_strlen($fields[$name]) > 255 && (!$existing || $fields[$name] !== (string) $existing->{$name})) {
                return new JSONMessage(false, __('plugins.generic.langsciAdditionalFeatures.reviews.error.length'));
            }
        }
        if (trim($fields['link_name']) === '' && (!$existing || $fields['link_name'] !== (string) $existing->link_name)) {
            return new JSONMessage(false, __('plugins.generic.langsciAdditionalFeatures.reviews.error.name'));
        }
        if ((!$existing || $fields['link'] !== (string) $existing->link) && !ReviewsRepository::validUrl($fields['link'])) {
            return new JSONMessage(false, __('plugins.generic.langsciAdditionalFeatures.reviews.error.url'));
        }
        if ((!$existing || $fields['date'] !== (string) $existing->date) && $fields['date'] !== '' && !ReviewsRepository::validDate($fields['date'])) {
            return new JSONMessage(false, __('plugins.generic.langsciAdditionalFeatures.reviews.error.date'));
        }

        if ($existing) {
            $repository->update($submission->getId(), (int) $reviewId, $fields);
        } else {
            $publication = $submission->getCurrentPublication();
            if (!$publication) {
                return new JSONMessage(false, __('plugins.generic.langsciAdditionalFeatures.reviews.error.publication'));
            }
            $repository->create($submission->getId(), $publication->getId(), $fields);
        }
        return new JSONMessage(true, __('plugins.generic.langsciAdditionalFeatures.reviews.saved'));
    }

    public function delete($args, $request): JSONMessage
    {
        if (!$request->isPost() || !$request->checkCSRF()) {
            return new JSONMessage(false, __('plugins.generic.langsciAdditionalFeatures.reviews.error.request'));
        }
        $reviewId = $request->getUserVar('reviewId');
        if (!is_scalar($reviewId) || !ctype_digit((string) $reviewId) || (int) $reviewId < 1) {
            return new JSONMessage(false, __('plugins.generic.langsciAdditionalFeatures.reviews.error.notFound'));
        }
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        if (!(new ReviewsRepository())->delete($submission->getId(), (int) $reviewId)) {
            return new JSONMessage(false, __('plugins.generic.langsciAdditionalFeatures.reviews.error.notFound'));
        }
        return new JSONMessage(true, __('plugins.generic.langsciAdditionalFeatures.reviews.deleted'));
    }
}
