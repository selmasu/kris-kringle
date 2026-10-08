<?php
class UsersModel extends Model {

    /**
	 * @param User $user
	 * @return int
	 */
	protected function saveUserToDB( User $user )
	{
        if( $user->userId()->propertySet() && $user->userId()->get() ) {
                $sql = "
                    SELECT
                        userId
                    FROM
                        " . $this->coreTables()->user() . "
                    WHERE
                        email = '" . $this->db->escapeString( $user->email()->get() ) . "'
                        AND userId != " . (int)$user->userId()->get();
                if( $this->db->numRows( $sql ) ) {
                    return 0;
                }

                $sql = "
                    UPDATE
                        " . $this->coreTables()->user() . "
                    SET
                        " . $user->sqlSet() . ",
                        updatedBy = " . (int)$this->s()->userId() . ",
                        updatedDate = NOW()
                    WHERE
                        userId = " . (int)$user->userId()->get();
                $this->db->query( $sql );

                return $user->userId()->get();
        } else {
            $sql = "
            SELECT
                userId
            FROM
                " . $this->coreTables()->user() . "
            WHERE
                email = '" . $this->db->escapeString( $user->email()->get() ) . "'";
            if( $this->db->numRows( $sql ) ) {
                return 0;
            }

            $sql = "
                INSERT INTO
                    " . $this->coreTables()->user() . "
                SET
                    " . $user->sqlSet() . ",
                    createdBy = " . (int)$this->s()->userId() . ",
                    createdDate = NOW()";
            return $this->db->insertId( $sql );
        }
	}
	
	/**
	 * @param int $id
	 * @return User
	 */
	protected function getUserFromDB( int $id )
	{
		$sql = "
			SELECT
				user.*,
				'' AS password,
				profilePhoto.location AS profilePhotoUrl,
				miniProfilePhoto.location AS miniProfilePhotoUrl
			FROM
				" . $this->coreTables()->user() . "
				LEFT JOIN " . $this->coreTables()->file() . " AS profilePhoto
				    ON profilePhoto.fileSerial = user.profilePhotoId
				LEFT JOIN " . $this->coreTables()->file() . " AS miniProfilePhoto
				    ON miniProfilePhoto.fileSerial = user.miniProfilePhotoId
			WHERE
			    user.deleted = 0
				AND userId = " . (int)$id . "
			LIMIT
				0,1";
		if( $row = $this->db->fetchAssoc( $sql ) ) {
			return User::create()->fromArray( $row );
		}
		
		return null;
	}

    /**
     * @param string $email
     * @return User|null
     */
    protected function getUserByEmailFromDB( string $email )
    {
        $sql = "
			SELECT
				user.*,
				'' AS password,
				profilePhoto.location AS profilePhotoUrl,
				miniProfilePhoto.location AS miniProfilePhotoUrl
			FROM
				" . $this->coreTables()->user() . "
				LEFT JOIN " . $this->coreTables()->file() . " AS profilePhoto
				    ON profilePhoto.fileSerial = user.profilePhotoId
				LEFT JOIN " . $this->coreTables()->file() . " AS miniProfilePhoto
				    ON miniProfilePhoto.fileSerial = user.miniProfilePhotoId
			WHERE
			    user.deleted = 0
				AND email = '" . $this->db->escapeString($email ). "'
			LIMIT
				0,1";
        if( $row = $this->db->fetchAssoc( $sql ) ) {
            return User::create()->fromArray( $row );
        }

        return null;
    }

	/**
	 * @param SqlQueryBits $query
	 * @return array
	 */
	protected function getUsersFromDB( SqlQueryBits $query ):array
	{
		$sql = "
			SELECT
				user.*,
				CONCAT( firstName, ' ', lastName ) AS name,
				'' AS password
				" . $query->select()->get() . "
			FROM
				" . $this->coreTables()->user() . "
				" . $query->join()->get() . "
			WHERE
				user.deleted = 0
				" . $query->where()->get() . "
			" . $query->orderByString() . "
			" . $query->limitString();
		return $this->db->fetchAll($sql);
	}

