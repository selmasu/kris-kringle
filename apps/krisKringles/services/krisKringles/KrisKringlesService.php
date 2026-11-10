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

    public function updateKrisKringlePicture(int $id, ?array $upload):array
    {
        $userId = (int)$this->s()->userId();
        if ($userId <= 0 || !$this->getOwnedKrisKringleFromDB($id, $userId)) {
            return ['error' => 'Only the creator can change the group photo.'];
        }

        try {
            $serial = PictureUpload::save($this->s(), $upload);
        } catch (InvalidArgumentException | RuntimeException $error) {
            return ['error' => $error->getMessage()];
        }
        if ($serial === null) {
            return ['error' => 'Choose a photo to upload.'];
        }

        $version = bin2hex(random_bytes(16));
        $group = $this->updateKrisKringlePictureInDB($id, $userId, $serial, $version);
        if ($group === null) {
            PictureUpload::delete($this->s(), $serial);
            return ['error' => 'Couldn’t update the group photo. Please try again.'];
        }
        PictureUpload::delete($this->s(), (int)$group['pictureFileSerial']);
        unset($group['pictureFileSerial']);
        $group['pictureVersion'] = $version;
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