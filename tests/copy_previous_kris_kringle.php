<?php
// Run: php tests/copy_previous_kris_kringle.php
// Uses an in-memory SQLite database; no accounts, photos, email or live API are accessed.

final class CopyTestDatabase {
    public PDO $pdo;
    public ?string $failNextChild = null;
    public array $queries = [];
    private $result;

    public function __construct() {
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('CREATE TABLE krisKringle (
            krisKringleId INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, year INTEGER,
            createdBy INTEGER, createdDate TEXT, deleted INTEGER DEFAULT 0,
            pictureVersion TEXT DEFAULT "", pictureFileSerial INTEGER DEFAULT 0)');
        $this->pdo->exec('CREATE TABLE krisKringleGroup (
            groupId INTEGER PRIMARY KEY AUTOINCREMENT, krisKringleId INTEGER, name TEXT)');
        $this->pdo->exec('CREATE TABLE krisKringleMember (
            krisKringleMemberId INTEGER PRIMARY KEY AUTOINCREMENT, krisKringleId INTEGER,
            groupId INTEGER, userId INTEGER, parentUserId INTEGER DEFAULT 0, name TEXT)');
        $this->pdo->exec('CREATE TABLE krisKringleAssignment (giverId INTEGER, recipientId INTEGER, krisKringleId INTEGER)');
        $this->pdo->exec('CREATE TABLE user (
            userId INTEGER PRIMARY KEY, displayName TEXT, firstName TEXT, lastName TEXT,
            email TEXT, deleted INTEGER DEFAULT 0)');
    }

