<?php
class KrisKringleService extends KrisKringleModel {
    /**
     * @param ListArguments $listArguments
     * @param int $returnType
     * @return array
     */
    public function getKrisKringles( ListArguments $listArguments, int $returnType = Model::RETURN_TYPE_ARRAY ):array {
        if ($this->s()->userId() <= 0) {
            return ['error' => 'Please sign in again.'];
        }

        $query = SqlQueryBits::create();
        $query->limit()->set( max(0, (int)$listArguments->take()->get()) );
        $query->offset()->set( max(0, (int)$listArguments->skip()->get()) );
        $query->orderBy()->set( 'krisKringle.year DESC, krisKringle.krisKringleId DESC' );
        
        $groups = $this->getKrisKringlesFromDB($query, $returnType);
        if ($returnType === Model::RETURN_TYPE_ARRAY) {
            $recipients = $this->getMyGiftRecipientsFromDB(array_column($groups, 'krisKringleId'), (int)$this->s()->userId());
            foreach ($groups as &$group) {
                $group['recipientName'] = $recipients[(int)$group['krisKringleId']] ?? null;
            }
            unset($group);
        }
        return ['data' => $groups, 'total' => $this->getKrisKringlesTotalFromDB()];
    }

    public function createKrisKringle(string $name, string $firstGroupName, ?array $upload = null, int $copyFromId = 0):array
    {
        $userId = (int)$this->s()->userId();
        if ($userId <= 0) {
            return ['error' => 'Please sign in again.'];
        }

        $name = trim($name);
        if (preg_match('/\A[^\p{Cc}]{1,100}\z/u', $name) !== 1) {
            return ['error' => 'Enter a name between 1 and 100 characters.'];
        }

        $firstGroupName = trim($firstGroupName);
        if ($copyFromId === 0 && preg_match('/\A[^\p{Cc}]{1,100}\z/u', $firstGroupName) !== 1) {
            return ['error' => 'Enter a first group name between 1 and 100 characters.'];
        }

        $year = (int)date('Y');
        if ($copyFromId < 0 || ($copyFromId > 0 && !$this->getCopySourceFromDB($copyFromId, $year))) {
            return ['error' => 'Choose an available Kris Kringle from a previous year.'];
        }

        try {
            $serial = PictureUpload::save($this->s(), $upload) ?? 0;
        } catch (InvalidArgumentException | RuntimeException $error) {
            return ['error' => $error->getMessage()];
        }

        $version = $serial > 0 ? bin2hex(random_bytes(16)) : '';
        try {
            $id = $this->createKrisKringleInDB($name, $firstGroupName, $year, $userId, $serial, $version, $copyFromId);
        } catch (Throwable $error) {
            PictureUpload::delete($this->s(), $serial);
            throw $error;
        }
        if ($id <= 0) {
            PictureUpload::delete($this->s(), $serial);
            return ['error' => 'Couldn’t create your Kris Kringle. Please try again.'];
        }

        return ['data' => [
            'krisKringleId' => $id,
            'name' => $name,
            'year' => $year,
            'createdBy' => $userId,
            'pictureVersion' => $version
        ]];
    }

    public function getKrisKringle(int $id): array
    {
        $userId = (int)$this->s()->userId();
        $group = $userId > 0 && $id > 0 ? $this->getVisibleKrisKringleFromDB($id) : null;
        if (!$group) {
            return ['error' => 'This Kris Kringle is no longer available.'];
        }
        $owner = (int)$group['createdBy'];
        return ['data' => $group] + $this->roster($id, $owner, $userId);
    }

    public function addKrisKringleGroup(int $id, string $name): array
    {
        $owner = (int)$this->s()->userId();
        if ($owner <= 0 || $id <= 0 || !$this->getOwnedKrisKringleFromDB($id, $owner)) {
            return ['error' => 'Only the creator can add groups.'];
        }
        $name = trim($name);
        if (preg_match('/\A[^\p{Cc}]{1,100}\z/u', $name) !== 1) {
            return ['error' => 'Enter a group name between 1 and 100 characters.'];
        }
        $groupId = $this->addKrisKringleGroupInDB($id, $owner, $name);
        if ($groupId <= 0) {
            return ['error' => 'Couldn’t add this group. Please try again.'];
        }
        return ['groupId' => $groupId] + $this->roster($id, $owner, $owner);
    }

    public function addKrisKringleMember(int $id, int $groupId, string $email, string $firstName = ''): array
    {
        $owner = (int)$this->s()->userId();
        if ($owner <= 0 || $id <= 0 || !$this->getOwnedKrisKringleFromDB($id, $owner)) {
            return ['error' => 'Only the creator can add members.'];
        }

        $email = strtolower(trim($email));
        if (strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['error' => 'Enter a valid email address.'];
        }
        if ($groupId <= 0 || !$this->hasKrisKringleGroupInDB($id, $groupId)) {
            return ['error' => 'Choose a group in this Kris Kringle.'];
        }
        $firstName = trim($firstName);
        if (preg_match('/\A[^\p{Cc}]{0,100}\z/u', $firstName) !== 1) {
            return ['error' => 'Enter a first name up to 100 characters.'];
        }

        $user = UsersService::create($this->s())->getOrCreateEmailAccount($email);
        $memberId = $user ? (int)$user->userId()->get() : 0;
        if ($memberId === $owner) {
            return ['error' => 'You’re already in this Kris Kringle.'];
        }
        if ($memberId <= 0) {
            return ['error' => 'Couldn’t add this member. Please try again.'];
        }
        $error = $this->addKrisKringleMemberInDB($id, $owner, $groupId, $memberId, $firstName);
        return $error === null ? $this->roster($id, $owner, $owner) : ['error' => $error];
    }

