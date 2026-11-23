<?php
// Run: php tests/copy_kris_kringle.php (requires pdo_sqlite).
// Exercise the production creation service/model with real SQL, without mail or S3.
error_reporting(E_ALL);
class Model {
    const RETURN_TYPE_ARRAY = 1;
    protected $s, $adb, $db;
    function __construct(Selmasu $s) { $this->s = $s; $this->adb = $this->db = $s->db; }
    function s() { return $this->s; }
    function tables() { return new TestTables(); }
    function coreTables() { return new TestTables(); }
}
class TestTables { function __call($name, $arguments) { return $name; } }
class Selmasu {
    function __construct(public TestDatabase $db, public int $id = 1) {}
    function userId() { return $this->id; }
}
class PictureUpload {
    static function save($s, $upload) { return null; }
    static function delete($s, $serial) {}
}
class ServiceAPI {
    function __construct(private Selmasu $s) {}
    function arguments($arguments) {}
}
class TestDatabase {
    public PDO $pdo;
    private $result;
    public $beforeTransaction = null;
    public ?string $failInsert = null;
    public bool $throwOnFailure = false;
    function __construct() {
        $this->pdo = class_exists('Pdo\\Sqlite') ? new \Pdo\Sqlite('sqlite::memory:') : new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if (method_exists($this->pdo, 'createFunction')) {
            $this->pdo->createFunction('NOW', fn () => '2026-11-20 12:00:00', 0);
        } else {
            $this->pdo->sqliteCreateFunction('NOW', fn () => '2026-11-20 12:00:00', 0);
        }
        $this->pdo->exec('CREATE TABLE krisKringle (krisKringleId INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, year INTEGER, createdBy INTEGER, createdDate TEXT, deleted INTEGER DEFAULT 0, pictureVersion TEXT, pictureFileSerial INTEGER DEFAULT 0);
            CREATE TABLE krisKringleGroup (groupId INTEGER PRIMARY KEY AUTOINCREMENT, krisKringleId INTEGER, name TEXT);
            CREATE TABLE krisKringleMember (krisKringleMemberId INTEGER PRIMARY KEY AUTOINCREMENT, krisKringleId INTEGER, groupId INTEGER, userId INTEGER, parentUserId INTEGER DEFAULT 0, name TEXT DEFAULT "");
            CREATE TABLE krisKringleAssignment (krisKringleId INTEGER, giverId INTEGER, recipientId INTEGER);
            CREATE TABLE user (userId INTEGER PRIMARY KEY, displayName TEXT, firstName TEXT, lastName TEXT, email TEXT, deleted INTEGER DEFAULT 0);');
        $year = (int)date('Y');
        $past = $year - 1;
        $this->pdo->exec("INSERT INTO krisKringle (krisKringleId, name, year, createdBy, pictureVersion, pictureFileSerial) VALUES (1, 'Previous Exchange', $past, 1, 'old-photo', 123), (2, 'Current Exchange', $year, 1, '', 0), (3, 'Private Exchange', $past, 3, '', 0);
            INSERT INTO krisKringleGroup VALUES (10, 1, 'Marketing'), (11, 1, 'Engineering'), (12, 1, 'Empty Group');
            INSERT INTO krisKringleMember VALUES (20, 1, 10, 1, 0, 'Robin'), (21, 1, 11, 2, 0, 'Alex'), (22, 1, 10, 0, 1, 'Child One'), (23, 1, 11, 0, 2, 'Child Two'), (24, 1, 10, 0, 1, 'Child Three');
            INSERT INTO krisKringleAssignment VALUES (1, 20, 21), (1, 21, 22), (1, 22, 20);
            INSERT INTO user VALUES (1, 'Robin Account', '', '', 'robin@example.test', 0), (2, 'Alex Account', '', '', 'alex@example.test', 0), (3, 'Private', '', '', 'private@example.test', 0);");
    }
    function query($sql) {
        if ($sql === 'START TRANSACTION' && $this->beforeTransaction) {
            ($this->beforeTransaction)($this->pdo);
            $this->beforeTransaction = null;
        }
        $sql = str_replace(['START TRANSACTION', ' FOR UPDATE'], ['BEGIN TRANSACTION', ''], $sql);
        $this->result = $this->pdo->query($sql);
        return $this;
    }
    function executePrepared($sql, $parameters) {
        if ($this->failInsert && str_contains($sql, $this->failInsert)) {
            if ($this->throwOnFailure) { throw new RuntimeException('Injected storage failure'); }
            return false;
        }
        return $this->pdo->prepare($sql)->execute($parameters);
    }
    function fetchAssoc() { return $this->result->fetch(PDO::FETCH_ASSOC) ?: null; }
    function fetchAll($sql) { return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC); }
    function insertId() { return $this->pdo->lastInsertId(); }
}
require __DIR__ . '/../apps/krisKringles/services/krisKringles/KrisKringlesModel.php';
require __DIR__ . '/../apps/krisKringles/services/krisKringles/KrisKringlesService.php';
require __DIR__ . '/../apps/krisKringles/services/krisKringles/KrisKringlesServiceAPI.php';
function check($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
function snapshot(TestDatabase $db): array {
    $rows = [];
    foreach (['krisKringle', 'krisKringleGroup', 'krisKringleMember', 'krisKringleAssignment', 'user'] as $table) {
        $rows[$table] = $db->fetchAll("SELECT * FROM $table ORDER BY rowid");
    }
    return $rows;
}
foreach ([1, 2] as $creator) {
    $db = new TestDatabase();
    $before = snapshot($db);
    $service = KrisKringleService::create(new Selmasu($db, $creator));
    $result = $service->createKrisKringle('New Exchange', '', null, 1);
    check(isset($result['data']), 'Owner and invited member can copy a visible previous exchange');
    $id = $result['data']['krisKringleId'];
    check($result['data']['year'] === (int)date('Y') && $result['data']['createdBy'] === $creator, 'New exchange uses current year and current creator');
    $groups = $db->fetchAll("SELECT groupId, name FROM krisKringleGroup WHERE krisKringleId = $id ORDER BY groupId");
    check(array_column($groups, 'name') === ['Marketing', 'Engineering', 'Empty Group'], 'All groups, including empty groups, are copied');
    check(min(array_column($groups, 'groupId')) > 12, 'Groups have new identifiers');
    $members = $db->fetchAll("SELECT m.*, g.name AS groupName FROM krisKringleMember m JOIN krisKringleGroup g ON g.groupId = m.groupId WHERE m.krisKringleId = $id ORDER BY m.krisKringleMemberId");
    check(count($members) === 5 && min(array_column($members, 'krisKringleMemberId')) > 24, 'All adults and children get new membership IDs');
    $adults = array_values(array_filter($members, fn ($m) => $m['userId'] > 0));
    check(count($adults) === 2 && count(array_filter($adults, fn ($m) => $m['userId'] === $creator)) === 1, 'Existing accounts reused; creator is included exactly once');
    $adultNames = array_column($adults, 'name', 'userId');
    check($adultNames[1] === 'Robin' && $adultNames[2] === 'Alex', 'Member display names are retained');
    foreach ($members as $member) {
        $expectedGroup = ($member['userId'] === 2 || $member['parentUserId'] === 2) ? 'Engineering' : 'Marketing';
        check($member['groupName'] === $expectedGroup, 'Adults and children stay in their original group');
    }
    $children = array_values(array_filter($members, fn ($m) => $m['userId'] === 0));
    usort($children, fn ($a, $b) => strcmp($a['name'], $b['name']));
    check(array_column($children, 'name') === ['Child One', 'Child Three', 'Child Two'] && array_column($children, 'parentUserId') === [1, 1, 2], 'Children retain names and their parent');
    check($db->fetchAll('SELECT * FROM krisKringleAssignment') === $before['krisKringleAssignment'], 'Gift assignments remain only in the source');
    check($db->fetchAll('SELECT * FROM krisKringle WHERE krisKringleId = 1') === [$before['krisKringle'][0]], 'Source exchange and its photo remain unchanged');
    check($db->fetchAll("SELECT pictureFileSerial FROM krisKringle WHERE krisKringleId = $id")[0]['pictureFileSerial'] === 0, 'Source photo is not copied');
    check($db->fetchAll('SELECT * FROM user') === $before['user'], 'No duplicate or altered user accounts');
}
foreach ([2, 3, 999, -1] as $source) {
    $db = new TestDatabase();
    $before = snapshot($db);
    $result = KrisKringleService::create(new Selmasu($db))->createKrisKringle('New Exchange', '', null, $source);
    check(isset($result['error']) && snapshot($db) === $before, 'Reject current-year, private, missing and negative source without changes');
}
foreach (['krisKringleGroup', 'krisKringleMember', 'parentUserId'] as $failure) {
    $db = new TestDatabase();
    $before = snapshot($db);
    $db->failInsert = $failure;
    $result = KrisKringleService::create(new Selmasu($db))->createKrisKringle('New Exchange', '', null, 1);
    check(isset($result['error']) && snapshot($db) === $before, 'Storage failure rolls back exchange, groups, adults and children');
}
$db = new TestDatabase();
$before = snapshot($db);
$db->failInsert = 'parentUserId';
$db->throwOnFailure = true;
try {
    KrisKringleService::create(new Selmasu($db))->createKrisKringle('New Exchange', '', null, 1);
    throw new LogicException('Expected injected exception');
} catch (RuntimeException $error) {
    check($error->getMessage() === 'Injected storage failure' && snapshot($db) === $before, 'Thrown storage failures roll back the transaction');
}
$db = new TestDatabase();
$db->beforeTransaction = fn ($pdo) => $pdo->exec('UPDATE krisKringle SET deleted = 1 WHERE krisKringleId = 1');
$result = KrisKringleService::create(new Selmasu($db))->createKrisKringle('New Exchange', '', null, 1);
check(isset($result['error']) && count($db->fetchAll('SELECT * FROM krisKringle')) === 3, 'Deleted source is rechecked under the transaction lock');
$db = new TestDatabase();
$service = KrisKringleService::create(new Selmasu($db));
check(isset($service->createKrisKringle('Fresh Exchange', '')['error']), 'Fresh exchange still requires first group');
$result = $service->createKrisKringle('Fresh Exchange', 'First Group');
$id = $result['data']['krisKringleId'];
check(count($db->fetchAll("SELECT * FROM krisKringleMember WHERE krisKringleId = $id")) === 1, 'Fresh creation still adds only the creator');
$_SERVER['REQUEST_METHOD'] = 'POST';
$api = new KrisKringleServiceAPI(new Selmasu(new TestDatabase()));
foreach (['invalid', -1, [], 1.5] as $invalid) {
    check(isset($api->api('createKrisKringle', ['name' => 'Test', 'copyFromId' => $invalid])['error']), 'API rejects invalid source identifiers');
}
check(isset($api->api('createKrisKringle', ['name' => 'Copied via API', 'copyFromId' => '1'])['data']), 'API forwards valid source without a first group');
echo "Previous-year copy checks passed.\n";
