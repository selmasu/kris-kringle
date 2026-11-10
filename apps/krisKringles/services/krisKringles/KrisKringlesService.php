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
        
        return ['data' => $this->getKrisKringlesFromDB( $query, $returnType ), 'total' => $this->getKrisKringlesTotalFromDB()];
    }

    public function createKrisKringle(string $name, ?array $upload = null):array
    {
        $userId = (int)$this->s()->userId();
        if ($userId <= 0) {
            return ['error' => 'Please sign in again.'];
        }

        $name = trim($name);
        if (preg_match('/\A[^\p{Cc}]{1,100}\z/u', $name) !== 1) {
            return ['error' => 'Enter a name between 1 and 100 characters.'];
        }

        try {
            $serial = PictureUpload::save($this->s(), $upload) ?? 0;
        } catch (InvalidArgumentException | RuntimeException $error) {
            return ['error' => $error->getMessage()];
        }

        $year = (int)date('Y');
        $version = $serial > 0 ? bin2hex(random_bytes(16)) : '';
        $id = $this->createKrisKringleInDB($name, $year, $userId, $serial, $version);
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
        return ['data' => $group, 'members' => $this->getKrisKringleMembersFromDB($id, $owner, $owner === $userId)];
    }

    public function addKrisKringleMember(int $id, string $email): array
    {
        $owner = (int)$this->s()->userId();
        if ($owner <= 0 || $id <= 0 || !$this->getOwnedKrisKringleFromDB($id, $owner)) {
            return ['error' => 'Only the creator can add members.'];
        }

        $email = strtolower(trim($email));
        if (strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['error' => 'Enter a valid email address.'];
        }
        $user = UsersService::create($this->s())->getOrCreateEmailAccount($email);
        $memberId = $user ? (int)$user->userId()->get() : 0;
        if ($memberId === $owner) {
            return ['error' => 'You’re already in this Kris Kringle.'];
        }
        if ($memberId <= 0 || !$this->addKrisKringleMemberInDB($id, $owner, $memberId)) {
            return ['error' => 'Couldn’t add this member. Please try again.'];
        }
        return ['members' => $this->getKrisKringleMembersFromDB($id, $owner, true)];
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