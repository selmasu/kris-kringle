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

    protected function getMyGiftRecipientsFromDB(array $ids, int $userId): array
    {
        if (!$ids) {
            return [];
        }
        $ids = implode(',', array_map('intval', $ids));
        $assignments = $this->tables()->krisKringleAssignment();
        $members = $this->tables()->krisKringleMember();
        $rows = $this->adb->fetchAll("SELECT gift.krisKringleId, recipient.userId, recipient.name
            FROM $assignments AS gift
            JOIN $members AS giver ON giver.krisKringleMemberId = gift.giverId AND giver.krisKringleId = gift.krisKringleId
            JOIN $members AS recipient ON recipient.krisKringleMemberId = gift.recipientId AND recipient.krisKringleId = gift.krisKringleId
            WHERE gift.krisKringleId IN ($ids) AND giver.userId = $userId");
        if (!$rows) {
            return [];
        }
        $userIds = implode(',', array_unique(array_map('intval', array_column($rows, 'userId'))));
        $userTable = $this->coreTables()->user();
        $users = array_column($this->db->fetchAll("SELECT userId, displayName, firstName, lastName, email
            FROM $userTable WHERE userId IN ($userIds) AND deleted = 0"), null, 'userId');
        $recipients = [];
        foreach ($rows as $row) {
            $recipientUserId = (int)$row['userId'];
            if ($recipientUserId > 0 && !isset($users[$recipientUserId])) {
                continue;
            }
            $user = $users[$recipientUserId] ?? [];
            $name = trim($row['name'] ?? '');
            if ($name === '') {
                $name = trim($user['displayName'] ?? '');
            }
            if ($name === '') {
                $name = trim(($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? ''));
            }
            $recipients[(int)$row['krisKringleId']] = $name !== '' ? $name : ($user['email'] ?? 'Member');
        }
        return $recipients;
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
        $this->adb->query("SELECT groupId, name FROM $memberTable LIMIT 0");
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

    private function insertKrisKringleMember(int $id, int $groupId, int $userId, ?string $firstName = null): bool
    {
        $table = $this->tables()->krisKringleMember();
        return $this->adb->executePrepared(
            "INSERT INTO $table (krisKringleId, groupId, userId, name) VALUES (?, ?, ?, ?)",
            [$id, $groupId, $userId, $firstName]
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

    protected function getKrisKringleMembersFromDB(int $id, int $owner): array
    {
        $memberTable = $this->tables()->krisKringleMember();
        $memberRowsByUser = [];
        $childrenByParent = [];
        $rows = $this->adb->fetchAll("SELECT krisKringleMemberId, groupId, userId, parentUserId, name
            FROM $memberTable WHERE krisKringleId = $id ORDER BY krisKringleMemberId");
        foreach ($rows as $row) {
            $userId = (int)$row['userId'];
            $parentId = (int)$row['parentUserId'];
            if ($userId > 0) {
                $memberRowsByUser[$userId] = $row;
            } elseif ($parentId > 0) {
                $childrenByParent[$parentId][] = [
                    'memberId' => (int)$row['krisKringleMemberId'],
                    'name' => $row['name'],
                ];
            }
        }
        if (!$memberRowsByUser) {
            return [];
        }
        $ids = implode(',', array_keys($memberRowsByUser));
        $userTable = $this->coreTables()->user();
        $users = $this->db->fetchAll("SELECT userId, displayName, firstName, lastName, email
            FROM $userTable WHERE userId IN ($ids) AND deleted = 0
            ORDER BY (userId = $owner) DESC, displayName, firstName, userId");
        return array_map(static function (array $user) use ($owner, $childrenByParent, $memberRowsByUser) {
            $userId = (int)$user['userId'];
            $row = $memberRowsByUser[$userId];
            $name = trim($row['name'] ?? '');
            if ($name === '') {
                $name = trim($user['displayName'] ?? '');
            }
            if ($name === '') {
                $name = trim(($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? ''));
            }
            return [
                'userId' => $userId,
                'memberId' => (int)$row['krisKringleMemberId'],
                'groupId' => (int)$row['groupId'],
                'name' => $name,
                'email' => $user['email'],
                'isCreator' => $userId === $owner,
                'children' => $childrenByParent[$userId] ?? [],
            ];
        }, $users);
    }

    protected function resolveGiftGiverId(array $members, int $giverId, int $giverUserId): int
    {
        if ($giverId > 0) {
            return $giverId;
        }
        foreach ($members as $member) {
            if ($member['userId'] === $giverUserId) {
                return $member['memberId'];
            }
        }
        return 0;
    }

    protected function giftParticipants(array $members): array
    {
        $participants = [];
        foreach ($members as $member) {
            $participants[$member['memberId']] = [
                'groupId' => $member['groupId'],
                'accountId' => $member['userId'],
                'name' => $member['name'] !== '' ? $member['name'] : ($member['email'] ?? 'Member'),
            ];
            foreach ($member['children'] as $child) {
                $participants[$child['memberId']] = [
                    'groupId' => $member['groupId'],
                    'accountId' => $member['userId'],
                    'name' => $child['name'],
                ];
            }
        }
        return $participants;
    }

    protected function getGiftAssignmentsFromDB(int $id, array $participants): array
    {
        $table = $this->tables()->krisKringleAssignment();
        $assignments = [];
        foreach ($this->adb->fetchAll("SELECT giverId, recipientId FROM $table WHERE krisKringleId = $id") as $row) {
            $giverId = (int)$row['giverId'];
            $recipientId = (int)$row['recipientId'];
            if (isset($participants[$giverId], $participants[$recipientId])) {
                $assignments[$giverId] = $recipientId;
            }
        }
        return $assignments;
    }

    protected function eligibleGiftRecipients(array $participants, array $assignments, int $giverId): array
    {
        if (!isset($participants[$giverId]) || isset($assignments[$giverId])) {
            return [];
        }
        foreach ($assignments as $giver => $recipient) {
            if ($giver === $recipient || ($assignments[$recipient] ?? null) === $giver) {
                return [];
            }
        }

        $givers = array_diff_key($participants, $assignments);
        $available = array_diff_key($participants, array_flip($assignments));
        $minimum = $this->sameGroupLowerBound($givers, $available);
        $complete = false;
        while ($minimum <= count($givers)) {
            if ($this->canCompleteGiftAllocation($givers, $available, $assignments, $minimum)) {
                $complete = true;
                break;
            }
            ++$minimum;
        }
        if (!$complete) {
            return [];
        }

        unset($givers[$giverId]);
        $outside = [];
        $inside = [];
        foreach ($available as $recipientId => $recipient) {
            if ($recipientId === $giverId || ($assignments[$recipientId] ?? null) === $giverId) {
                continue;
            }
            $withinGroup = $participants[$giverId]['groupId'] === $recipient['groupId'];
            $remaining = $available;
            unset($remaining[$recipientId]);
            $nextAssignments = $assignments;
            $nextAssignments[$giverId] = $recipientId;
            if ($this->canCompleteGiftAllocation($givers, $remaining, $nextAssignments, $minimum - (int)$withinGroup)) {
                if ($withinGroup) {
                    $inside[] = $recipientId;
                } else {
                    $outside[] = $recipientId;
                }
            }
        }
        return $outside ?: $inside;
    }

    private function sameGroupLowerBound(array $givers, array $recipients): int
    {
        $counts = array_count_values(array_column($givers, 'groupId'));
        foreach ($recipients as $recipient) {
            $groupId = $recipient['groupId'];
            $counts[$groupId] = ($counts[$groupId] ?? 0) + 1;
        }
        return max(0, ($counts ? max($counts) : 0) - count($givers));
    }

    private function canCompleteGiftAllocation(
        array $givers, array $recipients, array $assignments, int $sameGroupBudget
    ): bool {
        if ($sameGroupBudget < $this->sameGroupLowerBound($givers, $recipients)) {
            return false;
        }
        if (!$givers) {
            return true;
        }

        // Choose the most constrained giver first to find dead ends early.
        $nextGiver = null;
        $nextOptions = [];
        $reachable = [];
        $forcedSameGroup = 0;
        foreach ($givers as $giverId => $giver) {
            $outside = [];
            $inside = [];
            foreach ($recipients as $recipientId => $recipient) {
                if ($giverId === $recipientId || ($assignments[$recipientId] ?? null) === $giverId) {
                    continue;
                }
                if ($giver['groupId'] === $recipient['groupId']) {
                    if ($sameGroupBudget > 0) {
                        $inside[] = $recipientId;
                    }
                } else {
                    $outside[] = $recipientId;
                }
            }
            $options = array_merge($outside, $inside);
            if (!$options) {
                return false;
            }
            $forcedSameGroup += (int)!$outside;
            foreach ($options as $recipientId) {
                $reachable[$recipientId] = true;
            }
            if ($nextGiver === null || count($options) < count($nextOptions)) {
                $nextGiver = $giverId;
                $nextOptions = $options;
            }
        }
        if ($forcedSameGroup > $sameGroupBudget || count($reachable) < count($givers)) {
            return false;
        }

        $groupId = $givers[$nextGiver]['groupId'];
        unset($givers[$nextGiver]);
        foreach ($nextOptions as $recipientId) {
            $nextRecipients = $recipients;
            unset($nextRecipients[$recipientId]);
            $assignments[$nextGiver] = $recipientId;
            $withinGroup = $groupId === $recipients[$recipientId]['groupId'];
            if ($this->canCompleteGiftAllocation(
                $givers, $nextRecipients, $assignments, $sameGroupBudget - (int)$withinGroup
            )) {
                return true;
            }
        }
        return false;
    }

    protected function updateGiftAssignmentInDB(
        int $id, int $owner, int $giverId, int $giverUserId, int $recipientId, int $expectedRecipientId
    ): ?string {
        $table = $this->tables()->krisKringleAssignment();
        // The framework may create this table on first use, before taking the exchange lock.
        $this->adb->query("SELECT giverId FROM $table LIMIT 0");
        $this->adb->query('START TRANSACTION');
        if (!$this->getOwnedKrisKringleFromDB($id, $owner, true)) {
            $this->adb->query('ROLLBACK');
            return 'Only the creator can allocate gifts.';
        }

        $members = $this->getKrisKringleMembersFromDB($id, $owner);
        $giverId = $this->resolveGiftGiverId($members, $giverId, $giverUserId);
        $participants = $this->giftParticipants($members);
        $assignments = $this->getGiftAssignmentsFromDB($id, $participants);
        $current = $assignments[$giverId] ?? 0;
        if ($recipientId > 0 && ($assignments[$recipientId] ?? null) === $giverId) {
            $this->adb->query('ROLLBACK');
            return 'These members cannot buy gifts for each other. Choose another recipient.';
        }
        if (isset($participants[$giverId]) && $current === $recipientId) {
            $this->adb->query('COMMIT');
            return null;
        }
        if (!isset($participants[$giverId]) || $current !== $expectedRecipientId
            || ($recipientId > 0 && !in_array($recipientId, $this->eligibleGiftRecipients($participants, $assignments, $giverId), true))) {
            $this->adb->query('ROLLBACK');
            return 'This assignment changed or the recipient is no longer available. Refresh the choices and try again.';
        }

        // A row involving an inactive participant may still hold a unique key.
        $saved = $this->adb->executePrepared(
            "DELETE FROM $table WHERE krisKringleId = ? AND (giverId = ? OR recipientId = ?)",
            [$id, $giverId, $recipientId]
        );
        if ($saved && $recipientId > 0) {
            $saved = $this->adb->executePrepared(
                "INSERT INTO $table (krisKringleId, giverId, recipientId) VALUES (?, ?, ?)",
                [$id, $giverId, $recipientId]
            );
        }
        $this->adb->query($saved ? 'COMMIT' : 'ROLLBACK');
        return $saved ? null : 'Couldn’t save this assignment. Please try again.';
    }

    protected function addKrisKringleMemberInDB(
        int $id, int $owner, int $groupId, int $userId, string $firstName
    ): ?string {
        $memberTable = $this->tables()->krisKringleMember();
        $this->adb->query("SELECT groupId, name FROM $memberTable LIMIT 0");
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
        if ($existingGroupId === 0) {
            $saved = $this->insertKrisKringleMember($id, $groupId, $userId, $firstName === '' ? null : $firstName);
        } else {
            $saved = $firstName === '' || $this->adb->executePrepared(
                "UPDATE $memberTable SET name = ? WHERE krisKringleId = ? AND groupId = ? AND userId = ?",
                [$firstName, $id, $groupId, $userId]
            );
        }
        $this->adb->query($saved ? 'COMMIT' : 'ROLLBACK');
        return $saved ? null : 'Couldn’t save this member. Please try again.';
    }

    protected function getEditableKrisKringleMemberFromDB(int $id, int $memberId, int $userId = 0): ?array
    {
        $table = $this->tables()->krisKringleMember();
        $condition = $memberId > 0 ? "krisKringleMemberId = $memberId" : "userId = $userId AND userId > 0";
        return $this->adb->query("SELECT krisKringleMemberId, userId, parentUserId, name
            FROM $table WHERE krisKringleId = $id AND $condition LIMIT 1")->fetchAssoc();
    }

    protected function updateKrisKringleMemberInDB(
        int $id, int $owner, int $memberId, int $expectedUserId, int $targetUserId, string $name
    ): ?string {
        $table = $this->tables()->krisKringleMember();
        $this->adb->query('START TRANSACTION');
        if (!$this->getOwnedKrisKringleFromDB($id, $owner, true)) {
            $this->adb->query('ROLLBACK');
            return 'Only the creator can edit members.';
        }
        $member = $this->getEditableKrisKringleMemberFromDB($id, $memberId);
        if (!$member) {
            $this->adb->query('ROLLBACK');
            return 'This member is no longer available.';
        }
        $currentUserId = (int)$member['userId'];
        if ($currentUserId === $targetUserId && (string)$member['name'] === $name) {
            $this->adb->query('COMMIT');
            return null;
        }
        if ($currentUserId !== $expectedUserId || ($currentUserId === $owner && $targetUserId !== $owner)) {
            $this->adb->query('ROLLBACK');
            return 'This member changed. Refresh the roster and try again.';
        }
        if ($targetUserId > 0 && $this->adb->query("SELECT krisKringleMemberId FROM $table
            WHERE krisKringleId = $id AND userId = $targetUserId AND krisKringleMemberId <> $memberId LIMIT 1")
            ->fetchValue('krisKringleMemberId')) {
            $this->adb->query('ROLLBACK');
            return 'That email address already belongs to another member in this Kris Kringle.';
        }

        $saved = $this->adb->executePrepared("UPDATE $table SET name = ?, userId = ?
            WHERE krisKringleId = ? AND krisKringleMemberId = ?", [$name, $targetUserId, $id, $memberId]);
        if ($saved && $currentUserId > 0 && $currentUserId !== $targetUserId) {
            $saved = $this->adb->executePrepared("UPDATE $table SET parentUserId = ?
                WHERE krisKringleId = ? AND userId = 0 AND parentUserId = ?", [$targetUserId, $id, $currentUserId]);
        }
        $this->adb->query($saved ? 'COMMIT' : 'ROLLBACK');
        return $saved ? null : 'Couldn’t save this member. Please try again.';
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
