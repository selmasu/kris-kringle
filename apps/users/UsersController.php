<?php
class UsersController extends Controller implements ContentGenerator
{
    const AJAX_REQUEST_USER_PROFILE_SAVE    = 'saveProfile';
    const AJAX_REQUEST_ADMIN_USER_LIST      = 'adminUserList';
    const AJAX_REQUEST_ADMIN_USER_FORM      = 'adminUserForm';

	/**
	 * @param Selmasu $s
	 */
	public function __construct( Selmasu $s ){
		parent::__construct( $s );
	}
	
	public function getContent(){
        if( !$this->canManage() )
			return '';
		
		switch( $this->s->pageRequest()->page() )
		{
			default:
				return UserListController::create( $this->s )->getContent();
		}
	}
	
	public function execAjax(){
		if( !$this->canAccess() )
			exit;

        switch( $this->s->pageRequest()->action() )
        {
            case self::AJAX_REQUEST_USER_PROFILE_SAVE:
                UserFormController::create( $this->s )->execAjax();
        }

        if( !$this->canManage() )
            exit;
			
		switch( $this->s->pageRequest()->action() )
		{
            case self::AJAX_REQUEST_ADMIN_USER_LIST:
			case Controller::AJAX_REQUEST_LIST:
				UserListController::create( $this->s )->execAjax();

            case self::AJAX_REQUEST_ADMIN_USER_FORM:
			case Controller::AJAX_REQUEST_SAVE:
			case Controller::AJAX_REQUEST_FORM:
				UserFormController::create( $this->s )->execAjax();
		}
	}

    private function canManage(){
        return $this->s()->user()->permissions()->users()->canManage();
    }
	
	private function canAccess(){
		return $this->s()->loggedIn();
	}
	
	/**
	 * @param Selmsau $s
	 * @return UsersController
	 */
	public static function create( Selmasu $s ){
		return new UsersController( $s );
	}
}