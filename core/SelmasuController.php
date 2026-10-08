<?php

final class SelmasuController
{
	const REQUEST_JSON      = 'json';
	const REQUEST_AJAX      = 'ajax';
	const REQUEST_PAJAX     = 'pajax';
	const REQUEST_FILE      = 'file';
	const REQUEST_SING_OUT  = 'sign-out';

	const VERSION           = '0.0.2';
	
	/** @var Selmasu */
	protected $s;
	
	public function __construct()
	{
		$this->s = new Selmasu();
		$this->run();
	}
	
	private function run()
	{
		if( php_sapi_name() == 'cli' ) {
			$this->executeCli();
		} else if(!in_array($this->s->pageRequest()->request(), ['templates'])) {
			$this->parseRequest();
		}
	}
	
	private function parseRequest()
	{
		$content = '';

		switch( $this->s->pageRequest()->request() )
		{
			case self::REQUEST_JSON:
				$this->s->handleRemoteRequest();
			break;
			
			case self::REQUEST_AJAX:
				$this->s->handleAjaxRequest();
			break;

			case self::REQUEST_PAJAX:
				$this->s->handlePublicAjaxRequest();
			break;
			
			case self::REQUEST_FILE;
				$content = FileController::create( $this->s )->getContent();
			break;

			case self::REQUEST_SING_OUT:
                $this->signOut();
			break;
			
			default:
				$content = $this->getAppContent();
			break;
		}
		
		if( $content ) {
			echo $content;
		}
	}

	private function signOut()
	{
		$this->s->session()->destroySession();
		header( 'Location: /' );
		exit;
	}
	
	private function getAppContent()
	{
		return $this->s->parseTemplate( 'app', $this->getPagePlaceholderData() );
	}
	
	/**
	 * @return PagePlaceholderData
	 */
	private function getPagePlaceholderData():PagePlaceholderData
	{
        $defaultController = DefaultController::create( $this->s );

	    $data = PagePlaceholderData::create();
        $data->content()->set( $defaultController->getPublicContent() );
        $data->pageTitle()->set( $defaultController->getPageTitle() );

        return $data;
	}

    private function setCliUser()
    {
        $user = User::create();
        $user->userId()->set(-1);
        $user->organisationId()->set(-1);
        $user->firstName()->set('simpli');
        $user->firstName()->set('travel');
        $user->superUser()->set(true);
        $user->setPermissions(UserPermissions::create()->grantFullAccess());

        $this->s->setUser($user);
    }
	
	private function executeCli()
	{
	    $this->setCliUser();

		switch( $this->s->server()->arg1() ) {
			case 'daily':
                echo 'Do a thing daily @ ' . date( 'Y-m-d H:i:s' ) . PHP_EOL;
                break;
		}
	}
	
}

spl_autoload_register( 'selmasuAutoloader' );

function selmasuAutoloader( $className )
{
	include '/usr/share/selmasu-framework/framework/classes.php';
	include '/usr/share/selmasu-simpli.travel/core/classes.php';

    require_once '/usr/share/selmasu-framework/framework/libs/aws/aws-autoloader.php';
	
	if( isset( $classes[$className] ) ) {
		require_once $classes[$className];
	}
    if( isset( $appClasses[$className] ) ) {
        require_once $appClasses[$className];
    }
}