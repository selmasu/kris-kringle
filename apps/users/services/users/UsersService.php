<?php
class UsersService extends UsersModel {

	/**
	 * @param User $user
	 * @param bool $updatePermissions
	 * @return array
	 */
	public function saveUser( User $user, bool $updatePermissions = true ):array
	{
	    if( $userId = $this->saveUserToDB( $user ) ) {
			if( $updatePermissions ) {
				$this->saveUserPermissionsToDB( $userId, $user->permissions() );
			}

            return ['userId' => $userId];
		}

		return ['userId' => 0, 'error' => 'User with email ' . $user->email()->get() . ' already exists'];
	}
	
	/**
	 * @param array $data
	 * @param bool $updatePermissions
	 * @return array
	 */
	public function saveFromArray( array $data, bool $updatePermissions = true ):array {
		if(isset($data['userId']) && (int)$data['userId'] > 0){
		    $user = $this->getUserObject($data['userId']);
		    $user->fromArray($data);

		    //Prevent switching organisations
		    $user->organisationId()->set($this->s()->user()->organisationId()->get());
        } else {
		    $user = User::create()->fromArray( $data );
		    if(isset($data['signUp']) && $data['signUp'] && isset($data['organisationId']) && $data['organisationId']){
                $user->organisationId()->set($data['organisationId']);
                $user->admin()->set(true);
                $user->setPermissions(UserPermissions::create()->grantFullAccess());
            } else {
                $user->organisationId()->set($this->s()->user()->organisationId()->get());
            }
        }

		if(!$this->s()->loggedIn() || !$this->s()->user()->superUser()->get()){
		    $user->superUser()->set(false);
        }

		return $this->saveUser($user, $updatePermissions );
	}

    public function signUp($data):array {
        if($data['token'] == Constants::SIGN_UP_TOKEN){
            $user = User::create()->fromArray($data);
            if($user->email()->get()){
                $user->admin()->set(false);
                $user->superUser()->set(false);

                return $this->saveUser( $user, FALSE );
            }
        }

        return ['userId' => 0];
    }

    public function getMyProfile(): array
    {
        $userId = $this->s()->userId();
        if ($userId <= 0) {
            return ['error' => 'Please sign in again.'];
        }

        $user = $this->s()->user();
        $picture = PictureUpload::read($this->s(), $this->getProfilePhotoSerialFromDB($userId));
        $name = trim($user->displayName()->get() ?? '');
        return [
            'name' => $name === '' ? trim($user->name()) : $name,
            'picture' => $picture === null ? null : base64_encode($picture),
            'appleLinked' => $this->appleSubjectHashForUser($userId) !== null,
        ];
    }

    public function updateMyProfile(?string $name, ?array $upload): array
    {
        $userId = $this->s()->userId();
        if ($userId <= 0) {
            return ['error' => 'Please sign in again.'];
        }
        if ($name !== null) {
            $name = trim($name);
            if (!preg_match('/\A[^\p{Cc}]{1,100}\z/u', $name)) {
                return ['error' => 'Enter a name between 1 and 100 characters.'];
            }
        }
        try {
            $serial = PictureUpload::save($this->s(), $upload);
        } catch (InvalidArgumentException | RuntimeException $error) {
            return ['error' => $error->getMessage()];
        }
        if ($name === null && $serial === null) {
            return ['error' => 'Change your name or choose a photo to save.'];
        }

        $previousSerial = $this->saveProfileToDB($userId, $name, $serial);
        if ($previousSerial === null) {
            PictureUpload::delete($this->s(), $serial ?? 0);
            return ['error' => 'Couldn’t save your profile. Check your changes and try again.'];
        }
        PictureUpload::delete($this->s(), $previousSerial);
        return ['saved' => true];
    }

    public function updateUser(array $data):array {
        $this->s()->user()->firstName()->set($data['firstName']);
        $this->s()->user()->lastName()->set($data['lastName']);
        $this->s()->user()->displayName()->set($data['displayName']);
        $this->s()->user()->picture()->set($data['picture']);

        return $this->saveUser($this->s()->user(), false);
    }

	/**
	 * @param int $id
	 * @return User
	 */
	public function getUser( int $id ){
		if( $user = $this->getUserFromDB( $id ) ) {
			$permissions = UserPermissions::create()->fromArray( $this->getUserPermissionsFromDB( $id ) );
			return $user->setPermissions( $permissions );
		}
		
		return null;
	}

    /**
     * @param int $id
     * @return User
     */
    public function getUserObject( int $id ):User {
        return $this->getUserFromDB( $id );
    }

    /**
     * @return array
     */
	public function getLoggedInUser():array {
		return $this->s()->user()->toArray();
	}
	
	/**
	 * @return array
	 */
	public function getUserPermissions():array {
		return UserPermissions::create()->fromArray( $this->getUserPermissionsFromDB( $this->s()->userId() ) )->toArray();
	}
	
