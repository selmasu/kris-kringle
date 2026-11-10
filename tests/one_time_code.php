<?php
// Run: php tests/one_time_code.php
// Override SELMASU_FRAMEWORK_PATH when the shared framework is installed elsewhere.
// SQLite executes the storage queries; mail delivery and session creation stay offline.

namespace {
    final class TestProperty {
        private $value;
        public function __construct(&$value) { $this->value =& $value; }
        public function get() { return $this->value ?? ''; }
        public function set($value) { $this->value = $value; return $this; }
        public function propertySet() { return $this->value !== null; }
        public function strReplace($from, $to) { $this->value = str_replace($from, $to, $this->get()); return $this; }
    }

    class User {
        private $values = [];
        public static function create() { return new self(); }
        public function __call($name, $arguments) { return new TestProperty($this->values[$name]); }
        public function fromArray($data) {
            foreach (['userId', 'email', 'firstName', 'lastName', 'displayName', 'admin', 'superUser', 'passwordLogin'] as $key) {
                if (array_key_exists($key, $data)) { $this->values[$key] = $data[$key]; }
            }
            return $this;
        }
        public function toArray() { return $this->values; }
        public function sqlSet() {
            $values = [];
            foreach ($this->values as $key => $value) {
                if ($value === null) { continue; }
                $values[] = $key . ' = ' . (is_bool($value) || is_int($value) ? (int)$value : "'" . str_replace("'", "''", $value) . "'");
            }
            return implode(', ', $values);
        }
    }

    class Email {
        private $values = [];
        public function __call($name, $arguments) { return new TestProperty($this->values[$name]); }
        protected function intProperty(&$value) { return new TestProperty($value); }
        protected function boolProperty(&$value) { return new TestProperty($value); }
        protected function stringProperty(&$value) { return new TestProperty($value); }
    }

    final class Settings { public static $mailgunDomain = 'example.test'; }

    final class EmailService {
        public static $messages = [];
        public static $succeeds = true;
        public static function create($s) { return new self(); }
        public function sendMail(Email $email) {
            self::$messages[] = [
                'to' => $email->toAddress()->get(),
                'html' => $email->htmlBody()->get(),
                'plain' => $email->plainBody()->get(),
                'subject' => $email->subject()->get(),
                'id' => $email->id()->get(),
            ];
            return self::$succeeds;
        }
    }

