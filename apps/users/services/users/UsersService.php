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
	public function getUserByEmail(string $email) {
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
	
	/**
	 * @param Selmasu $s
	 * @return UsersService
	 */
	public static function create( Selmasu $s ):UsersService {
		return new self( $s );
	}

}