    public function updateKrisKringleMember(
        int $id, int $memberId, int $userId, int $expectedUserId, string $name, string $email
    ): array {
        $owner = (int)$this->s()->userId();
        if ($owner <= 0 || $id <= 0 || !$this->getOwnedKrisKringleFromDB($id, $owner)) {
            return ['error' => 'Only the creator can edit members.'];
        }
        $member = $this->getEditableKrisKringleMemberFromDB($id, $memberId, $userId);
        if (!$member || ((int)$member['userId'] === 0) !== ($expectedUserId === 0)) {
            return ['error' => 'This member is no longer available. Refresh the roster and try again.'];
        }
        $name = trim($name);
        $child = $expectedUserId === 0;
        if (preg_match($child ? '/\A[^\p{Cc}]{1,100}\z/u' : '/\A[^\p{Cc}]{0,100}\z/u', $name) !== 1) {
            return ['error' => $child ? 'Enter a child’s name between 1 and 100 characters.' : 'Enter a first name up to 100 characters.'];
        }

        $targetUserId = 0;
        if (!$child) {
            $email = strtolower(trim($email));
            if (strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['error' => 'Enter a valid email address.'];
            }
            $currentUserId = (int)$member['userId'];
            $userTable = $this->coreTables()->user();
            $currentEmail = (string)$this->db->query("SELECT email FROM $userTable WHERE userId = $currentUserId AND deleted = 0")
                ->fetchValue('email');
            if ($email === strtolower(trim($currentEmail))) {
                $targetUserId = $currentUserId;
            } else {
                if ($currentUserId === $owner) {
                    return ['error' => 'The creator’s sign-in email cannot be changed from the member list.'];
                }
                try {
                    $error = UsersService::create($this->s())->withNewEmailAccount(
                        $email,
                        fn (User $user): ?string => $this->updateKrisKringleMemberInDB(
                            $id, $owner, (int)$member['krisKringleMemberId'], $expectedUserId, (int)$user->userId()->get(), $name
                        )
                    );
                } catch (DomainException $error) {
                    return ['error' => $error->getMessage()];
                }
                return $error === null ? $this->roster($id, $owner, $owner) : ['error' => $error];
            }
        }
        $error = $this->updateKrisKringleMemberInDB(
            $id, $owner, (int)$member['krisKringleMemberId'], $expectedUserId, $targetUserId, $name
        );
        return $error === null ? $this->roster($id, $owner, $owner) : ['error' => $error];
    }

    public function addKrisKringleChild(int $id, int $parentId, string $name): array
    {
        $owner = (int)$this->s()->userId();
        if ($owner <= 0 || $id <= 0 || !$this->getOwnedKrisKringleFromDB($id, $owner)) {
            return ['error' => 'Only the creator can add children.'];
        }

        $name = trim($name);
        if (preg_match('/\A[^\p{Cc}]{1,100}\z/u', $name) !== 1) {
            return ['error' => 'Enter a child’s name between 1 and 100 characters.'];
        }
        if ($parentId <= 0 || !$this->addKrisKringleChildInDB($id, $owner, $parentId, $name)) {
            return ['error' => 'Couldn’t add this child. Choose a parent who is a member of this Kris Kringle and try again.'];
        }
        return $this->roster($id, $owner, $owner);
    }

    public function getGiftRecipientOptions(int $id, int $giverId, int $giverUserId): array
    {
        $owner = (int)$this->s()->userId();
        if ($owner <= 0 || $id <= 0 || !$this->getOwnedKrisKringleFromDB($id, $owner)) {
            return ['error' => 'Only the creator can allocate gifts.'];
        }
        $members = $this->getKrisKringleMembersFromDB($id, $owner);
        $giverId = $this->resolveGiftGiverId($members, $giverId, $giverUserId);
        $participants = $this->giftParticipants($members);
        $exchange = $this->getOwnedKrisKringleFromDB($id, $owner);
        $excluded = $this->getPastGiftMatchesFromDB((int)$exchange['year'], $participants);
        if (!isset($participants[$giverId])) {
            return ['error' => 'Choose a member of this Kris Kringle.'];
        }
        $assignments = $this->getGiftAssignmentsFromDB($id, $participants);
        $options = [];
        foreach ($this->eligibleGiftRecipients($participants, $assignments, $giverId, $excluded) as $recipientId) {
            $options[] = [
                'memberId' => $recipientId,
                'name' => $participants[$recipientId]['name'],
                'groupId' => $participants[$recipientId]['groupId'],
            ];
        }
        $recipientId = $assignments[$giverId] ?? null;
        return [
            'options' => $options,
            'recipientId' => $recipientId,
            'recipientName' => $recipientId === null ? null : $participants[$recipientId]['name'],
        ];
    }

