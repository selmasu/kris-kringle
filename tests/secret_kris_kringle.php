<?php
// Run: php tests/secret_kris_kringle.php (requires pdo_sqlite).
// Real roster SQL and service permissions, with offline accounts and photos.
class Model {
    const RETURN_TYPE_ARRAY = 1;
    const RETURN_TYPE_OBJECT = 0;
    protected $s, $db, $adb;
    public function __construct(Selmasu $s) { $this->s=$s; $this->db=$this->adb=$s->db; }
    protected function s() { return $this->s; }
    public function tables() { return new SecretTables(); }
    public function coreTables() { return new SecretTables(); }
}
final class SecretTables { public function __call($name,$args) { return $name; } }
final class SecretProperty { public function __construct(private $value) {} public function get() { return $this->value; } }
final class SecretUser { public function selmasuToken() { return new SecretProperty('offline-token'); } }
final class Selmasu {
    public function __construct(public SecretDatabase $db, public int $id=1) {}
    public function userId() { return $this->id; }
    public function user() { return new SecretUser(); }
}
class ServiceAPI {
    private array $args=[];
    public function __construct(private Selmasu $s) {}
    protected function s() { return $this->s; }
    protected function arguments($args) { $this->args=$args; }
    protected function intArgument($name) { return (int)($this->args[$name]??0); }
}
final class PictureUpload {
    public static array $reads=[];
    public static function save($s,$upload) { return null; }
    public static function delete($s,$serial) {}
    public static function read($s,$serial) { self::$reads[]=$serial; return $serial>0 ? "photo:$serial" : null; }
}
final class SecretDatabase {
    public PDO $pdo;
    public bool $failUpdate=false;
    private $result;
    public function __construct() {
        $this->pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('CREATE TABLE krisKringle (krisKringleId INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT,year INTEGER,createdBy INTEGER,createdDate TEXT,deleted INTEGER DEFAULT 0,pictureVersion TEXT DEFAULT "",pictureFileSerial INTEGER DEFAULT 0,updatedBy INTEGER,updatedDate TEXT,secret INTEGER NOT NULL DEFAULT 0);
            CREATE TABLE krisKringleGroup (groupId INTEGER PRIMARY KEY AUTOINCREMENT,krisKringleId INTEGER,name TEXT);
            CREATE TABLE krisKringleMember (krisKringleMemberId INTEGER PRIMARY KEY AUTOINCREMENT,krisKringleId INTEGER,groupId INTEGER,userId INTEGER,parentUserId INTEGER DEFAULT 0,name TEXT);
            CREATE TABLE krisKringleAssignment (giverId INTEGER PRIMARY KEY,recipientId INTEGER,krisKringleId INTEGER);
            CREATE TABLE user (userId INTEGER PRIMARY KEY,displayName TEXT,firstName TEXT,lastName TEXT,email TEXT,deleted INTEGER DEFAULT 0,picture TEXT);
            CREATE TABLE krisKringleProfilePhoto (userId INTEGER PRIMARY KEY,pictureFileSerial INTEGER);
            INSERT INTO user VALUES (1,"Owner","","","owner@example.test",0,NULL),(2,"Parent","","","parent@example.test",0,NULL),(3,"Other Member","","","other@example.test",0,NULL),(4,"Outsider","","","outsider@example.test",0,NULL);
            INSERT INTO krisKringleGroup VALUES (10,1,"First Group"),(11,1,"Second Group");
            INSERT INTO krisKringleMember VALUES (101,1,10,1,0,"Owner"),(102,1,11,2,0,"Parent"),(103,1,11,0,2,"Abbie"),(104,1,10,3,0,"Other Member"),(105,1,10,0,1,"Owner Child");
            INSERT INTO krisKringleAssignment VALUES (101,104,1),(102,105,1),(103,101,1),(104,103,1);
            INSERT INTO krisKringleProfilePhoto VALUES (1,501),(2,502),(3,503);');
        $stmt=$this->pdo->prepare('INSERT INTO krisKringle (krisKringleId,name,year,createdBy) VALUES (1,?,?,1)');
        $stmt->execute(['Christmas',(int)date('Y')]);
    }
    private function sqlite($sql) { return str_replace(['START TRANSACTION',' FOR UPDATE','NOW()'],['BEGIN TRANSACTION','',"datetime('now')"],$sql); }
    public function query($sql) { $this->result=$this->pdo->query($this->sqlite($sql)); return $this; }
    public function fetchAssoc($sql='') { if ($sql!=='') $this->query($sql); return $this->result->fetch(PDO::FETCH_ASSOC)?:null; }
    public function fetchValue($key,$sql='') { return $this->fetchAssoc($sql)[$key]??null; }
    public function fetchAll($sql) { return $this->pdo->query($this->sqlite($sql))->fetchAll(PDO::FETCH_ASSOC); }
    public function executePrepared($sql,$args) { if ($this->failUpdate && str_starts_with($sql,'UPDATE krisKringle SET')) return false; return $this->pdo->prepare($this->sqlite($sql))->execute($args); }
    public function insertId() { return (int)$this->pdo->lastInsertId(); }
}
require __DIR__.'/../apps/krisKringles/services/krisKringles/KrisKringlesModel.php';
require __DIR__.'/../apps/krisKringles/services/krisKringles/KrisKringlesService.php';
require __DIR__.'/../apps/krisKringles/services/krisKringles/KrisKringlesServiceAPI.php';
function check($test,$message) { if (!$test) throw new RuntimeException($message); }
$db=new SecretDatabase();
$owner=KrisKringleService::create(new Selmasu($db,1));
$parent=KrisKringleService::create(new Selmasu($db,2));
$other=KrisKringleService::create(new Selmasu($db,3));
check(count($parent->getKrisKringle(1)['members'])===3,'Normal exchanges preserve the full read-only roster');
$_SERVER['REQUEST_METHOD']='POST';
$_POST=['id'=>'1','secret'=>'1','selmasuToken'=>'offline-token'];
$ownerApi=new KrisKringleServiceAPI(new Selmasu($db,1));
$parentApi=new KrisKringleServiceAPI(new Selmasu($db,2));
check(isset($parentApi->api('updateKrisKringle',$_POST)['error']),'Invited members cannot change secrecy');
$result=$ownerApi->api('updateKrisKringle',$_POST);
check($result['data']['secret']===true,'Creator can enable secrecy without a name or photo change');
$hidden=$parent->getKrisKringle(1);
check($hidden['data']['secret']===true && $hidden['members']===[] && $hidden['groups']===[] && $hidden['assignments']===[],'Secret API responses contain no roster or other gift assignments');
check($hidden['data']['recipientName']==='Owner Child','Parent sees their own recipient');
check($hidden['data']['childGiftRecipients']===[['memberId'=>103,'childName'=>'Abbie','recipientName'=>'Owner']],'Parent sees their child’s recipient');
check(!str_contains(json_encode($hidden),'Other Member') && !str_contains(json_encode($hidden),'other@example.test'),'Unrelated member names and email are absent from the response');
check(count($owner->getKrisKringle(1)['members'])===3 && count($owner->getKrisKringle(1)['assignments'])===4,'Creator retains all members and gift assignments');
$reads=count(PictureUpload::$reads);
check($parent->getKrisKringleMemberPicture(1,3)===null && count(PictureUpload::$reads)===$reads,'Other members’ photos cannot expose the secret roster');
check($parent->getKrisKringleMemberPicture(1,2)==='photo:502','Own photo remains accessible');
check($owner->getKrisKringleMemberPicture(1,3)==='photo:503','Creator retains access to member photos');
check(isset(KrisKringleService::create(new Selmasu($db,4))->getKrisKringle(1)['error']),'Outsiders cannot read the exchange');
$_POST['secret']='not-a-boolean';
check(isset($ownerApi->api('updateKrisKringle',$_POST)['error']),'Invalid Secret settings are rejected');
$_POST['secret']='0';
$db->failUpdate=true;
check(isset($ownerApi->api('updateKrisKringle',$_POST)['error']) && $parent->getKrisKringle(1)['data']['secret']===true,'Failed changes preserve the original setting');
$db->failUpdate=false;
check($ownerApi->api('updateKrisKringle',$_POST)['data']['secret']===false,'Creator can disable secrecy');
check(count($parent->getKrisKringle(1)['members'])===3,'Disabling secrecy restores the read-only roster');
check($parent->getKrisKringleMemberPicture(1,3)==='photo:503','Normal photo permissions are restored');
$fresh=$owner->createKrisKringle('Default Exchange','First Group');
check($fresh['data']['secret']===false,'New exchanges default to non-secret');
check(isset($ownerApi->api('createKrisKringle',['name'=>'Bad Secret','firstGroupName'=>'First Group','secret'=>['bad']])['error']),'Malformed create settings are rejected');
$secret=$ownerApi->api('createKrisKringle',['name'=>'Secret Exchange','firstGroupName'=>'First Group','secret'=>'1']);
check($secret['data']['secret']===true,'New exchanges can be secret');
$owner->updateKrisKringle(1,null,null,true);
$db->pdo->exec('UPDATE krisKringle SET year=year-1 WHERE krisKringleId=1');
$copy=$parent->createKrisKringle('Copy of Secret Exchange','',null,1);
check(isset($copy['data']),'Members can copy a secret previous exchange as requested');
$newId=$copy['data']['krisKringleId'];
check(count($db->fetchAll("SELECT * FROM krisKringleMember WHERE krisKringleId=$newId"))===5,'Copy includes groups, members and children');
check($db->fetchAll("SELECT * FROM krisKringleAssignment WHERE krisKringleId=$newId")===[],'Copy has no gift allocations');
check($copy['data']['secret']===false,'Copied exchange uses its own chosen Secret setting');
$db->pdo->exec('DELETE FROM krisKringleAssignment WHERE krisKringleId=1');
$unassigned=$parent->getKrisKringle(1);
check($unassigned['members']===[] && $unassigned['data']['recipientName']===null && $unassigned['data']['childGiftRecipients']===[],'Secret unassigned members see no roster or fabricated gifts');
echo "Secret Kris Kringle permission checks passed.\n";