    final class TestDatabase {
        public $pdo;
        public $afterCodeRead;
        public $lockAvailable = true;
        private $result;
        public function __construct() {
            $this->pdo = new \PDO('sqlite::memory:');
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $this->pdo->exec("CREATE TABLE user (
                userId INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL COLLATE NOCASE,
                firstName TEXT DEFAULT '', lastName TEXT DEFAULT '', displayName TEXT DEFAULT '',
                admin INTEGER DEFAULT 0, superUser INTEGER DEFAULT 0, passwordLogin INTEGER DEFAULT 0,
                deleted INTEGER DEFAULT 0, profilePhotoId INTEGER DEFAULT 0, miniProfilePhotoId INTEGER DEFAULT 0,
                oneTimeCode TEXT DEFAULT '', oneTimeCodeExpiry TEXT, oneTimeCodeAttempts INTEGER DEFAULT 0,
                createdBy INTEGER, createdDate TEXT, updatedBy INTEGER, updatedDate TEXT)");
            $this->pdo->exec('CREATE TABLE file (fileSerial INTEGER PRIMARY KEY, location TEXT)');
        }
        public function clear() { return $this; }
        public function escapeString($value) { return str_replace("'", "''", (string)$value); }
        public function query($sql) {
            if (str_contains($sql, 'RELEASE_LOCK(')) { return $this; }
            $sql = preg_replace_callback('/DATE_ADD\(NOW\(\), INTERVAL (\d+) MINUTE\)/i', function ($m) {
                return "datetime('now', '+" . $m[1] . " minutes')";
            }, $sql);
            $sql = str_ireplace('NOW()', "datetime('now')", $sql);
            $sql = str_ireplace('ROW_COUNT()', 'changes()', $sql);
            if (preg_match('/^\s*INSERT INTO\s+(\w+)\s+SET\s+(.*)$/si', $sql, $m)) {
                preg_match_all("/(\w+)\s*=\s*('(?:''|[^'])*'|datetime\('now'\)|\d+)/", $m[2], $assignments);
                $sql = 'INSERT INTO ' . $m[1] . ' (' . implode(', ', $assignments[1]) . ') VALUES (' . implode(', ', $assignments[2]) . ')';
            }
            if (preg_match('/^\s*UPDATE/i', $sql)) { $sql = preg_replace('/\s+LIMIT\s+1\s*$/i', '', $sql); }
            $this->result = $this->pdo->query($sql);
            return $this;
        }
        public function fetchAssoc($sql = '') {
            if ($sql !== '') { $this->query($sql); }
            $row = $this->result->fetch(\PDO::FETCH_ASSOC);
            if (str_contains($sql, 'AS codeActive') && $this->afterCodeRead) {
                $callback = $this->afterCodeRead;
                $this->afterCodeRead = null;
                $callback($this);
            }
            return $row;
        }
        public function fetchValue($key, $sql = '') {
            if (str_contains($sql, 'GET_LOCK(')) { return $this->lockAvailable ? 1 : 0; }
            $row = $this->fetchAssoc($sql);
            return $row[$key] ?? null;
        }
        public function numRows($sql) { $this->query($sql); return count($this->result->fetchAll()); }
        public function insertId($sql) { $this->query($sql); return (int)$this->pdo->lastInsertId(); }
        public function user($email) { return $this->fetchAssoc("SELECT * FROM user WHERE email = '" . $this->escapeString($email) . "'"); }
    }

    final class TestTables {
        public function user() { return 'user'; }
        public function file() { return 'file'; }
    }

    final class TestSession {
        public $created = [];
        public function createUserSession($id, $remember) { $this->created[] = $id; return 'offline-token-' . $id; }
    }

    final class Selmasu {
        public $db;
        public $session;
        private $user;
        public function __construct() { $this->db = new TestDatabase(); $this->session = new TestSession(); $this->user = User::create(); }
        public function database() { return $this->db; }
        public function appDatabase() { return $this->db; }
        public function userId() { return (int)$this->user->userId()->get(); }
        public function user() { return $this->user; }
        public function setUser($user) { $this->user = $user; }
        public function loggedIn() { return $this->userId() > 0; }
        public function session() { return $this->session; }
    }

    class Model {
        protected $db;
        protected $adb;
        protected $s;
        public function __construct(Selmasu $s) { $this->s = $s; $this->db = $s->db; $this->adb = $s->db; }
        protected function s() { return $this->s; }
        public function coreTables() { return new TestTables(); }
    }

    class ServiceAPI {
        protected $s;
        private $arguments = [];
        public function __construct(Selmasu $s) { $this->s = $s; }
        protected function arguments($arguments) { $this->arguments = $arguments; }
        protected function stringArgument($key) { return (string)($this->arguments[$key] ?? ''); }
    }

    final class UserService {
        private $s;
        private function __construct($s) { $this->s = $s; }
        public static function create($s) { return new self($s); }
        public function getUser($id) {
            $row = $this->s->db->fetchAssoc('SELECT * FROM user WHERE userId = ' . (int)$id . ' AND deleted = 0');
            return $row ? User::create()->fromArray($row) : null;
        }
    }

    final class SelmasuError {
        public static function create($db = null) { return new self(); }
        public function setPermissionError() { return $this; }
        public function toArray() { return ['error' => 'permissionError']; }
    }

    final class EmailsService {
        public static $configured = true;
        public static function create($s) { return new self(); }
        public function getEmailObject($id) {
            if (!self::$configured) { return null; }
            $email = \KrisKringle\Email::create();
            $email->subject()->set('Your code');
            $email->htmlBody()->set('<p>Hello [[First Name]], your code is [[Access Code]].</p>');
            return $email;
        }
    }
}

