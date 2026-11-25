<?php
// Run: php tests/gift_recipient_highlights.php (requires pdo_sqlite).
// Runs the production list service and recipient SQL against an offline database.
final class HighlightProperty {
    public function __construct(public mixed $value = null) {}
    public function set($value) { $this->value = $value; return $this; }
    public function get() { return $this->value; }
}
final class ListArguments {
    public function take() { return new HighlightProperty(100); }
    public function skip() { return new HighlightProperty(0); }
}
final class SqlQueryBits {
    private HighlightProperty $order;
    public function __construct() { $this->order = new HighlightProperty(); }
    public static function create() { return new self(); }
    public function limit() { return new HighlightProperty(); }
    public function offset() { return new HighlightProperty(); }
    public function orderBy() { return $this->order; }
    public function orderByString() { return 'ORDER BY ' . $this->order->get(); }
    public function limitString() { return 'LIMIT 100'; }
}
final class KrisKringle {
    private array $data;
    public static function create() { return new self(); }
    public function fromArray($data) { $this->data = $data; return $this; }
    public function toArray() { return $this->data; }
}
final class HighlightTables { public function __call($name, $args) { return $name; } }
class Model {
    const RETURN_TYPE_OBJECT = 0;
    const RETURN_TYPE_ARRAY = 1;
    protected $db, $adb, $s;
    public function __construct(Selmasu $s) { $this->s = $s; $this->db = $this->adb = $s->db; }
    protected function s() { return $this->s; }
    public function tables() { return new HighlightTables(); }
    public function coreTables() { return new HighlightTables(); }
}
final class Selmasu {
    public function __construct(public HighlightDatabase $db, public int $id) {}
    public function userId() { return $this->id; }
}
final class HighlightDatabase {
    public PDO $pdo;
    public function __construct() {
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('CREATE TABLE krisKringle (krisKringleId INTEGER PRIMARY KEY, name TEXT, year INTEGER, createdBy INTEGER, deleted INTEGER DEFAULT 0, pictureVersion TEXT DEFAULT "");
            CREATE TABLE krisKringleMember (krisKringleMemberId INTEGER PRIMARY KEY, krisKringleId INTEGER, userId INTEGER, parentUserId INTEGER DEFAULT 0, name TEXT);
            CREATE TABLE krisKringleAssignment (krisKringleId INTEGER, giverId INTEGER PRIMARY KEY, recipientId INTEGER);
            CREATE TABLE user (userId INTEGER PRIMARY KEY, displayName TEXT, firstName TEXT, lastName TEXT, email TEXT, deleted INTEGER DEFAULT 0);
            INSERT INTO user VALUES (1, "Parent", "", "", "parent@example.test", 0), (2, "Alice Account", "", "", "alice@example.test", 0), (3, "", "", "", "other@example.test", 0), (4, "Private", "", "", "private@example.test", 0), (5, "Deleted", "", "", "deleted@example.test", 1);
            INSERT INTO krisKringleMember VALUES
                (101,1,1,0,"Parent"), (102,1,0,1,"Sam"), (103,1,0,1,"Sam"), (104,1,0,1,"Unassigned"), (105,1,2,0,""),
                (106,1,3,0,"Roster Name"), (107,1,0,3,"Other Parent’s Child"), (108,1,0,2,"Child Recipient"), (109,1,5,0,"Deleted"),
                (110,1,0,1,"Deleted Recipient"), (111,1,0,1,"Invalid Cross Exchange"),
                (201,2,1,0,"Parent"), (202,2,0,1,"Old Child"), (203,2,2,0,""),
                (301,3,4,0,"Private"), (302,3,0,4,"Private Child"), (303,3,2,0,""),
                (401,4,1,0,"Parent"), (501,5,1,0,"Parent"), (502,5,0,1,"Taylor"), (503,5,2,0,"");
            INSERT INTO krisKringleAssignment VALUES (1,101,105), (1,102,108), (1,103,106), (1,107,101), (1,110,109), (1,111,203),
                (2,201,202), (2,202,203), (3,302,303), (5,502,503);');
        $year = (int)date('Y');
        $past = $year - 1;
        $this->pdo->exec("INSERT INTO krisKringle (krisKringleId,name,year,createdBy) VALUES
            (1,'Current Exchange',$year,1), (2,'Past Exchange',$past,1), (3,'Private Exchange',$year,4),
            (4,'Unassigned Exchange',$year,1), (5,'Child Only Exchange',$year,1)");
    }
    public function fetchAll($sql) { return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC); }
    public function fetchValue($key, $sql) { return $this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC)[$key] ?? null; }
}
require __DIR__ . '/../apps/krisKringles/services/krisKringles/KrisKringlesModel.php';
require __DIR__ . '/../apps/krisKringles/services/krisKringles/KrisKringlesService.php';
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
$db = new HighlightDatabase();
$list = KrisKringleService::create(new Selmasu($db, 1))->getKrisKringles(new ListArguments());
$byId = array_column($list['data'], null, 'krisKringleId');
check(count($byId) === 4 && !isset($byId[3]), 'Only visible exchanges appear');
check($byId[1]['recipientName'] === 'Alice Account', 'Own card still uses the account display name fallback');
check($byId[1]['childGiftRecipients'] === [
    ['memberId' => 102, 'childName' => 'Sam', 'recipientName' => 'Child Recipient'],
    ['memberId' => 103, 'childName' => 'Sam', 'recipientName' => 'Roster Name'],
], 'Each assigned child is returned separately, with stable IDs even for repeated names');
check($byId[5]['recipientName'] === null && $byId[5]['childGiftRecipients'] === [
    ['memberId' => 502, 'childName' => 'Taylor', 'recipientName' => 'Alice Account'],
], 'Children are included when the parent has no gift assignment');
check($byId[4]['recipientName'] === null && $byId[4]['childGiftRecipients'] === [], 'Unassigned children and parents have no misleading card');
check($byId[2]['childGiftRecipients'][0]['childName'] === 'Old Child', 'Assignments remain attached to their own exchange/year');
$other = KrisKringleService::create(new Selmasu($db, 3))->getKrisKringles(new ListArguments())['data'];
check(count($other) === 1 && $other[0]['childGiftRecipients'] === [
    ['memberId' => 107, 'childName' => 'Other Parent’s Child', 'recipientName' => 'Parent'],
], 'A parent never receives another parent’s child assignment');
check($other[0]['recipientName'] === null, 'A parent’s own and child assignments remain separate');
check(isset(KrisKringleService::create(new Selmasu($db, 0))->getKrisKringles(new ListArguments())['error']), 'Signed-out requests are rejected');
echo "Gift recipient highlight checks passed.\n";
