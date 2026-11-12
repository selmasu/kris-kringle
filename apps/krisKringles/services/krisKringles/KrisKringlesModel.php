<?php
class KrisKringleModel extends Model
{
    protected function getKrisKringlesFromDB(SqlQueryBits $query, int $returnType = Model::RETURN_TYPE_ARRAY):array
    {
        $sql = "SELECT
                    krisKringle.krisKringleId,
                    krisKringle.name,
                    krisKringle.year,
                    krisKringle.createdBy,
                    krisKringle.pictureVersion "
            . $this->visibleGroupsFromWhere() . "
            " . $query->orderByString() . "
            " . $query->limitString();

        return array_map(function ($row) use ($returnType) {
            $group = KrisKringle::create()->fromArray($row);
            return $returnType === Model::RETURN_TYPE_OBJECT ? $group : $group->toArray();
        }, $this->adb->fetchAll($sql));
    }

    protected function getKrisKringlesTotalFromDB():int
    {
        return (int)$this->adb->fetchValue('total', 'SELECT COUNT(*) AS total ' . $this->visibleGroupsFromWhere());
    }

    private function visibleGroupsFromWhere():string
    {
        $userId = (int)$this->s()->userId();
        return "FROM " . $this->tables()->krisKringle() . " AS krisKringle
            WHERE krisKringle.deleted = 0
              AND (krisKringle.createdBy = " . $userId . " OR EXISTS (
                    SELECT 1 FROM " . $this->tables()->krisKringleMember() . " AS member
                    WHERE member.krisKringleId = krisKringle.krisKringleId AND member.userId = " . $userId . "
                ))";
    }

    protected function createKrisKringleInDB(
        string $name, string $firstGroupName, int $year, int $createdBy, int $serial, string $version
    ): int {
        $table = $this->tables()->krisKringle();
        $groupTable = $this->tables()->krisKringleGroup();
        $memberTable = $this->tables()->krisKringleMember();
        // Schema initialization must finish before the transaction starts.
        $this->adb->query("SELECT pictureVersion, pictureFileSerial FROM $table LIMIT 0");
        $this->adb->query("SELECT groupId FROM $groupTable LIMIT 0");
        $this->adb->query("SELECT groupId FROM $memberTable LIMIT 0");
        $this->adb->query('START TRANSACTION');
        $saved = $this->adb->executePrepared(
            "INSERT INTO $table (name, `year`, createdBy, createdDate, deleted, pictureVersion, pictureFileSerial)
                VALUES (?, ?, ?, NOW(), 0, ?, ?)",
            [$name, $year, $createdBy, $version, $serial]
        );
        $id = $saved ? (int)$this->adb->insertId() : 0;
        $groupId = $id > 0 ? $this->insertKrisKringleGroup($id, $firstGroupName) : 0;
        if ($groupId <= 0 || !$this->insertKrisKringleMember($id, $groupId, $createdBy)) {
            $this->adb->query('ROLLBACK');
            return 0;
        }
        $this->adb->query('COMMIT');
        return $id;
    }

    protected function getKrisKringleGroupsFromDB(int $id): array
    {
        $table = $this->tables()->krisKringleGroup();
        $groups = $this->adb->fetchAll("SELECT groupId, name FROM $table WHERE krisKringleId = $id ORDER BY groupId");
        return array_map(static fn (array $group): array => [
            'groupId' => (int)$group['groupId'],
            'name' => $group['name'],
        ], $groups);
    }

    protected function hasKrisKringleGroupInDB(int $id, int $groupId): bool
    {
        $table = $this->tables()->krisKringleGroup();
        return (bool)$this->adb->query("SELECT groupId FROM $table WHERE krisKringleId = $id AND groupId = $groupId")
            ->fetchValue('groupId');
    }

