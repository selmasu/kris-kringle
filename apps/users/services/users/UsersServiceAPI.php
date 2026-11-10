<?php
class UsersServiceAPI extends ServiceAPI
{
	//Function Names
	const GET_USER                  = 'getUser';
	const GET_USER_BY_ID            = 'getUserById';
	const GET_USERS                 = 'getUsers';
	const GET_USERS_FOR_SELECT      = 'getUsersForSelect';
    const GET_USERS_FOR_ADMIN       = 'getUsersForAdmin';
    const GET_ADMIN_USERS           = 'getAdminUsers';
	const DELETE_USERS              = 'deleteUsers';
    const UPDATE_USER               = 'updateUser';
	const GET_PERMISSIONS           = 'getUserPermissions';
    const GET_USER_PROFILE          = 'getUserProfile';
    const GET_USER_PROFILE_BY_ID    = 'getUserProfileById';
    const ARCHIVE                   = 'archive';
    const DELETE_MY_ACCOUNT         = 'deleteMyAccount';
    const SIGN_UP                   = 'signUp';
    const SEND_ONE_TIME_CODE        = 'sendOneTimeCode';
	
	//Argument Names
	const USER_ID                   = 'userId';
	const IDS                       = 'ids';
    const NAME                      = 'name';
    const EMAIL                     = 'email';
    const DATA                      = 'data';
	
	/**
	 * 
	 * @var UsersService
	 */
	private $usersService;
	
	public function __construct( Selmasu $s )
	{
		parent::__construct( $s );
		
		$this->usersService = UsersService::create( $s );
	}
	
	/**
	 * (non-PHPdoc)
	 * @see framework/core/ServiceAPI::api()
	 */
	public function api( $functionToCall, $arguments = array() )
	{
		$this->arguments( $arguments );

		switch( $functionToCall )
		{
			case self::GET_USER_BY_ID:
				return $this->usersService->getUserById( $this->intArgument( self::USER_ID ) );
				
			case self::GET_USERS:
				return $this->usersService->getUsers( ListArguments::create()->fromArray( $arguments ) );

            case self::GET_USERS_FOR_SELECT:
                return $this->usersService->getUsersForSelect(ListArguments::create()->fromArray($arguments));
				
			case self::DELETE_USERS:
				return $this->usersService->deleteUsers( $this->arrayArgument( self::IDS ) );

            case self::DELETE_MY_ACCOUNT:
                return $this->usersService->deleteMyAccount();
				
			case self::GET_USER:
				return $this->usersService->getLoggedInUser();

            case self::UPDATE_USER:
                return $this->usersService->updateUser($this->arrayArgument(self::DATA));
				
			case self::GET_PERMISSIONS:
				return $this->usersService->getUserPermissions();

			case self::GET_USER_PROFILE:
				return $this->usersService->getUserProfile();

            case self::GET_USER_PROFILE_BY_ID:
                return $this->usersService->getUserProfile($this->intArgument( self::USER_ID ));

            case self::ARCHIVE:
                return $this->usersService->archive($this->intArgument( self::USER_ID ));

            case self::SEND_ONE_TIME_CODE:
                return $this->usersService->sendOneTimeCode($this->stringArgument(self::EMAIL));

            case self::SIGN_UP:
                return $this->usersService->signUp($this->arrayArgument(self::DATA));
		}

        if($this->s()->user()->superUser()->get()){
            switch( $functionToCall )
            {
                case self::GET_USERS_FOR_ADMIN:
                    return $this->usersService->getUsersForAdmin( ListArguments::create()->fromArray( $arguments ) );

                case self::GET_ADMIN_USERS:
                    return $this->usersService->getAdminUsers( ListArguments::create()->fromArray( $arguments ) );
            }
        }
	}
	
	/**
	 * @param string $function
	 * @return bool
	 */
	public static function getIsAllowedPublicly( string $function ):bool{
		return in_array($function, [self::GET_USER, self::SIGN_UP, self::SEND_ONE_TIME_CODE]);
	}
	
	/**
	 * 
	 * Static creator
	 * @param Selmasu $s
	 * @return UsersServiceAPI
	 */
	public static function create( Selmasu $s ){
		return new self( $s );
	}
}