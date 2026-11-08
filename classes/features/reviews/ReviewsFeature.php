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

use APP\plugins\generic\langsciAdditionalFeatures\classes\features\Feature;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use PKP\plugins\Hook;

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

        $reviews = DB::table('langsci_review_links')
            ->where('submission_id', $submission->getId())
            ->orderBy('review_id')
            ->get(['link', 'link_name', 'reviewer', 'date', 'money_quote'])
            ->map(function ($review) {
                $date = (string) ($review->date ?? '');
                $parsedDate = preg_match('/^[0-9]{8}$/D', $date)
                    ? DateTimeImmutable::createFromFormat('!Ymd', $date)
                    : false;
                $dateIsValid = $parsedDate && $parsedDate->format('Ymd') === $date;
                $link = (string) ($review->link ?? '');

                return [
                    'link' => $link,
                    'linkIsValid' => (bool) (preg_match('~^https?://~i', $link) && filter_var($link, FILTER_VALIDATE_URL)),
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