    /**
     * @param SqlQueryBits $query
     * @return array
     */
    protected function getUsersForSelectFromDB( SqlQueryBits $query ):array
    {
        $sql = "
			SELECT
				user.userId,
				CONCAT( firstName, ' ', lastName ) AS name,
				miniProfilePhoto.location AS miniProfilePhotoUrl
			FROM
				" . $this->coreTables()->user() . "
				LEFT JOIN " . $this->coreTables()->file() . " AS miniProfilePhoto
				    ON miniProfilePhoto.fileSerial = user.miniProfilePhotoId
			WHERE
				user.deleted = 0
				" . $query->where()->get() . "
			" . $query->orderByString() . "
			" . $query->limitString();
        return $this->db->fetchAll($sql);
    }

    /**
     * @param SqlQueryBits $query
     * @return array
     */
    protected function getUsersForAdminFromDB( SqlQueryBits $query ):array
    {
        $sql = "
			SELECT
				user.*,
				CONCAT( firstName, ' ', lastName ) AS name,
			    organisation.name AS organisation,
				'' AS password
			FROM
				" . $this->coreTables()->user() . "
			WHERE
				user.deleted = 0
				" . $query->where()->get() . "
			" . $query->orderByString() . "
			" . $query->limitString();
        return $this->db->fetchAll($sql);
    }
	
	/**
	 * @param SqlQueryBits $query
	 * @return int
	 */
	protected function getUsersTotalFromDB( SqlQueryBits $query ):int
	{
		$sql = "
			SELECT
				COUNT( * ) AS total
			FROM
				" . $this->coreTables()->user() . "
			WHERE
				user.deleted = 0
				AND organisationId = " . (int)$this->s()->user()->organisationId()->get() . "
				" . $query->where()->get();
		return (int)$this->db->fetchValue( 'total', $sql );
	}

    /**
     * @param SqlQueryBits $query
     * @return int
     */
    protected function getUsersForAdminTotalFromDB( SqlQueryBits $query ):int
    {
        $sql = "
			SELECT
				COUNT( * ) AS total
			FROM
				" . $this->coreTables()->user() . "
				LEFT JOIN " . $this->coreTables()->organisation() . " AS organisation
				    ON organisation.organisationId = user.organisationId
			WHERE
				user.deleted = 0
				" . $query->where()->get();
        return (int)$this->db->fetchValue( 'total', $sql );
    }
	
	/**
	 * @param int $id
	 * @return array
	 */
	protected function getUserPermissionsFromDB( int $id ):array {
	    $this->db->clear();

		$sql = "
			SELECT
				userId,
				permission,
				appId
			FROM
				" . $this->coreTables()->permission() . "
			WHERE
				userId = " . (int)$id;
		return $this->db->fetchAll($sql);
	}
	
	/**
	 * @param int $userId
	 * @param UserPermissions $userPermissions
	 */
	protected function saveUserPermissionsToDB( int $userId, UserPermissions $userPermissions )
	{
		$sql = "
			DELETE FROM
				" . $this->coreTables()->permission() . "
			WHERE
				userId = " . (int)$userId;
		$this->db->query( $sql );

		//$this->saveUserAppPermissionToDB( $userId, $userPermissions->emails()->getPermission(), App::EMAILS );
	}

	private function saveUserAppPermissionToDB( $userId, $permission, $appId )
	{
		$sql = "
			INSERT INTO
				" . $this->coreTables()->permission() . "
			SET
				userId = " . (int)$userId . ",
				permission = " . (int)$permission . ",
				appId = " . (int)$appId;
		$this->db->query( $sql );
	}
	
	/**
	 * Updates the specified user to deleted = 1
	 */
	protected function deleteUserFromDB( $id )
	{
		$sql = "
			UPDATE
				" . $this->coreTables()->user() . "
			SET
				deleted = 1
			WHERE
				userId = " . (int)$id . "
				AND organisationId = " . (int)$this->s()->user()->organisationId()->get();
		$this->db->query( $sql );
		if(Settings::$dbMode == Settings::DB_MODE_MULTI){
            $this->db->query( $sql );
        }
	}

    protected function saveFollowToDB(int $followId, bool $follow){
       $sql = "
			DELETE FROM
				" . $this->tables()->follow() . "
			WHERE
				userId = " . (int)$this->s()->userId() . "
				AND followUserId = " . (int)$followId . "
			LIMIT
			    1";
       $this->adb->query($sql);

       if($follow){
            $sql = "
                INSERT INTO
                    " . $this->tables()->follow() . "
                SET
                    userId = " . (int)$this->s()->userId() . ",
                    followUserId = " . (int)$followId;
            $this->adb->query($sql);
       }
    }

