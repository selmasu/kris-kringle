<?php
class KrisKringleService extends KrisKringleModel {
    private const MAX_PICTURE_BYTES = 512 * 1024;

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
            $picture = $this->readPictureUpload($upload);
        } catch (InvalidArgumentException $error) {
            return ['error' => $error->getMessage()];
        }

        $year = (int)date('Y');
        $id = $this->createKrisKringleInDB($name, $year, $userId, $picture);
        if ($id <= 0) {
            return ['error' => 'Couldn’t create your Kris Kringle. Please try again.'];
        }

        return ['data' => [
            'krisKringleId' => $id,
            'name' => $name,
            'year' => $year,
            'createdBy' => $userId,
            'pictureVersion' => $picture === null ? '' : hash('sha256', $picture)
        ]];
    }

    public function updateKrisKringlePicture(int $id, ?array $upload):array
    {
        $userId = (int)$this->s()->userId();
        if ($userId <= 0 || !$this->getOwnedKrisKringleFromDB($id, $userId)) {
            return ['error' => 'Only the creator can change the group photo.'];
        }

        try {
            $picture = $this->readPictureUpload($upload);
        } catch (InvalidArgumentException $error) {
            return ['error' => $error->getMessage()];
        }
        if ($picture === null) {
            return ['error' => 'Choose a photo to upload.'];
        }

        $this->updateKrisKringlePictureInDB($id, $userId, $picture);
        $group = $this->getOwnedKrisKringleFromDB($id, $userId);
        return $group ? ['data' => $group] : ['error' => 'This Kris Kringle is no longer available.'];
    }

    public function getKrisKringlePicture(int $id): ?string
    {
        if ($this->s()->userId() <= 0 || $id <= 0) {
            return null;
        }
        return $this->getVisibleKrisKringlePictureFromDB($id);
    }

    private function readPictureUpload(?array $upload): ?string
    {
        if ($upload === null || ($upload['error'] ?? null) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        $path = $upload['tmp_name'] ?? null;
        if (($upload['error'] ?? null) !== UPLOAD_ERR_OK || !is_string($path) || !is_uploaded_file($path)) {
            throw new InvalidArgumentException('Couldn’t upload the photo. Please try again.');
        }

        $data = @file_get_contents($path, false, null, 0, self::MAX_PICTURE_BYTES + 1);
        if ($data === false || strlen($data) > self::MAX_PICTURE_BYTES) {
            throw new InvalidArgumentException('Choose a photo smaller than 512 KB.');
        }
        $size = @getimagesizefromstring($data);
        if (!$size || $size[2] !== IMAGETYPE_JPEG || min($size[0], $size[1]) < 1
            || max($size[0], $size[1]) > 1024 || substr($data, -2) !== "\xFF\xD9") {
            throw new InvalidArgumentException('Choose a valid JPEG photo up to 1024 pixels wide or tall.');
        }
        return $data;
    }

    /**
	 * @param Selmasu $s
	 * @return KrisKringleService
	 */
	public static function create( Selmasu $s ):KrisKringleService{
		return new self( $s );
	}

}