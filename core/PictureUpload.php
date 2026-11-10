<?php
final class PictureUpload
{
    private const MAX_PICTURE_BYTES = 512 * 1024;

    public static function save(Selmasu $s, ?array $upload): ?int
    {
        $data = self::validate($upload);
        if ($data === null) {
            return null;
        }

        $fileService = self::s3($s);
        if ($fileService === null) {
            throw new RuntimeException('Photo storage is unavailable. Please try again.');
        }
        $file = File::create()
            ->name()->set(bin2hex(random_bytes(16)) . '.jpg')
            ->tmpName()->set($upload['tmp_name'])
            ->type()->set('image/jpeg')
            ->size()->set(strlen($data));

        try {
            $serial = (int)$fileService->savePrivateFile($file);
            // FileService can return a serial even when the upload failed.
            if ($serial > 0 && $file->location()->get()) {
                return $serial;
            }
        } catch (Exception $error) {
            Utils::trace('Could not upload a Kris Kringle photo to S3.');
        }

        self::delete($s, (int)$file->fileSerial()->get());
        throw new RuntimeException('Couldn’t upload the photo. Please try again.');
    }

    public static function read(Selmasu $s, int $serial): ?string
    {
        if ($serial <= 0 || !($fileService = self::s3($s))) {
            return null;
        }
        $path = '';
        try {
            $file = $fileService->getFile($serial);
            if (!$file->fileSerial()->get() || $file->deleted()->get() || !$file->isPrivate()->get()
                || $file->storageType()->get() !== Settings::FILE_STORE_AWS_S3 || !$file->location()->get()) {
                return null;
            }
            $path = $fileService->downloadPrivateFileToTmp($file);
            if ($path === '') {
                return null;
            }
            $data = @file_get_contents($path, false, null, 0, self::MAX_PICTURE_BYTES + 1);
            return $data !== false && strlen($data) <= self::MAX_PICTURE_BYTES && strlen($data) === $file->size()->get()
                ? $data
                : null;
        } catch (Exception $error) {
            Utils::trace('Could not read a Kris Kringle photo from S3.');
            return null;
        } finally {
            if ($path !== '') {
                @unlink($path);
            }
        }
    }

    public static function delete(Selmasu $s, int $serial): void
    {
        if ($serial <= 0 || !($fileService = self::s3($s))) {
            return;
        }
        try {
            $fileService->deleteFile($serial);
        } catch (Exception $error) {
            Utils::trace('Could not delete a Kris Kringle photo from S3.');
        }
    }

    private static function s3(Selmasu $s): ?AWSFileService
    {
        $service = FileService::create($s);
        return $service instanceof AWSFileService ? $service : null;
    }

    private static function validate(?array $upload): ?string
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
}
