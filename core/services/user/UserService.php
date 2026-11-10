<?php
/**
 * Legacy service called by framework
 * Class UserService
 */
class UserService extends UsersService {

    public function appleSignInPolicy(): array
    {
        return [
            'nativeClientId' => 'com.selmasu.Kris-Kringle',
            'allowAccountCreation' => false,
        ];
    }

    public function getUserByAppleSubject(string $subject): ?User
    {
        $table = $this->tables()->krisKringleAppleIdentity();
        $hash = hash('sha256', $subject);
        $userId = (int)$this->db->query("SELECT userId FROM $table WHERE subjectHash = '$hash'")->fetchValue('userId');
        return $userId > 0 ? $this->getUserFromDB($userId) : null;
    }

    public function hasAppleLink(User $user): bool
    {
        return $this->appleSubjectHashForUser($user->userId()->get()) !== null;
    }

    public function saveAppleLink(string $subject): bool
    {
        $userId = $this->s()->userId();
        if ($userId <= 0) {
            return false;
        }
        $table = $this->tables()->krisKringleAppleIdentity();
        $hash = hash('sha256', $subject);
        $userTable = $this->coreTables()->user();
        $this->db->query("INSERT INTO $table (userId, subjectHash)
            SELECT account.userId, '$hash' FROM $userTable AS account WHERE account.userId = $userId AND account.deleted = 0
            ON DUPLICATE KEY UPDATE userId = $table.userId");
        return $this->appleSubjectHashForUser($userId) === $hash;
    }

    /**
     * @param Selmasu $s
     * @return UserService
     */
    public static function create(Selmasu $s): UsersService
    {
        return new self($s);
    }

}