    private function sqlite(string $sql): string {
        return str_ireplace(['START TRANSACTION', ' FOR UPDATE', 'NOW()'], ['BEGIN TRANSACTION', '', "datetime('now')"], $sql);
    }
    public function query(string $sql) {
        $this->queries[] = $sql;
        $this->result = $this->pdo->query($this->sqlite($sql));
        return $this;
    }
    public function fetchAssoc(string $sql = ''): ?array {
        if ($sql !== '') $this->query($sql);
        return $this->result->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    public function fetchValue(string $key, string $sql = '') {
        return $this->fetchAssoc($sql)[$key] ?? null;
    }
    public function fetchAll(string $sql): array {
        $this->query($sql);
        return $this->result->fetchAll(PDO::FETCH_ASSOC);
    }
    public function executePrepared(string $sql, array $parameters): bool {
        if ($this->failNextChild !== null && str_contains($sql, 'parentUserId')) {
            $mode = $this->failNextChild;
            $this->failNextChild = null;
            if ($mode === 'throw') throw new RuntimeException('Offline injected child insertion failure');
            return false;
        }
        return $this->pdo->prepare($this->sqlite($sql))->execute($parameters);
    }
    public function insertId(): int { return (int)$this->pdo->lastInsertId(); }
}

final class CopyTestTables {
    public function krisKringle(): string { return 'krisKringle'; }
    public function krisKringleGroup(): string { return 'krisKringleGroup'; }
    public function krisKringleMember(): string { return 'krisKringleMember'; }
    public function krisKringleAssignment(): string { return 'krisKringleAssignment'; }
    public function user(): string { return 'user'; }
}

final class Selmasu {
    public CopyTestDatabase $db;
    public int $currentUserId = 1;
    public function __construct() { $this->db = new CopyTestDatabase(); }
    public function userId(): int { return $this->currentUserId; }
}

class Model {
    const RETURN_TYPE_ARRAY = 1;
    protected $adb;
    protected $db;
    private Selmasu $app;
    public function __construct(Selmasu $s) { $this->app = $s; $this->db = $this->adb = $s->db; }
    protected function s(): Selmasu { return $this->app; }
    protected function tables(): CopyTestTables { return new CopyTestTables(); }
    protected function coreTables(): CopyTestTables { return new CopyTestTables(); }
}

class ServiceAPI {
    public function __construct(Selmasu $s) {}
    protected function arguments(array $arguments): void {}
}

final class PictureUpload {
    public static array $deleted = [];
    public static int $saved = 0;
    public static function save(Selmasu $s, ?array $upload): ?int {
        self::$saved++;
        return $upload['serial'] ?? null;
    }
    public static function delete(Selmasu $s, int $serial): void { self::$deleted[] = $serial; }
}

$app = dirname(__DIR__);
require $app . '/apps/krisKringles/services/krisKringles/KrisKringlesModel.php';
require $app . '/apps/krisKringles/services/krisKringles/KrisKringlesService.php';
require $app . '/apps/krisKringles/services/krisKringles/KrisKringlesServiceAPI.php';

$assertions = 0;
function check(bool $condition, string $message): void {
    global $assertions;
    if (!$condition) throw new RuntimeException($message);
    $assertions++;
}
function fixture(): Selmasu {
    $s = new Selmasu();
    $year = (int)date('Y');
    $s->db->pdo->exec("INSERT INTO user VALUES
        (1, 'Robin', '', '', 'robin@example.test', 0),
        (2, 'Ada', '', '', 'ada@example.test', 0),
        (3, 'Deleted', '', '', 'deleted@example.test', 1),
        (4, 'Other', '', '', 'other@example.test', 0)");
    $s->db->pdo->exec("INSERT INTO krisKringle VALUES
        (1, 'Last year', " . ($year - 1) . ", 1, '', 0, 'old-photo', 99),
        (2, 'Invited last year', " . ($year - 1) . ", 4, '', 0, '', 0),
        (3, 'This year', $year, 1, '', 0, '', 0),
        (4, 'Next year', " . ($year + 1) . ", 1, '', 0, '', 0),
        (5, 'Deleted exchange', " . ($year - 1) . ", 1, '', 1, '', 0)");
    $s->db->pdo->exec("INSERT INTO krisKringleGroup VALUES
        (10, 1, 'Family'), (20, 1, 'Friends'), (30, 1, 'Empty group'), (40, 2, 'Invited group')");
    $s->db->pdo->exec("INSERT INTO krisKringleMember VALUES
        (101, 1, 10, 1, 0, 'Mum'), (102, 1, 20, 2, 0, NULL),
        (103, 1, 10, 0, 1, 'Alice'), (104, 1, 20, 0, 2, 'Bob'),
        (105, 1, 20, 3, 0, 'Deleted adult'), (106, 1, 20, 0, 3, 'Deleted account child'),
        (107, 2, 40, 1, 0, 'Invited creator')");
    $s->db->pdo->exec('INSERT INTO krisKringleAssignment VALUES (101, 102, 1), (102, 101, 1)');
    return $s;
}
function snapshot(Selmasu $s): array {
    $result = [];
    foreach (['krisKringle', 'krisKringleGroup', 'krisKringleMember', 'krisKringleAssignment', 'user'] as $table) {
        $result[$table] = $s->db->fetchAll("SELECT * FROM $table ORDER BY 1");
    }
    return $result;
}

$s = fixture();
$before = snapshot($s);
$_SERVER['REQUEST_METHOD'] = 'POST';
$_FILES = [];
$result = (new KrisKringleServiceAPI($s))->api('createKrisKringle', ['name' => 'New KK', 'copyFromId' => '1']);
check(isset($result['data']['krisKringleId']), 'The existing create API accepts a previous year without a first group name');
$id = (int)$result['data']['krisKringleId'];
check($result['data']['year'] === (int)date('Y') && $result['data']['createdBy'] === 1, 'The new exchange uses this year and its creating account');
check($result['data']['pictureVersion'] === '', 'The old exchange photo is not implicitly reused');
$groups = $s->db->fetchAll("SELECT * FROM krisKringleGroup WHERE krisKringleId = $id ORDER BY groupId");
check(array_column($groups, 'name') === ['Family', 'Friends', 'Empty group'], 'Every source group, including empty groups, is copied');
check(!array_intersect(array_column($groups, 'groupId'), [10, 20, 30]), 'Copied groups receive fresh IDs');
$members = $s->db->fetchAll("SELECT * FROM krisKringleMember WHERE krisKringleId = $id ORDER BY krisKringleMemberId");
check(count($members) === 4, 'Active adults and their children are copied once, without deleted accounts');
check(count(array_filter($members, fn ($member) => (int)$member['userId'] === 1)) === 1, 'Copy does not add a duplicate creator');
check(!array_intersect(array_column($members, 'krisKringleMemberId'), [101, 102, 103, 104]), 'Copied members and children receive fresh IDs');
$byName = array_column($members, null, 'name');
check((int)$byName['Mum']['userId'] === 1 && (int)$byName['Ada']['userId'] === 2, 'Adult account identities and roster names are retained');
check((int)$byName['Alice']['parentUserId'] === 1 && (int)$byName['Bob']['parentUserId'] === 2, 'Children remain attached to their original parent accounts');
check($byName['Alice']['groupId'] === $byName['Mum']['groupId'] && $byName['Bob']['groupId'] === $byName['Ada']['groupId'], 'Children and adults map to the copied groups');
check($s->db->fetchAll("SELECT * FROM krisKringleAssignment WHERE krisKringleId = $id") === [], 'No previous allocations are copied');
$after = snapshot($s);
foreach ($before as $table => $rows) {
    check(array_slice($after[$table], 0, count($rows)) === $rows, "Source records in $table remain unchanged");
}
check((bool)array_filter($s->db->queries, fn ($sql) => str_contains($sql, 'FOR UPDATE')), 'Source access is rechecked while locked within the transaction');

foreach ([3, 4, 5, 999, -1] as $source) {
    $s = fixture();
    $before = snapshot($s);
    $result = KrisKringleService::create($s)->createKrisKringle('Rejected copy', '', null, $source);
    check(isset($result['error']), "Source $source is rejected when current, future, deleted, missing or invalid");
    check(snapshot($s) === $before, 'Rejected sources do not create partial exchanges');
}

$s = fixture();
$s->currentUserId = 2;
$result = KrisKringleService::create($s)->createKrisKringle('Invited copy', '', null, 1);
check(isset($result['data']) && $result['data']['createdBy'] === 2, 'An invited adult may copy a visible previous exchange');
$id = (int)$result['data']['krisKringleId'];
check(count($s->db->fetchAll("SELECT * FROM krisKringleMember WHERE krisKringleId = $id AND userId = 2")) === 1, 'An invited creator is included once');
check(count($s->db->fetchAll("SELECT * FROM krisKringleMember WHERE krisKringleId = $id AND userId = 0 AND parentUserId = 1")) === 1, 'Changing the creator preserves other adults’ parent relationships');

$s = fixture();
$s->currentUserId = 4;
$before = snapshot($s);
check(isset(KrisKringleService::create($s)->createKrisKringle('Outsider copy', '', null, 1)['error']), 'A caller outside the source cannot copy it');
check(snapshot($s) === $before, 'Denied outsider requests do not change stored data');

foreach (['false', 'throw'] as $mode) {
    $s = fixture();
    $before = snapshot($s);
    $s->db->failNextChild = $mode;
    PictureUpload::$deleted = [];
    $thrown = false;
    try {
        $result = KrisKringleService::create($s)->createKrisKringle('Failed copy', '', ['serial' => 200], 1);
        check($mode === 'false' && isset($result['error']), 'A failed child insert returns a readable error');
    } catch (RuntimeException $error) {
        $thrown = true;
        check($mode === 'throw' && $error->getMessage() === 'Offline injected child insertion failure', 'Thrown database failures retain their exception contract');
    }
    check($thrown === ($mode === 'throw'), 'Only thrown storage failures propagate');
    check(snapshot($s) === $before, 'A failed copy rolls back its exchange, groups, members and children');
    check(PictureUpload::$deleted === [200], 'A failed copy removes its newly uploaded photo');
}

$s = fixture();
$result = KrisKringleService::create($s)->createKrisKringle('Fresh KK', 'Fresh group');
$id = (int)$result['data']['krisKringleId'];
check(count($s->db->fetchAll("SELECT * FROM krisKringleGroup WHERE krisKringleId = $id")) === 1, 'Creating from scratch still adds the requested first group');
check(count($s->db->fetchAll("SELECT * FROM krisKringleMember WHERE krisKringleId = $id AND userId = 1")) === 1, 'Creating from scratch still adds the creator');

$s = fixture();
$s->currentUserId = 0;
$before = snapshot($s);
check(isset(KrisKringleService::create($s)->createKrisKringle('Anonymous copy', '', null, 1)['error']), 'Signed-out callers cannot copy');
check(snapshot($s) === $before, 'Signed-out requests do not change stored data');

$s = fixture();
$before = snapshot($s);
foreach (['wrong', -1, ['1']] as $source) {
    check(isset((new KrisKringleServiceAPI($s))->api('createKrisKringle', ['name' => 'Bad source', 'copyFromId' => $source])['error']), 'Malformed API source IDs are rejected');
}
check(snapshot($s) === $before, 'Malformed API requests leave no partial exchange');

echo "Passed $assertions copy-from-previous-year assertions.\n";
