<?php

namespace APP\core {
    class Application
    {
        public const ASSOC_TYPE_SUBMISSION = 1;
        public const ASSOC_TYPE_PUBLICATION = 2;
        public const ASSOC_TYPE_USER_ROLES = 3;
        public static $dao;
        public static function getRepresentationDAO() { return self::$dao; }
    }
}

namespace APP\submission {
    class Submission { public const STATUS_PUBLISHED = 3; }
}

namespace APP\log\event {
    class SubmissionEventLogEntry { public const SUBMISSION_LOG_PUBLICATION_FORMAT_CREATE = 268435474; }
}

namespace PKP\core {
    class Core { public static function getCurrentDate() { return '2026-01-01 00:00:00'; } }
    class PKPApplication { public const ASSOC_TYPE_SUBMISSION = 1; }
}

namespace PKP\security {
    class Role
    {
        public const ROLE_ID_SITE_ADMIN = 1;
        public const ROLE_ID_MANAGER = 16;
        public const ROLE_ID_SUB_EDITOR = 17;
        public const ROLE_ID_ASSISTANT = 18;
        public const ROLE_ID_AUTHOR = 19;
    }
}

namespace APP\facades {
    class Repo
    {
        public static $publications;
        public static $submissions;
        public static $events;
        public static function publication() { return self::$publications; }
        public static function submission() { return self::$submissions; }
        public static function eventLog() { return self::$events; }
    }
}

namespace {
    use APP\core\Application;
    use APP\facades\Repo;
    use APP\plugins\generic\langsciAdditionalFeatures\classes\features\defaultPublicationFormats\DefaultPublicationFormatsFeature as Feature;
    use Illuminate\Container\Container;
    use Illuminate\Database\Capsule\Manager;
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Facade;
    use PKP\plugins\Hook;
    use PKP\security\Role;
    use PKP\services\PKPSchemaService;

    function __($key, $params = []) { return $key . ':' . ($params['formatName'] ?? ''); }

    function checkFormats(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }

    $root = dirname(__DIR__, 4);
    require $root . '/lib/pkp/lib/vendor/autoload.php';
    define('PKP_STRICT_MODE', true);
    define('BASE_SYS_DIR', $root);
    require $root . '/lib/pkp/classes/plugins/Hook.php';
    require $root . '/lib/pkp/classes/services/PKPSchemaService.php';
    require $root . '/plugins/generic/langsciAdditionalFeatures/classes/features/Feature.php';
    require $root . '/plugins/generic/langsciAdditionalFeatures/classes/features/defaultPublicationFormats/DefaultPublicationFormatsFeature.php';

    $container = new Container();
    $capsule = new Manager($container);
    $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    $container->instance('db', $capsule->getDatabaseManager());
    Facade::setFacadeApplication($container);
    $db = $capsule->getConnection();
    $db->statement('CREATE TABLE publications (publication_id INTEGER PRIMARY KEY, submission_id INTEGER, status INTEGER, locale TEXT)');
    $db->statement("CREATE TABLE publication_settings (publication_id INTEGER, locale TEXT DEFAULT '', setting_name TEXT, setting_value TEXT, UNIQUE(publication_id, locale, setting_name))");
    $db->statement('CREATE TABLE formats (id INTEGER PRIMARY KEY AUTOINCREMENT, publication_id INTEGER, seq REAL, name TEXT, entry_key TEXT, physical INTEGER, width INTEGER, height INTEGER)');
    $db->statement('CREATE TABLE events (publication_format_name TEXT, user_id INTEGER)');
    $db->table('publications')->insert([
        ['publication_id' => 1, 'submission_id' => 10, 'status' => 1, 'locale' => 'en'],
        ['publication_id' => 2, 'submission_id' => 10, 'status' => 3, 'locale' => 'en'],
        ['publication_id' => 3, 'submission_id' => 10, 'status' => 1, 'locale' => 'en'],
        ['publication_id' => 4, 'submission_id' => 10, 'status' => 1, 'locale' => 'en'],
        ['publication_id' => 5, 'submission_id' => 10, 'status' => 1, 'locale' => 'en'],
    ]);

    Hook::add('Schema::get::publication', Feature::addToPublicationSchema(...));
    $schema = new PKPSchemaService();
    $props = [Feature::INITIALIZED => true, Feature::LEGACY_CREATED => '["PDF"]'];
    checkFormats($schema->sanitize('publication', $props) === $props, 'Markers must survive publication edits and version cloning');
    $rules = $schema->getValidationRules('publication', ['en'])[Feature::INITIALIZED];
    $translator = new \Illuminate\Translation\Translator(new \Illuminate\Translation\ArrayLoader(), 'en');
    $validator = new \Illuminate\Validation\Factory($translator);
    checkFormats($validator->make([Feature::INITIALIZED => true], [Feature::INITIALIZED => $rules])->fails(), 'API writes to the marker must be prohibited');
    checkFormats($validator->make([], [Feature::INITIALIZED => $rules])->passes(), 'Normal metadata edits must not require the marker');