	/**
	 * @param int $id
	 * @return array
	 */
	public function getUserById( int $id ):array {
        if( $user = $this->getUserFromDB( $id ) ) {
        	$permissions = UserPermissions::create()->fromArray( $this->getUserPermissionsFromDB( $id ) );

            return array_merge($user->toArray(), $permissions->toArray());
        }
		
		return array();
	}

    /**
     * @param string $email
     * @return User|null
     */
	public function getUserByEmail(string $email): ?User {
        return $this->getUserByEmailFromDB($email);
    }

	/**
     * @param int $id
	 * @return array
	 */
	public function getUserProfile(int $id = 0):array{
	    $id = $id ? $id : $this->s()->userId();
		return $this->getUserById($id);
	}

    /**
     * @param int $id
     * @return int
     */
	public function archive(int $id):int {
	    $user = $this->getUserObject($id);
	    $user->archived()->set(true);
	    $this->saveUserToDB($user);

	    return $id;
    }

    /**
     * @param ListArguments $listArguments
     * @return array
     */
	public function getUsers( ListArguments $listArguments ):array
	{
		$where = ' AND userId > 0';
		
		if( $listArguments->filters()->stringVal( 'search' ) ) {
			$search = $listArguments->filters()->stringVal( 'search' );
			$where .= " AND ( firstName LIKE '%" . $this->db->escapeString( $search ) . "%'
							OR lastName LIKE '%" . $this->db->escapeString( $search ) . "%'
							OR email LIKE '%" . $this->db->escapeString( $search ) . "%'
							OR mobile LIKE '%" . $this->db->escapeString( $search ) . "%' )";
		}
		
		$query = SqlQueryBits::create();
        $query->limit()->set( $listArguments->take()->get() );
		$query->offset()->set( $listArguments->skip()->get() );
		$query->orderBy()->set( $this->sortToString( $listArguments->sort()->get() ) );
		$query->where()->set( $where );
		
