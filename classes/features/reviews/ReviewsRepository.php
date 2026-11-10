<?php

namespace APP\plugins\generic\langsciAdditionalFeatures\classes\features\reviews;

use DateTimeImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReviewsRepository
{
    public function listForSubmission(int $submissionId): Collection
    {
        return DB::table('langsci_review_links')
            ->where('submission_id', $submissionId)
            ->orderBy('review_id')
            ->get(['review_id', 'publication_id', 'link', 'link_name', 'reviewer', 'date', 'money_quote']);
    }

    public function find(int $submissionId, int $reviewId): ?object
    {
        return DB::table('langsci_review_links')
            ->where('submission_id', $submissionId)
            ->where('review_id', $reviewId)
            ->first();
    }

    public function create(int $submissionId, int $publicationId, array $fields): int
    {
        return (int) DB::table('langsci_review_links')->insertGetId([
            ...$fields,
            'submission_id' => $submissionId,
            'publication_id' => $publicationId,
        ], 'review_id');
    }

    public function update(int $submissionId, int $reviewId, array $fields): int
    {
        return DB::table('langsci_review_links')
            ->where('submission_id', $submissionId)
            ->where('review_id', $reviewId)
            ->update($fields);
    }

    public function delete(int $submissionId, int $reviewId): int
    {
        return DB::table('langsci_review_links')
            ->where('submission_id', $submissionId)
            ->where('review_id', $reviewId)
            ->delete();
    }

    public static function validDate(string $date): bool
    {
        if (!preg_match('/^[0-9]{8}$/D', $date)) {
            return false;
        }
        $parsed = DateTimeImmutable::createFromFormat('!Ymd', $date);
        return $parsed && $parsed->format('Ymd') === $date;
    }

    public static function validUrl(string $url): bool
    {
        return (bool) (preg_match('~^https?://~i', $url) && filter_var($url, FILTER_VALIDATE_URL));
    }
}