    protected function saveInviteToFollowToDB(string $email, string $message){
        $sql = "
			SELECT
			    *
			FROM
				" . $this->tables()->followInvite() . "
			WHERE
				userId = " . (int)$this->s()->userId() . "
				AND inviteEmail = '" . $this->adb->escapeString($email) . "'
			LIMIT
			    0,1";
        foreach($this->adb->fetchAll($sql) as $invite){
            //If statusId is 1 or 2 they've accepted or decline so do nothing, otherwise update
            if($invite['statusId'] == 0){
                $sql = "
                    UPDATE
                        " . $this->tables()->followInvite() . "
                    SET
                        message = '" . $this->adb->escapeString($message) . "'
                    WHERE
                        followInviteId = " . (int)$invite['followInviteId'];
                $this->adb->query($sql);
            }

            return;
        }

        $sql = "
            INSERT INTO
                " . $this->tables()->followInvite() . "
            SET
                userId = " . (int)$this->s()->userId() . ",
                inviteEmail = '" . $this->adb->escapeString($email) . "',
                message = '" . $this->adb->escapeString($message) . "',
                createdDate = NOW()";
        $this->adb->query($sql);
    }

    protected function updateInviteToFollowInDB(int $userId, int $statusId){
        $sql = "
            UPDATE
                " . $this->tables()->followInvite() . "
            SET
                statusId = " . (int)$statusId . "
            WHERE
                userId = " . (int)$userId . "
                AND inviteEmail = '" . $this->adb->escapeString($this->s()->user()->email()->get()) . "'
            LIMIT
                1";
        $this->adb->query($sql);
    }

    protected function saveRequestToFollowToDB(int $followId, string $message){
        $sql = "
			SELECT
			    *
			FROM
				" . $this->tables()->followRequest() . "
			WHERE
				userId = " . (int)$this->s()->userId() . "
				AND requestToFollowId = " . (int)$followId . "
			LIMIT
			    0,1";
        foreach($this->adb->fetchAll($sql) as $request){
            //If statusId is 1 or 2 they've accepted or decline so do nothing, otherwise update
            if($request['statusId'] == 0){
                $sql = "
                    UPDATE
                        " . $this->tables()->followRequest() . "
                    SET
                        message = '" . $this->adb->escapeString($message) . "'
                    WHERE
                        followRequestId = " . (int)$request['followRequestId'];
                $this->adb->query($sql);
            }

            return;
        }

        $sql = "
            INSERT INTO
                " . $this->tables()->followRequest() . "
            SET
                userId = " . (int)$this->s()->userId() . ",
                requestToFollowId = " . (int)$followId . ",
                message = '" . $this->adb->escapeString($message) . "',
                createdDate = NOW()";
        $this->adb->query($sql);
    }

    protected function updateRequestToFollowInDB(int $userId, int $statusId){
        $sql = "
            UPDATE
                " . $this->tables()->followRequest() . "
            SET
                statusId = " . (int)$statusId . "
            WHERE
                requestToFollowId = " . (int)$this->s()->userId() . "
                AND userId = " . (int)$userId . "
            LIMIT
                1";
        $this->adb->query($sql);

        if($statusId == 1){
            $sql = "
			DELETE FROM
				" . $this->tables()->follow() . "
			WHERE
				userId = " . (int)$userId . "
				AND followUserId = " . (int)$this->s()->userId() . "
			LIMIT
			    1";
            $this->adb->query($sql);

            $sql = "
            INSERT INTO
                " . $this->tables()->follow() . "
            SET
                userId = " . (int)$userId . ",
                followUserId = " . (int)$this->s()->userId();
            $this->adb->query($sql);
        }
    }

    protected function getFollowInvitesFromDB(SqlQueryBits $query):array {
        $sql = "
			SELECT
				*
			FROM
				" . $this->tables()->followInvite() . "
			WHERE
				1
				" . $query->where()->get() . "
            " . $query->orderByString();
        return $this->db->fetchAll($sql);
    }

}