namespace {
    $app = dirname(__DIR__);
    $framework = getenv('SELMASU_FRAMEWORK_PATH') ?: dirname($app) . '/framework/framework';
    require $app . '/core/valueObjects/Email.php';
    require $app . '/apps/users/services/users/UsersModel.php';
    require $app . '/apps/users/services/users/UsersService.php';
    require $app . '/apps/users/services/users/UsersServiceAPI.php';
    require $app . '/core/AppServiceAPI.php';
    require $framework . '/services/authentication/AuthenticationModel.php';
    require $framework . '/services/authentication/AuthenticationService.php';
    require $framework . '/core/RemoteService.php';

    $assertions = 0;
    function check($condition, $message) {
        global $assertions;
        if (!$condition) { throw new \RuntimeException($message); }
        $assertions++;
    }
    function send($s, $email) { return RemoteService::create($s)->callService('users', 'users', 'sendOneTimeCode', ['email' => $email]); }
    function login($s, $email, $code) { return RemoteService::create($s)->callService('framework', 'authentication', 'loginOneTimeCode', ['email' => $email, 'oneTimeCode' => $code]); }
    function seed($s, $email, $code = '012345', $expires = '+5 minutes', $attempts = 0) {
        $query = $s->db->pdo->prepare("INSERT INTO user (email, firstName, oneTimeCode, oneTimeCodeExpiry, oneTimeCodeAttempts) VALUES (?, 'Ada', ?, datetime('now', ?), ?)");
        $query->execute([$email, $code, $expires, $attempts]);
    }

    $s = new Selmasu();
    check(UsersServiceAPI::getIsAllowedPublicly('sendOneTimeCode'), 'Code requests must be publicly accessible');
    check(!UsersServiceAPI::getIsAllowedPublicly('deleteUsers'), 'Administrative methods must remain private');
    $result = send($s, '  New@Example.test  ');
    $row = $s->db->user('new@example.test');
    check($result['sent'] === true && $row, 'Requesting a code creates a new account');
    check($row['email'] === 'new@example.test', 'Email addresses are normalized');
    check(!$row['admin'] && !$row['superUser'] && !$row['passwordLogin'], 'New accounts have no administrative or password privileges');
    check(preg_match('/^[0-9]{6}$/', $row['oneTimeCode']) === 1, 'Codes contain six numeric characters');
    check(str_contains(EmailService::$messages[0]['html'], $row['oneTimeCode']), 'The requested code is included in the email');
    check(!isset($result['token']) && count($s->session->created) === 0, 'Sending a code never signs in');
    check(!isset($result['oneTimeCode']) && !isset($result['code']), 'The API never returns the code');
    $messageCount = count(EmailService::$messages);
    check(send($s, 'new@example.test')['sent'] === false, 'Immediate resends are throttled');
    check(count(EmailService::$messages) === $messageCount, 'Throttled requests do not send mail');
    check((int)$s->db->fetchValue('total', 'SELECT COUNT(*) AS total FROM user') === 1, 'Existing accounts are reused');

    $code = $row['oneTimeCode'];
    $signedIn = login($s, 'new@example.test', $code);
    check(isset($signedIn['token'], $signedIn['user']) && $signedIn['user']['userId'] == $row['userId'], 'Valid codes return the existing session/user contract');
    check(count($s->session->created) === 1, 'Valid codes create exactly one session');
    check(isset(login($s, 'new@example.test', $code)['error']), 'Codes cannot be replayed');
    check(count($s->session->created) === 1, 'Replay does not create a session');

    $s = new Selmasu();
    seed($s, 'existing@example.test', '123456', '-1 minute');
    $result = send($s, 'existing@example.test');
    check($result['sent'] === true, 'Existing accounts can request a new code');
    check((int)$s->db->fetchValue('total', 'SELECT COUNT(*) AS total FROM user') === 1, 'Existing-account requests do not create duplicates');
    check(str_contains(end(EmailService::$messages)['html'], 'Ada'), 'Configured email templates use the user object correctly');

