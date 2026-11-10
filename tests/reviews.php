<?php

/**
 * Integration check for the reviews provider and LangSci child-theme display.
 * Run from the OMP root: php plugins/generic/langsciAdditionalFeatures/tests/reviews.php
 */

$root = dirname(__DIR__, 4);
require $root . '/lib/pkp/lib/vendor/autoload.php';
define('PKP_STRICT_MODE', false);
require $root . '/lib/pkp/classes/plugins/Hook.php';
require $root . '/plugins/generic/langsciAdditionalFeatures/classes/features/Feature.php';
require $root . '/plugins/generic/langsciAdditionalFeatures/classes/features/reviews/ReviewsRepository.php';
require $root . '/plugins/generic/langsciAdditionalFeatures/classes/features/reviews/ReviewsFeature.php';

use APP\plugins\generic\langsciAdditionalFeatures\classes\features\reviews\ReviewsFeature;
use Gettext\Loader\PoLoader;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Support\Facades\Facade;

function checkReviewTest(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$container = new Container();
$capsule = new Manager($container);
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
$container->instance('db', $capsule->getDatabaseManager());
Facade::setFacadeApplication($container);
$db = $capsule->getConnection();
$db->statement('CREATE TABLE langsci_review_links (review_id INTEGER, submission_id INTEGER, publication_id INTEGER, link TEXT, link_name TEXT, reviewer TEXT, date TEXT, money_quote TEXT)');
$db->table('langsci_review_links')->insert([
    ['review_id' => 2, 'submission_id' => 1, 'link' => 'javascript:alert(1)', 'link_name' => 'Unsafe <review>', 'reviewer' => null, 'date' => '20240230', 'money_quote' => null],
    ['review_id' => 1, 'submission_id' => 1, 'link' => 'https://example.com/?a=1&b=2', 'link_name' => 'Good & review', 'reviewer' => '<Reviewer>', 'date' => '20240229', 'money_quote' => "<script>bad</script>\nGood"],
    ['review_id' => 3, 'submission_id' => 2, 'link' => 'https://other.example', 'link_name' => 'Other book', 'reviewer' => '', 'date' => '', 'money_quote' => ''],
]);

$templateManager = new class {
    public array $vars = [];
    public function getTemplateVars($name) { return $this->vars[$name] ?? null; }
    public function assign($name, $value) { $this->vars[$name] = $value; }
};
$feature = (new ReflectionClass(ReviewsFeature::class))->newInstanceWithoutConstructor();
$submission = new class {
    public int $id = 1;
    public function getId() { return $this->id; }
};
$templateManager->vars['publishedSubmission'] = $submission;
$feature->assignReviews('', [$templateManager, 'frontend/pages/catalog.tpl']);
checkReviewTest(!isset($templateManager->vars['reviewsBySubmission']), 'Catalog pages should not receive reviews');
$templateManager->vars['isChapterRequest'] = true;
$feature->assignReviews('', [$templateManager, 'frontend/pages/book.tpl']);
checkReviewTest(!isset($templateManager->vars['reviewsBySubmission']), 'Chapter pages should not receive reviews');
$templateManager->vars['isChapterRequest'] = false;
$feature->assignReviews('', [$templateManager, 'frontend/pages/book.tpl']);
$reviews = $templateManager->vars['reviewsBySubmission'];
checkReviewTest(count($reviews) === 2, 'Expected only reviews for this submission');
checkReviewTest($reviews[0]['name'] === 'Good & review' && $reviews[0]['date'] === '2024-02-29' && $reviews[0]['dateIsValid'], 'Valid review mapping failed');
checkReviewTest($reviews[0]['linkIsValid'] && !$reviews[1]['linkIsValid'], 'Review URL validation failed');
checkReviewTest($reviews[1]['date'] === '20240230' && !$reviews[1]['dateIsValid'], 'Invalid dates should remain visible');
$submission->id = 3;
$feature->assignReviews('', [$templateManager, 'frontend/pages/book.tpl']);
checkReviewTest($templateManager->vars['reviewsBySubmission'] === [], 'Expected an empty list for a book without reviews');

$compileDir = sys_get_temp_dir() . '/langsci-reviews-' . bin2hex(random_bytes(8));
if (!mkdir($compileDir)) {
    throw new RuntimeException('Could not create Smarty compilation directory');
}
try {
    $smarty = new Smarty();
    $smarty->setCompileDir($compileDir);
    $loader = new PoLoader();
    $translations = $loader->loadFile($root . '/plugins/themes/langsciDefaultChildTheme/locale/en/locale.po');
    $smarty->registerPlugin('function', 'translate', function ($params) use (&$translations) {
        $translation = $translations->find(null, $params['key']);
        checkReviewTest($translation !== null, 'Missing translation: ' . $params['key']);
        return strtr($translation->getTranslation(), [
            '{$reviewer}' => $params['reviewer'] ?? '',
            '{$date}' => $params['date'] ?? '',
        ]);
    });
    $template = $root . '/plugins/themes/langsciDefaultChildTheme/templates/frontend/objects/reviews.tpl';
    $smarty->assign('dateFormatLong', 'Y-m-d');
    $smarty->assign('reviewsBySubmission', []);
    checkReviewTest(trim($smarty->fetch($template)) === '', 'Empty reviews should not render');
    $smarty->assign('reviewsBySubmission', $reviews);
    $html = $smarty->fetch($template);
    checkReviewTest(substr_count($html, '<ul>') === 1 && substr_count($html, '<li>') === 2, 'Expected one list and two entries');
    checkReviewTest(str_contains($html, 'href="https://example.com/?a=1&amp;b=2"'), 'Valid URL should be escaped and linked');
    checkReviewTest(!str_contains($html, 'href="javascript:') && str_contains($html, 'Unsafe &lt;review&gt;'), 'Unsafe URL should render as text');
    checkReviewTest(str_contains($html, 'by &lt;Reviewer&gt;'), 'Reviewer should be escaped');
    checkReviewTest(str_contains($html, '&lt;script&gt;bad&lt;/script&gt;') && str_contains($html, '<br'), 'Quotation should be escaped with line breaks');
    checkReviewTest(str_contains($html, 'published 2024-02-29') && str_contains($html, 'published 20240230'), 'Dates should be formatted or displayed as supplied');
    $translations = $loader->loadFile($root . '/plugins/themes/langsciDefaultChildTheme/locale/de/locale.po');
    $html = $smarty->fetch($template);
    checkReviewTest(str_contains($html, 'Rezensionen') && str_contains($html, 'von &lt;Reviewer&gt;') && str_contains($html, 'veröffentlicht 2024-02-29'), 'German translations should render');
} finally {
    foreach (glob($compileDir . '/*') as $file) {
        unlink($file);
    }
    rmdir($compileDir);
}

echo "Review provider and theme display checks passed\n";
