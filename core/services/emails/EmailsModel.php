<?php
namespace simpli;
class EmailsModel extends \Model
{
    /**
     * Gets all email details for a given query
     * @param \SqlQueryBits $query
     * @return array
     */
    protected function getEmailsFromDB( \SqlQueryBits $query )
    {
        $sql = "
			SELECT
				email.*
			FROM
				" . $this->coreTables()->email() . " AS email
			WHERE
				1
				" . $query->where()->get() . "
			" . $query->orderByString() . "
			" . $query->limitString();
        return $this->adb->fetchAll( $sql );
    }

    /**
     * Gets the total demo count for a given query
     * @param \SqlQueryBits $query
     * @return int
     */
    protected function getEmailsTotalFromDB( \SqlQueryBits $query )
    {
        $sql = "
			SELECT
				COUNT( * ) AS total
			FROM
				" . $this->coreTables()->email() . " AS email
			WHERE
				1
				" . $query->where()->get();
        return $this->adb->fetchValue( 'total', $sql );
    }

    /**
     * @param \simpli\Email $email
     * @return int
     */
    protected function createEmailInDB( \simpli\Email $email )
    {
        $sql = "
            INSERT INTO
                " . $this->coreTables()->email() . "
            SET
                " . $email->sqlSet();
        $this->adb->query( $sql );
        return $email->emailId()->get();
    }

    /**
     * @param \simpli\Email $email
     * @return int
     */
    protected function saveToDB( \simpli\Email $email )
    {
        $sql = "
            UPDATE
                " . $this->coreTables()->email() . "
            SET
                " . $email->sqlSet() . "
            WHERE
                emailId = " . (int)$email->emailId()->get();
        $this->adb->query( $sql );
        return $email->emailId()->get();
    }

    protected function addAttachmentToDB( $emailId, $fileId )
    {
        $sql = "
			INSERT INTO
				" . $this->coreTables()->emailAttachment() . "
            SET
                emailId = " . (int)$emailId . ",
				fileId = " . (int)$fileId;
        $this->adb->query( $sql );
    }

    protected function deleteAttachmentFromDB( $emailId, $fileId )
    {
        $sql = "
			DELETE FROM
				" . $this->coreTables()->emailAttachment() . "
            WHERE
                emailId = " . (int)$emailId . "
				AND fileId = " . (int)$fileId . "
            LIMIT
                1";
        $this->adb->query( $sql );
    }

    protected function getAttachmentsFromDB( \SqlQueryBits $query, $returnType = \Model::RETURN_TYPE_ARRAY )
    {
        $sql = "
			SELECT
				file.*
			FROM
				" . $this->coreTables()->file() . " AS file
				JOIN " . $this->coreTables()->emailAttachment() . " AS attachment
					ON ( attachment.fileId = file.fileSerial )
				JOIN " . $this->coreTables()->email() . " AS email
					ON ( attachment.emailId = email.emailId )
			WHERE
				1
				" . $query->where()->get() . "
			" . $query->orderByString() . "
			" . $query->limitString();
        if( $returnType == \Model::RETURN_TYPE_ARRAY )
        {
            return $this->adb->fetchAll( $sql );
        }

        $files = array();
        foreach( $this->adb->fetchAll( $sql ) as $item )
        {
            $files[] = \File::create()->fromArray( $item );
        }

        return $files;
    }

    protected function getAttachmentsTotalFromDB( \SqlQueryBits $query )
    {
        $sql = "
			SELECT
				COUNT( * ) AS total
			FROM
				" . $this->coreTables()->file() . " AS file
				JOIN " . $this->coreTables()->emailAttachment() . " AS attachment
					ON ( attachment.fileId = file.fileSerial )
				JOIN " . $this->coreTables()->email() . " AS email
					ON ( attachment.emailId = email.emailId )
			WHERE
				1
				" . $query->where()->get();
        return $this->adb->fetchValue( 'total', $sql );
    }
}