<?php

namespace APP\core {
    class Application
    {
        public const ASSOC_TYPE_SUBMISSION = 1;
    }
}

namespace APP\handler {
    class Handler
    {
        public array $roles = [];
        public array $policies = [];
        public $submission;
        public function __construct() {}
        public function addRoleAssignment($roles, $ops) { $this->roles = $roles; }
        public function addPolicy($policy) { $this->policies[] = $policy; }
        public function authorize($request, &$args, $roles) { return true; }
        public function getAuthorizedContextObject($type) { return $this->submission; }
    }
}

namespace APP\plugins\generic\langsciAdditionalFeatures {
    class LangsciAdditionalFeaturesPlugin
    {
        public bool $enabled = true;
        public function isFeatureEnabled($id, $context) { return $this->enabled; }
    }
}

namespace PKP\security {
    class Role
    {
        public const ROLE_ID_MANAGER = 16;
        public const ROLE_ID_SITE_ADMIN = 1;
    }
}

namespace PKP\security\authorization {
    class SubmissionAccessPolicy
    {
        public function __construct($request, $args, $roles) {}
    }
}

namespace {
    use APP\plugins\generic\langsciAdditionalFeatures\classes\features\reviews\ReviewsHandler;
    use APP\plugins\generic\langsciAdditionalFeatures\classes\features\reviews\ReviewsRepository;
    use APP\plugins\generic\langsciAdditionalFeatures\classes\features\reviews\ReviewsSchemaMigration;
    use APP\plugins\generic\langsciAdditionalFeatures\LangsciAdditionalFeaturesPlugin;
    use Illuminate\Container\Container;
    use Illuminate\Database\Capsule\Manager;
    use Illuminate\Support\Facades\Facade;

    function __($key) { return $key; }
    function check(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }

    $root = dirname(__DIR__, 4);
    require $root . '/lib/pkp/lib/vendor/autoload.php';
    define('PKP_STRICT_MODE', true);
    require $root . '/lib/pkp/classes/core/JSONMessage.php';
    require $root . '/plugins/generic/langsciAdditionalFeatures/classes/features/reviews/ReviewsRepository.php';
    require $root . '/plugins/generic/langsciAdditionalFeatures/classes/features/reviews/ReviewsSchemaMigration.php';
    require $root . '/plugins/generic/langsciAdditionalFeatures/classes/features/reviews/ReviewsHandler.php';

    $container = new Container();
    $capsule = new Manager($container);
    $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    $container->instance('db', $capsule->getDatabaseManager());
    $container->bind('db.schema', fn ($container) => $container['db']->connection()->getSchemaBuilder());
    Facade::setFacadeApplication($container);
    $connection = $capsule->getConnection();

    $migration = new ReviewsSchemaMigration();
    $migration->up();
    $migration->up();

    $plugin = new LangsciAdditionalFeaturesPlugin();
    $handler = new ReviewsHandler($plugin);
    check($handler->roles === [16, 1], 'Only manager and administrator roles should be assigned');
    $submission = new class {
        public int $id = 10;
        public ?int $publicationId = 20;
        public function getId() { return $this->id; }
        public function getCurrentPublication() {
            return $this->publicationId === null ? null : new class ($this->publicationId) {
                public function __construct(private int $id) {}
                public function getId() { return $this->id; }
            };
        }
    };
    $handler->submission = $submission;
    $request = new class {
        public array $vars = [];
        public bool $post = false;
        public bool $csrf = false;
        public function getContext() { return (object) ['id' => 1]; }
        public function getUserVar($name) { return $this->vars[$name] ?? null; }
        public function isPost() { return $this->post; }
        public function isGet() { return !$this->post; }
        public function checkCSRF() { return $this->csrf; }
    };
    $args = [];
    check($handler->authorize($request, $args, []) && count($handler->policies) === 1, 'Submission policy must be installed');
    $plugin->enabled = false;
    check(!$handler->authorize($request, $args, []), 'Disabled feature must reject access');
    $plugin->enabled = true;

    $request->vars = ['link_name' => 'Review', 'link' => 'https://example.test/review', 'reviewer' => 'Editor', 'date' => '20240229', 'money_quote' => 'Good'];
    check(!$handler->save([], $request)->getStatus(), 'GET must not mutate reviews');
    $request->post = true;
    check(!$handler->save([], $request)->getStatus(), 'Missing CSRF must reject writes');
    $request->csrf = true;
    check($handler->save([], $request)->getStatus(), 'Valid review should be created');
    $repo = new ReviewsRepository();
    $review = $repo->listForSubmission(10)->first();
    check($review->publication_id === 20, 'New review must use current publication');
    $id = $review->review_id;
    $migration->up();
    check($repo->find(10, $id)->link_name === 'Review', 'Adopting existing storage must preserve review IDs and content');
    $request->vars['reviewId'] = (string) $id;
    $request->vars['date'] = '20240230';
    check(!$handler->save([], $request)->getStatus(), 'Invalid changed date should fail');
    $request->vars['date'] = '20240229';
    $request->vars['link'] = 'javascript:alert(1)';
    check(!$handler->save([], $request)->getStatus(), 'Invalid changed URL should fail');
    $connection->table('langsci_review_links')->where('review_id', $id)->update(['link' => 'javascript:alert(1)', 'date' => '20240230']);
    $request->vars['date'] = '20240230';
    $request->vars['link_name'] = 'Updated';
    $submission->publicationId = 21;
    check($handler->save([], $request)->getStatus(), 'Unchanged legacy invalid values must be preserved');
    check($repo->find(10, $id)->publication_id === 20, 'Edit must preserve publication');
    $submission->id = 11;
    check(!$handler->save([], $request)->getStatus(), 'Cross-submission update must fail');
    check(!$handler->delete([], $request)->getStatus(), 'Cross-submission delete must fail');
    $submission->id = 10;
    $submission->publicationId = null;
    check($handler->fetch([], new class {
        public function isGet() { return true; }
    })->getStatus(), 'Listing must not require a current publication');
    check($handler->save([], $request)->getStatus(), 'Editing must not require a current publication');
    unset($request->vars['reviewId']);
    $request->vars['link'] = 'https://example.test/review';
    $request->vars['date'] = '20240229';
    $result = $handler->save([], $request);
    check(!$result->getStatus() && str_ends_with($result->getContent(), '.error.publication'), 'Creation must fail without a current publication');
    $request->vars['reviewId'] = (string) $id;
    check($handler->delete([], $request)->getStatus(), 'Delete should work without a current publication');
    check(!$handler->delete([], $request)->getStatus(), 'Deleting a missing review must fail');
    $submission->publicationId = 21;
    unset($request->vars['reviewId']);
    check($handler->save([], $request)->getStatus(), 'A later review can be created for the new current publication');
    check($repo->listForSubmission(10)->first()->publication_id === 21, 'New reviews must use the current publication at creation time');

    $connection->getSchemaBuilder()->drop('langsci_review_links');
    $connection->statement('CREATE TABLE langsci_review_links (review_id INTEGER PRIMARY KEY AUTOINCREMENT, submission_id INTEGER)');
    try {
        $migration->up();
        throw new \RuntimeException('Incompatible schema should fail');
    } catch (\RuntimeException $e) {
        check(str_contains($e->getMessage(), 'missing columns'), 'Incompatible schema must be reported');
    }
    echo "Reviews backend checks passed\n";
}