		return ['data' => $this->getUsersFromDB( $query ), 'total' => $this->getUsersTotalFromDB( $query )];
	}

    /**
     * @param ListArguments $listArguments
     * @return array
     */
    public function getUsersForSelect(ListArguments $listArguments){
        $where = ' AND user.userId > 0';

        if ($listArguments->filters()->stringVal('name')) {
            $search = $listArguments->filters()->stringVal('name');
            $where .= " AND ( user.firstName LIKE '%" . $this->adb->escapeString($search) . "%'
                            OR user.lastName LIKE '%" . $this->adb->escapeString($search) . "%')";
        }

        $query = SqlQueryBits::create();
        $query->limit()->set($listArguments->take()->get());
        $query->offset()->set($listArguments->skip()->get());
        $query->orderBy()->set('name');
        $query->where()->set($where);

        $users = [];
        if($listArguments->filters()->boolVal('incBlank')){
            $users[] = ['userId' => 0, 'miniProfilePhotoUrl' => '', 'name' => 'Team member...'];
        }

        foreach($this->getUsersForSelectFromDB($query) as $user){
	        $users[] = $user;
        }

        return $users;
    }

    /**
     * @param ListArguments $listArguments
     * @return array
     */
    public function getUsersForAdmin( ListArguments $listArguments )
    {
        $where = ' AND userId > 0';

        if( $listArguments->filters()->stringVal( 'search' ) ) {
            $search = $listArguments->filters()->stringVal( 'search' );
            $where .= " AND ( firstName LIKE '%" . $this->db->escapeString( $search ) . "%'
							OR lastName LIKE '%" . $this->db->escapeString( $search ) . "%'
							OR email LIKE '%" . $this->db->escapeString( $search ) . "%'
							OR mobile LIKE '%" . $this->db->escapeString( $search ) . "%'
							OR organisation.name LIKE '%" . $this->db->escapeString( $search ) . "%')";
        }

        $query = SqlQueryBits::create();
        $query->limit()->set( $listArguments->take()->get() );
        $query->offset()->set( $listArguments->skip()->get() );
        $query->orderBy()->set( $this->sortToString( $listArguments->sort()->get() ) );
        $query->where()->set( $where );

        return ['data' => $this->getUsersForAdminFromDB( $query ), 'total' => $this->getUsersForAdminTotalFromDB( $query )];
    }

    /**
     * @param ListArguments $listArguments
     * @return array
     */
    public function getAdminUsers(ListArguments $listArguments):array {
        $where = ' AND user.userId > 0';
        $where .= ' AND user.admin = 1';

        if ($listArguments->filters()->stringVal('name')) {
            $search = $listArguments->filters()->stringVal('name');
            $where .= " AND ( user.firstName LIKE '%" . $this->adb->escapeString($search) . "%'
                            OR user.lastName LIKE '%" . $this->adb->escapeString($search) . "%')";
        }

        $query = SqlQueryBits::create();
        $query->limit()->set($listArguments->take()->get());
        $query->offset()->set($listArguments->skip()->get());
        $query->orderBy()->set('name');
        $query->where()->set($where);

        return $this->getUsersForSelectFromDB($query);
    }

    /**
     * @param array $ids
     */
	public function deleteUsers( array $ids ){
		if( $this->s()->user()->permissions()->users()->canManage() ) {
			foreach( $ids as $id ) {
				if( $id != $this->s()->userId() ) {
					$this->deleteUserFromDB( $id );
				}
			}
		}
	}

    public function deleteMyAccount(){
        $this->deleteUserFromDB( $this->s()->userId() );
        $this->s()->session()->destroySession();
    }

    public function getOrCreateEmailAccount(string $emailAddress): ?User
    {
        $lock = $this->emailAccountLock($emailAddress);
        if ((int)$this->db->fetchValue('acquired', "SELECT GET_LOCK('$lock', 5) AS acquired") !== 1) {
            return null;
        }
        try {
            return $this->findOrCreateEmailAccount($emailAddress);
        } finally {
            $this->db->query("DO RELEASE_LOCK('$lock')");
        }
    }

    private function emailAccountLock(string $emailAddress): string
    {
        return 'otp_' . substr(hash('sha256', $emailAddress), 0, 56);
    }

    private function findOrCreateEmailAccount(string $emailAddress): ?User
    {
        $user = $this->getUserByEmail($emailAddress);
        if ($user) {
            return $user;
        }

        $user = User::create();
        $user->email()->set($emailAddress);
        $user->admin()->set(false);
        $user->superUser()->set(false);
        $user->passwordLogin()->set(false);
        $userId = $this->saveUserToDB($user);
        if ($userId) {
            $user->userId()->set($userId);
            return $user;
        }

        // Another sign-in provider may have created the address.
        return $this->getUserByEmail($emailAddress);
    }

    public function sendOneTimeCode(string $emailAddress):array
    {
        $emailAddress = strtolower(trim($emailAddress));
        if (strlen($emailAddress) > 100 || !filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
            return ['sent' => false, 'error' => 'Please enter a valid email address'];
        }

        $lock = $this->emailAccountLock($emailAddress);
        if ((int)$this->db->fetchValue('acquired', "SELECT GET_LOCK('" . $lock . "', 5) AS acquired") !== 1) {
            return ['sent' => false, 'error' => 'Unable to send a code. Please try again'];
        }
        try {
            $user = $this->findOrCreateEmailAccount($emailAddress);

            if (!$user || !$user->userId()->get()) {
                return ['sent' => false, 'error' => 'Unable to send a code. Please try again'];
            }

            $oneTimeCode = $this->setOneTimeCodeInDB($emailAddress);
            if ($oneTimeCode === null) {
                return ['sent' => false, 'error' => 'Please wait one minute before requesting another code', 'retryAfter' => 60];
            }
        } finally {
            $this->db->query("DO RELEASE_LOCK('" . $lock . "')");
        }

        $email = EmailsService::create($this->s())->getEmailObject(\KrisKringle\Email::TYPE_ONE_TIME_CODE);
        if (!$email) {
            $email = \KrisKringle\Email::create();
            $email->subject()->set('Your Kris Kringle sign-in code');
            $email->htmlBody()->set('<p>Your sign-in code is <strong>[[Access Code]]</strong>.</p><p>Use it to sign in or create your account. It expires in five minutes.</p><p>If you did not request this code, you can ignore this email.</p>');
        }

        $email->fromAddress()->set('noreply@' . Settings::$mailgunDomain);
        $email->fromName()->set('Kris Kringle');
        $email->toAddress()->set($emailAddress);
        $email->htmlBody()->strReplace('[[First Name]]', htmlspecialchars($user->firstName()->get(), ENT_QUOTES, 'UTF-8'));
        $email->htmlBody()->strReplace('[[Access Code]]', $oneTimeCode);
        $email->plainBody()->set('Your Kris Kringle sign-in code is ' . $oneTimeCode . '. It expires in five minutes.');
        $email->active()->set(true);
        $email->app()->set('authentication');
        $email->id()->set('OTC_' . $user->userId()->get() . '_' . bin2hex(random_bytes(8)));

        if (!EmailService::create($this->s())->sendMail($email)) {
            $this->clearOneTimeCodeInDB($user->userId()->get(), $oneTimeCode);
            return ['sent' => false, 'error' => 'Could not send email, please try again in one minute'];
        }

        return ['sent' => true, 'error' => '', 'retryAfter' => 60, 'expiresIn' => 300];
    }
	
	/**
	 * @param Selmasu $s
	 * @return UsersService
	 */
	public static function create( Selmasu $s ):UsersService {
		return new self( $s );
	}

}