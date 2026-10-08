<?php
class UsersServiceAPI extends ServiceAPI
{
	//Function Names
	const GET_USER                  = 'getUser';
	const GET_USER_BY_ID            = 'getUserById';
	const GET_USERS                 = 'getUsers';
	const GET_USERS_FOR_SELECT      = 'getUsersForSelect';
    const GET_USERS_FOR_ADMIN       = 'getUsersForAdmin';
	const DELETE_USERS              = 'deleteUsers';
    const UPDATE_USER               = 'updateUser';
	const GET_PERMISSIONS           = 'getUserPermissions';
    const GET_USER_PROFILE          = 'getUserProfile';
    const GET_USER_PROFILE_BY_ID    = 'getUserProfileById';
    const GET_ORGANISATION_FOR_EDIT = 'getOrganisationForEdit';
    const ARCHIVE                   = 'archive';
    const FOLLOW                    = 'follow';
    const UNFOLLOW                  = 'unfollow';
    const INVITE_TO_FOLLOW          = 'inviteToFollow';
    const REQUEST_TO_FOLLOW         = 'requestToFollow';
    const CHECK_REQUESTS            = 'checkRequests';
    const GET_FOLLOWING_LISTS       = 'getFollowingLists';
    const ACCEPT_INVITE_TO_FOLLOW   = 'acceptInviteToFollow';
    const DECLINE_INVITE_TO_FOLLOW  = 'declineInviteToFollow';
    const ACCEPT_REQUEST_TO_FOLLOW  = 'acceptRequestToFollow';
    const DECLINE_REQUEST_TO_FOLLOW = 'declineRequestToFollow';
    const DELETE_MY_ACCOUNT         = 'deleteMyAccount';
    const SIGN_UP                   = 'signUp';
	
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

            case self::FOLLOW:
                return $this->usersService->follow($this->intArgument( self::USER_ID ));

            case self::UNFOLLOW:
                return $this->usersService->unfollow($this->intArgument( self::USER_ID ));

            case self::INVITE_TO_FOLLOW:
                return $this->usersService->inviteToFollow($this->arrayArgument( self::DATA ));

            case self::CHECK_REQUESTS:
                return $this->usersService->checkRequests($this->arrayArgument( self::DATA ));

            case self::REQUEST_TO_FOLLOW:
                return $this->usersService->requestToFollow($this->arrayArgument( self::DATA ));

            case self::ACCEPT_INVITE_TO_FOLLOW:
                return $this->usersService->acceptInviteToFollow($this->intArgument( self::USER_ID ));

            case self::DECLINE_INVITE_TO_FOLLOW:
                return $this->usersService->declineInviteToFollow($this->intArgument( self::USER_ID ));

            case self::ACCEPT_REQUEST_TO_FOLLOW:
                return $this->usersService->acceptRequestToFollow($this->intArgument( self::USER_ID ));

            case self::DECLINE_REQUEST_TO_FOLLOW:
                return $this->usersService->declineRequestToFollow($this->intArgument( self::USER_ID ));

            case self::GET_FOLLOWING_LISTS:
                return $this->usersService->getFollowingLists();

            case self::SIGN_UP:
                return $this->usersService->signUp($this->arrayArgument(self::DATA));
		}

        if($this->s()->user()->superUser()->get()){
            switch( $functionToCall )
            {
                case self::GET_USERS_FOR_ADMIN:
                    return $this->usersService->getUsersForAdmin( ListArguments::create()->fromArray( $arguments ) );
            }
        }
	}
	
	/**
	 * @param string $function
	 * @return bool
	 */
	public static function getIsAllowedPublicly( string $function ):bool{
		return in_array($function, [self::GET_USER, self::SIGN_UP]);
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