    protected function addKrisKringleGroupInDB(int $id, int $owner, string $name): int
    {
        $table = $this->tables()->krisKringleGroup();
        $this->adb->query("SELECT groupId FROM $table LIMIT 0");
        $this->adb->query('START TRANSACTION');
        if (!$this->getOwnedKrisKringleFromDB($id, $owner, true)) {
            $this->adb->query('ROLLBACK');
            return 0;
        }
        $escapedName = $this->adb->escapeString($name);
        $groupId = (int)$this->adb->query("SELECT groupId FROM $table
            WHERE krisKringleId = $id AND LOWER(name) = LOWER('$escapedName') LIMIT 1")->fetchValue('groupId');
        if ($groupId <= 0) {
            $groupId = $this->insertKrisKringleGroup($id, $name);
        }
        $this->adb->query($groupId > 0 ? 'COMMIT' : 'ROLLBACK');
        return $groupId;
    }

    private function insertKrisKringleGroup(int $id, string $name): int
    {
        $table = $this->tables()->krisKringleGroup();
        $saved = $this->adb->executePrepared("INSERT INTO $table (krisKringleId, name) VALUES (?, ?)", [$id, $name]);
        return $saved ? (int)$this->adb->insertId() : 0;
    }

    private function insertKrisKringleMember(int $id, int $groupId, int $userId): bool
    {
        $table = $this->tables()->krisKringleMember();
        return $this->adb->executePrepared(
            "INSERT INTO $table (krisKringleId, groupId, userId) VALUES (?, ?, ?)",
            [$id, $groupId, $userId]
        );
    }

    private function getKrisKringleMemberGroupId(int $id, int $userId): int
    {
        $table = $this->tables()->krisKringleMember();
        return (int)$this->adb->query("SELECT groupId FROM $table WHERE krisKringleId = $id AND userId = $userId LIMIT 1")
            ->fetchValue('groupId');
    }

    protected function getOwnedKrisKringleFromDB(int $id, int $owner, bool $lock = false): ?array
    {
        $table = $this->tables()->krisKringle();
        $lockClause = $lock ? ' FOR UPDATE' : '';
        return $this->adb->query("SELECT krisKringleId, name, `year`, createdBy, pictureVersion, pictureFileSerial
            FROM $table WHERE krisKringleId = $id AND createdBy = $owner AND deleted = 0$lockClause")->fetchAssoc();
    }

    protected function getVisibleKrisKringleFromDB(int $id): ?array
    {
        return $this->adb->query("SELECT krisKringle.krisKringleId, krisKringle.name, krisKringle.year,
            krisKringle.createdBy, krisKringle.pictureVersion " . $this->visibleGroupsFromWhere()
            . " AND krisKringle.krisKringleId = $id")->fetchAssoc();
    }

    protected function getKrisKringleMembersFromDB(int $id, int $owner, bool $showEmails): array
    {
        $memberTable = $this->tables()->krisKringleMember();
        $groupIdsByUser = [];
        $childrenByParent = [];
        $rows = $this->adb->fetchAll("SELECT krisKringleMemberId, groupId, userId, parentUserId, name
            FROM $memberTable WHERE krisKringleId = $id ORDER BY krisKringleMemberId");
        foreach ($rows as $row) {
            $userId = (int)$row['userId'];
            $parentId = (int)$row['parentUserId'];
            if ($userId > 0) {
                $groupIdsByUser[$userId] = (int)$row['groupId'];
            } elseif ($parentId > 0) {
                $childrenByParent[$parentId][] = [
                    'memberId' => (int)$row['krisKringleMemberId'],
                    'name' => $row['name'],
                ];
            }
        }
        if (!$groupIdsByUser) {
            return [];
        }
        $ids = implode(',', array_keys($groupIdsByUser));
        $userTable = $this->coreTables()->user();
        $emailColumn = $showEmails ? ', email' : '';
        $users = $this->db->fetchAll("SELECT userId, displayName, firstName, lastName$emailColumn
            FROM $userTable WHERE userId IN ($ids) AND deleted = 0
            ORDER BY (userId = $owner) DESC, displayName, firstName, userId");
        return array_map(static function (array $user) use ($owner, $showEmails, $childrenByParent, $groupIdsByUser) {
            $userId = (int)$user['userId'];
            $name = trim($user['displayName'] ?? '');
            if ($name === '') {
                $name = trim(($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? ''));
            }
            $member = [
                'userId' => $userId,
                'groupId' => $groupIdsByUser[$userId],
                'name' => $name,
                'isCreator' => $userId === $owner,
                'children' => $childrenByParent[$userId] ?? [],
            ];
            if ($showEmails) {
                $member['email'] = $user['email'];
            }
            return $member;
        }, $users);
    }

    protected function addKrisKringleMemberInDB(int $id, int $owner, int $groupId, int $userId): ?string
    {
        $memberTable = $this->tables()->krisKringleMember();
        $this->adb->query("SELECT groupId FROM $memberTable LIMIT 0");
        $this->adb->query('START TRANSACTION');
        if (!$this->getOwnedKrisKringleFromDB($id, $owner, true) || !$this->hasKrisKringleGroupInDB($id, $groupId)) {
            $this->adb->query('ROLLBACK');
            return 'Choose a group in this Kris Kringle.';
        }

        $existingGroupId = $this->getKrisKringleMemberGroupId($id, $userId);
        if ($existingGroupId > 0 && $existingGroupId !== $groupId) {
            $this->adb->query('ROLLBACK');
            return 'This member is already in another group in this Kris Kringle.';
        }
        if ($existingGroupId === 0 && !$this->insertKrisKringleMember($id, $groupId, $userId)) {
            $this->adb->query('ROLLBACK');
            return 'Couldn’t add this member. Please try again.';
        }
        $this->adb->query('COMMIT');
        return null;
    }

    protected function addKrisKringleChildInDB(int $id, int $owner, int $parentId, string $name): bool
    {
        $memberTable = $this->tables()->krisKringleMember();
        $this->adb->query("SELECT groupId, parentUserId, name FROM $memberTable LIMIT 0");
        $this->adb->query('START TRANSACTION');
        if (!$this->getOwnedKrisKringleFromDB($id, $owner, true)) {
            $this->adb->query('ROLLBACK');
            return false;
        }

        $groupId = $this->getKrisKringleMemberGroupId($id, $parentId);
        $userTable = $this->coreTables()->user();
        if ($groupId <= 0 || !$this->db->query("SELECT userId FROM $userTable
            WHERE userId = $parentId AND deleted = 0")->fetchValue('userId')) {
            $this->adb->query('ROLLBACK');
            return false;
        }

        // Keep an exact name retry under the same parent idempotent.
        $nameHex = strtoupper(bin2hex($name));
        $existing = $this->adb->query("SELECT krisKringleMemberId FROM $memberTable
            WHERE krisKringleId = $id AND userId = 0 AND parentUserId = $parentId
            AND HEX(name) = '$nameHex' LIMIT 1")->fetchValue('krisKringleMemberId');
        if (!$existing && !$this->adb->executePrepared(
            "INSERT INTO $memberTable (krisKringleId, groupId, userId, parentUserId, name) VALUES (?, ?, 0, ?, ?)",
            [$id, $groupId, $parentId, $name]
        )) {
            $this->adb->query('ROLLBACK');
            return false;
        }
        $this->adb->query('COMMIT');
        return true;
    }

    protected function updateKrisKringleInDB(int $id, int $owner, ?string $name, ?int $serial, ?string $version): ?array
    {
        $table = $this->tables()->krisKringle();
        $this->adb->query('START TRANSACTION');
        $group = $this->getOwnedKrisKringleFromDB($id, $owner, true);
        if (!$group || !$this->adb->executePrepared(
            "UPDATE $table SET name = COALESCE(?, name), pictureFileSerial = COALESCE(?, pictureFileSerial),
                pictureVersion = COALESCE(?, pictureVersion), updatedBy = ?, updatedDate = NOW()
                WHERE krisKringleId = ?",
            [$name, $serial, $version, $owner, $id]
        )) {
            $this->adb->query('ROLLBACK');
            return null;
        }
        $this->adb->query('COMMIT');
        return $group;
    }

    protected function getVisibleKrisKringlePictureFromDB(int $id): int
    {
        return (int)$this->adb->query('SELECT krisKringle.pictureFileSerial '
            . $this->visibleGroupsFromWhere() . ' AND krisKringle.krisKringleId = ' . $id)->fetchValue('pictureFileSerial');
    }
}
