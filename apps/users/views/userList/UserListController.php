<?php
class UserListController extends Controller implements ContentGenerator
{
	/**
	 * @var UserListView
	 */
	private $v;
	
	/**
	 * @param Selmasu $s
	 */
	public function __construct( Selmasu $s )
	{
		parent::__construct( $s );
		
		$this->v = new UserListView();
	}
	
	public function getContent()
	{
		return $this->v->getContentDiv();
	}

    public function getAdminContent()
    {
        return $this->v->getAdminContentDiv();
    }
	
	public function execAjax()
	{
		switch( $this->s->pageRequest()->action() )
		{
            case UsersController::AJAX_REQUEST_ADMIN_USER_LIST:
                echo $this->v->getAdminUserList($this->s());
                exit;

			default:
				echo $this->v->getUserList($this->s());
				exit;
		}
	}
	
	/**
	 * 
	 * @param Selmasu $s
	 * @return UserListController
	 */
	public static function create( Selmasu $s )
	{
		return new UserListController( $s );
	}
}