    Repo::$publications = new class {
        public function get($id) {
            $row = DB::table('publications')->where('publication_id', $id)->first();
            if (!$row) { return null; }
            $settings = DB::table('publication_settings')->where('publication_id', $id)->pluck('setting_value', 'setting_name')->all();
            return new class ($row, $settings) {
                public function __construct(private $row, private $settings) {}
                public function getId() { return $this->row->publication_id; }
                public function getData($key) {
                    return match ($key) {
                        'submissionId' => $this->row->submission_id,
                        'locale' => $this->row->locale,
                        default => $this->settings[$key] ?? null,
                    };
                }
            };
        }
    };
    Repo::$submissions = new class {
        public bool $canEdit = true;
        public function canEditPublication($submissionId, $userId) { return $this->canEdit; }
    };
    Repo::$events = new class {
        public bool $fail = false;
        public function newDataObject($data) { return $data; }
        public function add($data) {
            if ($this->fail) { throw new \RuntimeException('Event failed'); }
            checkFormats($data['isTranslated'] && str_starts_with($data['message'], 'submission.event.publicationFormatCreated:'), 'The log message must retain the format name');
            DB::table('events')->insert(['publication_format_name' => $data['message'], 'user_id' => $data['userId']]);
        }
    };
    Application::$dao = new class {
        public function getByPublicationId($id) {
            return DB::table('formats')->where('publication_id', $id)->orderBy('seq')->get()->map(function ($row) {
                return new class ($row) {
                    public function __construct(private $row) {}
                    public function getName() { return json_decode($this->row->name, true); }
                    public function getSequence() { return $this->row->seq; }
                };
            })->all();
        }
        public function newDataObject() {
            return new class {
                public array $data = [];
                public function setData($key, $value) { $this->data[$key] = $value; }
                public function setSequence($value) { $this->data['seq'] = $value; }
                public function setName($value) { $this->data['name'] = $value; }
                public function getName() { return $this->data['name']; }
                public function setEntryKey($value) { $this->data['entryKey'] = $value; }
                public function setPhysicalFormat($value) { $this->data['physical'] = $value; }
                public function setWidth($value) { $this->data['width'] = $value; }
                public function setHeight($value) { $this->data['height'] = $value; }
            };
        }
        public function insertObject($format) {
            $d = $format->data;
            DB::table('formats')->insert([
                'publication_id' => $d['publicationId'], 'seq' => $d['seq'], 'name' => json_encode($d['name']),
                'entry_key' => $d['entryKey'], 'physical' => $d['physical'],
                'width' => $d['width'] ?? null, 'height' => $d['height'] ?? null,
            ]);
        }
    };
    $submission = new class {
        public function getId() { return 10; }
        public function getData($key) { return ['contextId' => 5, 'locale' => 'en'][$key] ?? null; }
    };
    $grid = new class ($submission) {
        public int $publicationId = 1;
        public array $roles = [Role::ROLE_ID_MANAGER];
        public function __construct(private $submission) {}
        public function getAuthorizedContextObject($type) {
            return match ($type) {
                Application::ASSOC_TYPE_SUBMISSION => $this->submission,
                Application::ASSOC_TYPE_PUBLICATION => Repo::publication()->get($this->publicationId),
                Application::ASSOC_TYPE_USER_ROLES => $this->roles,
            };
        }
    };
    $request = new class {
        public string $op = 'fetchGrid';
        public bool $get = true;
        public int $contextId = 5;
        public function isGet() { return $this->get; }
        public function getRouter() { return $this; }
        public function getRequestedOp($request) { return $this->op; }
        public function getUser() { return new class { public function getId() { return 8; } }; }
        public function getContext() { return new class ($this->contextId) {
            public function __construct(private $id) {}
            public function getId() { return $this->id; }
        }; }
    };
    $feature = (new \ReflectionClass(Feature::class))->newInstanceWithoutConstructor();
    $feature->register();
    $run = fn () => Hook::call('publicationformatgridhandler::initfeatures', [$grid, $request, null, []]);