    public function updateGiftAssignment(
        int $id, int $giverId, int $giverUserId, int $recipientId, int $expectedRecipientId
    ): array {
        $owner = (int)$this->s()->userId();
        if ($owner <= 0 || $id <= 0 || !$this->getOwnedKrisKringleFromDB($id, $owner)) {
            return ['error' => 'Only the creator can allocate gifts.'];
        }
        $error = $this->updateGiftAssignmentInDB($id, $owner, $giverId, $giverUserId, $recipientId, $expectedRecipientId);
        return $error === null ? $this->roster($id, $owner, $owner) : ['error' => $error];
    }

    public function updateAllGiftRecipients(int $id, bool $clear): array
    {
        $owner = (int)$this->s()->userId();
        if ($owner <= 0 || $id <= 0) {
            return ['error' => 'Only the creator can set or clear gift recipients.'];
        }
        $error = $this->updateAllGiftRecipientsInDB($id, $owner, $clear);
        return $error === null ? $this->roster($id, $owner, $owner) : ['error' => $error];
    }

    private function roster(int $id, int $owner, int $viewer): array
    {
        $members = $this->getKrisKringleMembersFromDB($id, $owner);
        $participants = $this->giftParticipants($members);
        $assignments = [];
        foreach ($this->getGiftAssignmentsFromDB($id, $participants) as $giverId => $recipientId) {
            $assignments[] = [
                'giverId' => $giverId,
                'recipientId' => $recipientId,
                'recipientName' => $participants[$recipientId]['name'],
            ];
        }
        $photos = $this->getMemberPhotoDetailsFromDB(array_column($members, 'userId'));
        foreach ($members as &$member) {
            $photo = $photos[$member['userId']] ?? [];
            $serial = (int)($photo['pictureFileSerial'] ?? 0);
            $member['pictureVersion'] = $serial > 0 ? (string)$serial : '';
            $member['pictureURL'] = $serial > 0 ? null : ($photo['picture'] ?? null);
            if ($viewer !== $owner) {
                unset($member['email']);
            }
        }
        unset($member);
        return ['groups' => $this->getKrisKringleGroupsFromDB($id), 'members' => $members, 'assignments' => $assignments];
    }

    public function updateKrisKringlePicture(int $id, ?array $upload): array
    {
        return $this->updateKrisKringle($id, null, $upload);
    }

    public function updateKrisKringle(int $id, ?string $name, ?array $upload): array
    {
        $userId = (int)$this->s()->userId();
        if ($userId <= 0 || $id <= 0 || !$this->getOwnedKrisKringleFromDB($id, $userId)) {
            return ['error' => 'Only the creator can edit this Kris Kringle.'];
        }
        if ($name !== null) {
            $name = trim($name);
            if (preg_match('/\A[^\p{Cc}]{1,100}\z/u', $name) !== 1) {
                return ['error' => 'Enter a name between 1 and 100 characters.'];
            }
        }

        try {
            $serial = PictureUpload::save($this->s(), $upload);
        } catch (InvalidArgumentException | RuntimeException $error) {
            return ['error' => $error->getMessage()];
        }
        if ($name === null && $serial === null) {
            return ['error' => 'Choose a photo to upload.'];
        }

        $version = $serial === null ? null : bin2hex(random_bytes(16));
        $group = $this->updateKrisKringleInDB($id, $userId, $name, $serial, $version);
        if ($group === null) {
            PictureUpload::delete($this->s(), $serial ?? 0);
            return ['error' => 'Couldn’t update this Kris Kringle. Please try again.'];
        }
        if ($serial !== null) {
            PictureUpload::delete($this->s(), (int)$group['pictureFileSerial']);
            $group['pictureVersion'] = $version;
        }
        unset($group['pictureFileSerial']);
        $group['name'] = $name ?? $group['name'];
        return ['data' => $group];
    }

    public function getKrisKringleMemberPicture(int $id, int $userId): ?string
    {
        if ($this->s()->userId() <= 0 || $id <= 0 || $userId <= 0 || !$this->getVisibleKrisKringleFromDB($id)) {
            return null;
        }
        return PictureUpload::read($this->s(), $this->getMemberPhotoSerialFromDB($id, $userId));
    }

    public function getKrisKringlePicture(int $id): ?string
    {
        if ($this->s()->userId() <= 0 || $id <= 0) {
            return null;
        }
        return PictureUpload::read($this->s(), $this->getVisibleKrisKringlePictureFromDB($id));
    }

    /**
	 * @param Selmasu $s
	 * @return KrisKringleService
	 */
	public static function create( Selmasu $s ):KrisKringleService{
		return new self( $s );
	}

}
