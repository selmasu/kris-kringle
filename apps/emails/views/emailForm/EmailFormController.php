<?php
class EmailFormController extends Controller implements ContentGenerator
{
	/**
	 * @var EmailFormView
	 */
	private $v;
	
	/**
	 * @param Selmasu $s
	 */
	public function __construct( Selmasu $s )
	{
		parent::__construct( $s );
		
		$this->v = new EmailFormView();
	}
	
	public function getContent()
	{
		return $this->v->getContentDiv();
	}
	
	public function execAjax()
	{
		switch( $this->s->pageRequest()->action() )
		{
            case self::AJAX_REQUEST_SAVE:
				echo $this->saveEmail();
				exit;
				
			default:
				echo $this->v->getEmailForm();
				exit;
		}
	}
	
	private function saveEmail()
	{
		if( $this->s->pageRequest()->intVar( 'emailId' ) ) {
			return EmailsService::create( $this->s )->saveFromArray( $this->s->pageRequest()->vars() );
		}
		
		return -1;
	}
	
	/**
	 * @param Selmasu $s
	 * @return EmailFormController
	 */
	public static function create( Selmasu $s ):EmailFormController
	{
		return new self( $s );
	}
}