    $db->table('formats')->insert(['publication_id' => 1, 'seq' => 8, 'name' => '{"de":"PDF"}', 'entry_key' => 'custom', 'physical' => 0]);
    $db->table('formats')->insert(['publication_id' => 1, 'seq' => 7, 'name' => '{"de":"Datasets"}', 'entry_key' => 'custom2', 'physical' => 0]);
    $grid->roles = [Role::ROLE_ID_AUTHOR];
    $run();
    checkFormats($db->table('formats')->count() === 2, 'Authors must not trigger initialization');
    $grid->roles = [Role::ROLE_ID_MANAGER];
    Repo::$submissions->canEdit = false;
    $run();
    checkFormats($db->table('formats')->count() === 2, 'Users without metadata permission must not initialize');
    Repo::$submissions->canEdit = true;
    $request->contextId = 6;
    $run();
    $request->contextId = 5;
    $request->op = 'updateFormat';
    $run();
    $request->op = 'fetchGrid';
    $request->get = false;
    $run();
    $request->get = true;
    checkFormats($db->table('formats')->count() === 2, 'Wrong press, operation or method must not initialize');

    $run();
    $formats = $db->table('formats')->where('publication_id', 1)->orderBy('seq')->get();
    checkFormats(count($formats) === 7 && $formats[0]->entry_key === 'custom2' && $formats[1]->entry_key === 'custom' && $formats[1]->seq == 8, 'Existing formats and ordering must be preserved');
    checkFormats($formats[2]->seq == 9 && json_decode($formats[2]->name, true)['en'] === 'Hardcover', 'Missing formats must be appended in default order');
    $hardcover = $formats->first(fn ($format) => $format->entry_key === 'BB');
    checkFormats($hardcover->physical == 1 && $hardcover->width == 180 && $hardcover->height == 245, 'Hardcover metadata must match legacy defaults');
    checkFormats($db->table('events')->count() === 5 && $db->table('events')->where('user_id', 8)->count() === 5, 'Only newly created formats must be logged');
    checkFormats($db->table('events')->where('publication_format_name', 'submission.event.publicationFormatCreated:Hardcover')->exists(), 'The creation event must name the format');
    $db->table('formats')->where('id', $hardcover->id)->delete();
    $run();
    checkFormats($db->table('formats')->where('publication_id', 1)->count() === 6, 'Deleted defaults must not be recreated');

    $grid->publicationId = 2;
    $run();
    checkFormats($db->table('formats')->where('publication_id', 2)->count() === 0, 'Published versions must not be changed');
    $db->table('publication_settings')->insert(['publication_id' => 3, 'setting_name' => Feature::LEGACY_CREATED, 'setting_value' => '["PDF"]']);
    $grid->publicationId = 3;
    $run();
    checkFormats($db->table('formats')->where('publication_id', 3)->count() === 0, 'Legacy initialized publications must not receive new defaults');
    checkFormats($db->table('publication_settings')->where('publication_id', 3)->where('setting_name', Feature::INITIALIZED)->exists(), 'Legacy state must be adopted');

    $grid->publicationId = 4;
    Repo::$events->fail = true;
    try {
        $feature->initializeGrid('', [$grid, $request]);
        throw new \RuntimeException('Expected logging failure');
    } catch (\RuntimeException $exception) {
        checkFormats($exception->getMessage() === 'Event failed', 'Failures must propagate');
    }
    checkFormats($db->table('formats')->where('publication_id', 4)->count() === 0, 'Failure must roll back format creation');
    checkFormats(!$db->table('publication_settings')->where('publication_id', 4)->exists(), 'Failure must not mark initialization complete');
    Repo::$events->fail = false;
    $run();
    checkFormats($db->table('formats')->where('publication_id', 4)->count() === 7, 'A failed initialization must be retryable');
    $defaults = $db->table('formats')->where('publication_id', 4)->orderBy('seq')->get()->mapWithKeys(
        fn ($format) => [json_decode($format->name, true)['en'] => $format->entry_key]
    )->all();
    checkFormats($defaults === [
        'Datasets' => 'EA', 'PDF' => 'DA', 'Hardcover' => 'BB',
        'Buy from Amazon.de' => 'BC', 'Buy from Amazon.co.uk' => 'BC', 'Buy from Amazon.com' => 'BC',
        'Collaborative reading on Paperhive' => 'DA',
    ], 'Defaults must retain their names, ordering and ONIX codes');

    $copied = $schema->sanitize('publication', [Feature::INITIALIZED => true]);
    $db->table('publication_settings')->insert(['publication_id' => 5, 'setting_name' => Feature::INITIALIZED, 'setting_value' => (int) $copied[Feature::INITIALIZED]]);
    $grid->publicationId = 5;
    $run();
    checkFormats($db->table('formats')->where('publication_id', 5)->count() === 0, 'An inherited initialization marker must prevent recreating deleted formats');

    echo "Default publication format checks passed\n";
}
