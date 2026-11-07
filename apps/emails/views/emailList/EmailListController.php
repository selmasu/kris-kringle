<?php
class EmailListController extends Controller implements ContentGenerator
{
	/** @var EmailListView */
	private $v;
	
	/**
	 * 
	 * @param Selmasu $s
	 */
	public function __construct( Selmasu $s )
	{
		parent::__construct( $s );
		
		$this->v = new EmailListView();
	}
	
	public function getContent()
	{
		return $this->v->getContentDiv();
	}
	
	public function execAjax()
	{
		switch( $this->s->pageRequest()->action() )
		{
			default:
				echo $this->v->getEmailList();
				exit;
		}
	}
	
	/**
	 * @param Selamsu $s
	 * @return EmailListController
	 */
	public static function create( Selmasu $s )
	{
		return new EmailListController( $s );
	}
}