<?php
class UserFormController extends Controller implements ContentGenerator
{
	/**
	 * @var UserFormView
	 */
	private $v;
	
	/**
	 * 
	 * @param Selmasu $s
	 */
	public function __construct( Selmasu $s )
	{
		parent::__construct( $s );
		
		$this->v = new UserFormView();
	}
	
	public function getContent()
	{
		return '';
	}
	
	public function execAjax()
	{
		switch( $this->s->pageRequest()->action() )
		{
			case Controller::AJAX_REQUEST_SAVE:
				echo $this->saveUser();
				exit;

            case UsersController::AJAX_REQUEST_ADMIN_USER_FORM:
                echo $this->v->getAdminUserForm();
                exit;
				
			default:
				echo $this->v->getUserForm();
				exit;
		}
	}
	
	private function saveUser()
	{
		if($this->s()->user()->permissions()->users()->canManage()){
			if($this->s->pageRequest()->stringVar( 'email' )) {
				return json_encode(UserService::create( $this->s )->saveFromArray( $this->s()->pageRequest()->vars() ));
			}

			return ['userId' => 0, 'error' => 'User must enter an email address'];
		}

		return ['userId' => 0, 'error' => 'You do not have permission to perform this task'];
	}
	
	/**
	 * 
	 * @param Selmasu $s
	 * @return UserFormController
	 */
	public static function create( Selmasu $s )
	{
		return new self( $s );
	}
}