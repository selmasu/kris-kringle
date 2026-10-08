<?php
class EmailsServiceAPI extends ServiceAPI
{
	//Function Names
    const GET_EMAILS                        = 'getEmails';
	const GET_EMAIL                         = 'getEmail';
    const ADD_ATTACHMENT                    = 'addAttachment';
    const DELETE_ATTACHMENT                 = 'deleteAttachment';
    const GET_ATTACHMENTS                   = 'getAttachments';
    const GET_LOGGED_EMAILS                 = 'getLoggedEmails';
	
	//Argument Names
	const EMAIL_ID                          = 'emailId';
    const FILE_ID                           = 'fileId';
	
	/** @var EmailsService */
	private $emailsService;
	
	public function __construct( Selmasu $s )
	{
		parent::__construct( $s );
		
		$this->emailsService = \simpli\EmailsService::create( $s );
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
            case self::GET_EMAILS:
                return $this->emailsService->getEmails( ListArguments::create()->fromArray( $arguments ) );
            
			case self::GET_EMAIL:
				return $this->emailsService->getEmail( $this->intArgument( self::EMAIL_ID ) );

            case self::ADD_ATTACHMENT:
                return $this->emailsService->addAttachment( $this->intArgument( self::EMAIL_ID ), $this->intArgument( self::FILE_ID ) );

            case self::DELETE_ATTACHMENT:
                return $this->emailsService->deleteAttachment( $this->intArgument( self::EMAIL_ID ), $this->intArgument( self::FILE_ID ) );

            case self::GET_ATTACHMENTS:
                return $this->emailsService->getAttachments( ListArguments::create()->fromArray( $arguments ), $this->intArgument( self::EMAIL_ID ) );

            case self::GET_LOGGED_EMAILS:
                return $this->emailsService->getLoggedEmails( ListArguments::create()->fromArray( $arguments ) );
		}
	}
	
	/**
	 * Static creator
	 * @param Selmasu $s
	 * @return EmailsServiceAPI
	 */
	public static function create( Selmasu $s )
	{
		return new self( $s );
	}
}