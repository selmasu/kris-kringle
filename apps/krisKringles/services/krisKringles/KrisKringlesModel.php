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

    protected function createKrisKringleInDB(string $name, int $year, int $createdBy, int $serial, string $version):int
    {
        $table = $this->tables()->krisKringle();
        // Let the framework initialise the schema before using a prepared insert.
        $this->adb->query("SELECT pictureVersion, pictureFileSerial FROM $table LIMIT 0");
        $saved = $this->adb->executePrepared(
            "INSERT INTO $table (name, `year`, createdBy, createdDate, deleted, pictureVersion, pictureFileSerial)
                VALUES (?, ?, ?, NOW(), 0, ?, ?)",
            [$name, $year, $createdBy, $version, $serial]
        );
        return $saved ? (int)$this->adb->insertId() : 0;
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
        $userIds = $this->adb->fetchAllValues('userId', "SELECT userId FROM $memberTable WHERE krisKringleId = $id");
        $userIds[] = $owner;
        $ids = implode(',', array_map('intval', $userIds));
        $userTable = $this->coreTables()->user();
        $emailColumn = $showEmails ? ', email' : '';
        $users = $this->db->fetchAll("SELECT userId, displayName, firstName, lastName$emailColumn
            FROM $userTable WHERE userId IN ($ids) AND deleted = 0
            ORDER BY (userId = $owner) DESC, displayName, firstName, userId");
        return array_map(static function (array $user) use ($owner, $showEmails) {
            $name = trim($user['displayName'] ?? '');
            if ($name === '') {
                $name = trim(($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? ''));
            }
            $member = [
                'userId' => (int)$user['userId'],
                'name' => $name,
                'isCreator' => (int)$user['userId'] === $owner,
            ];
            if ($showEmails) {
                $member['email'] = $user['email'];
            }
            return $member;
        }, $users);
    }

    protected function addKrisKringleMemberInDB(int $id, int $owner, int $userId): bool
    {
        $memberTable = $this->tables()->krisKringleMember();
        $this->adb->query("SELECT userId FROM $memberTable LIMIT 0");
        $this->adb->query('START TRANSACTION');
        if (!$this->getOwnedKrisKringleFromDB($id, $owner, true)) {
            $this->adb->query('ROLLBACK');
            return false;
        }
        // Locking the group serializes duplicate additions, including on existing schemas.
        $exists = $this->adb->query("SELECT userId FROM $memberTable
            WHERE krisKringleId = $id AND userId = $userId LIMIT 1")->fetchValue('userId');
        if (!$exists && !$this->adb->executePrepared(
            "INSERT INTO $memberTable (krisKringleId, userId) VALUES (?, ?)",
            [$id, $userId]
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