    $s = new Selmasu();
    seed($s, 'expired@example.test', '123456', '-1 second');
    check(isset(login($s, 'expired@example.test', '123456')['error']), 'Even matching expired codes are rejected');
    check(count($s->session->created) === 0, 'Expired codes never create sessions');

    $s = new Selmasu();
    seed($s, 'locked@example.test');
    for ($attempt = 0; $attempt < 5; $attempt++) { check(isset(login($s, 'locked@example.test', '999999')['error']), 'Wrong codes must fail'); }
    check($s->db->user('locked@example.test')['oneTimeCodeAttempts'] == 5, 'Failed attempts are counted and capped');
    check(isset(login($s, 'locked@example.test', '012345')['error']), 'Five incorrect attempts invalidate even the correct code');
    check(count($s->session->created) === 0, 'Attempt limits cannot create sessions');

    $s = new Selmasu();
    seed($s, 'leading-zero@example.test', '000012', '+5 minutes', 4);
    check(isset(login($s, 'leading-zero@example.test', '000012')['token']), 'Leading zeroes and a valid fifth attempt work');

    $s = new Selmasu();
    seed($s, 'race@example.test', '123456');
    $s->db->afterCodeRead = function ($db) { $db->query("UPDATE user SET oneTimeCode = '' WHERE email = 'race@example.test'"); };
    check(isset(login($s, 'race@example.test', '123456')['error']), 'A concurrently consumed code cannot authenticate twice');

    $s = new Selmasu();
    seed($s, 'resend-race@example.test', '123456');
    $s->db->afterCodeRead = function ($db) { $db->query("UPDATE user SET oneTimeCode = '654321', oneTimeCodeExpiry = datetime('now', '+6 minutes') WHERE email = 'resend-race@example.test'"); };
    login($s, 'resend-race@example.test', '999999');
    $row = $s->db->user('resend-race@example.test');
    check($row['oneTimeCode'] === '654321' && $row['oneTimeCodeAttempts'] == 0, 'A stale failed attempt does not damage a newly issued code');

    $s = new Selmasu();
    seed($s, 'resend@example.test', '123456', '+239 seconds', 3);
    check(send($s, 'resend@example.test')['sent'] === true, 'Resending is allowed after the cooldown');
    check($s->db->user('resend@example.test')['oneTimeCodeAttempts'] == 0, 'Resending resets the attempt budget');
    check(end(EmailService::$messages)['id'] !== EmailService::$messages[count(EmailService::$messages) - 2]['id'], 'Each code email has a unique delivery ID');

    $s = new Selmasu();
    check(send($s, 'not an email')['sent'] === false, 'Invalid email addresses are rejected');
    check((int)$s->db->fetchValue('total', 'SELECT COUNT(*) AS total FROM user') === 0, 'Invalid email addresses do not create accounts');
    $s->db->lockAvailable = false;
    check(send($s, 'busy@example.test')['sent'] === false, 'Concurrent issuance lock failures stop account creation');
    check((int)$s->db->fetchValue('total', 'SELECT COUNT(*) AS total FROM user') === 0, 'Unavailable issuance locks do not create accounts');

    $s = new Selmasu();
    EmailService::$succeeds = false;
    check(send($s, 'delivery@example.test')['sent'] === false, 'Mail transport failures are reported');
    check($s->db->user('delivery@example.test')['oneTimeCode'] === '', 'Undelivered codes are invalidated');
    EmailService::$succeeds = true;

    $s = new Selmasu();
    EmailsService::$configured = false;
    check(send($s, 'fallback@example.test')['sent'] === true, 'A default email works before a custom template is configured');
    check(str_contains(end(EmailService::$messages)['subject'], 'Kris Kringle'), 'The default code email is branded');

    echo "PASS: " . $assertions . " email-code authentication assertions\n";
}
