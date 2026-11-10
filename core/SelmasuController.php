<?php

final class SelmasuController
{
	const REQUEST_JSON      = 'json';
	const REQUEST_AJAX      = 'ajax';
	const REQUEST_PAJAX     = 'pajax';
	const REQUEST_FILE      = 'file';
	const REQUEST_SIGN_OUT  = 'sign-out';
    const REQUEST_LOGIN     = 'login';

	const VERSION           = '0.0.1';
	
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

			case self::REQUEST_SIGN_OUT:
                $this->signOut();
			break;

            case self::REQUEST_LOGIN:
                $content = $this->login();
                break;
			
			default:
				$content = $this->getAppContent();
			break;
		}
		
		if( $content ) {
			echo $content;
		}
	}

    private function login()
    {
        if( !$this->s->loggedIn() || $this->s->user()->mfaCode()->get()) {
            return $this->s->parseTemplate( 'login', PagePlaceholderData::create()->content()->set( LoginController::create( $this->s )->getContent() ) );
        } else {
            header( 'Location: /' );
            exit;
        }
    }

	private function signOut()
	{
		$this->s->session()->destroySession();
		header( 'Location: /' );
		exit;
	}
	
	private function getAppContent() {
        return $this->adminContent();
	}

    private function adminContent(){
        if( !$this->s->loggedIn() || $this->s->user()->mfaCode()->get() ) {
            header( 'Location: /login?r=/' . $this->s->pageRequest()->uri() );
            exit;
        }

        return $this->s->parseTemplate( 'app', $this->getAdminPlaceholders() );
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

    /**
     * @return PagePlaceholderData
     */
    private function getAdminPlaceholders():PagePlaceholderData {
        $defaultController = DefaultController::create( $this->s );
        $placeholderData = PagePlaceholderData::create();
        $placeholderData->content()->set( DefaultController::create( $this->s )->getContent() );
        $placeholderData->menu()->set( MenuBuilder::create( $this->s )->menu() );
        $placeholderData->topBarButtons()->set( MenuBuilder::create( $this->s )->topBarButtons() );
        $placeholderData->pageTitle()->set( $defaultController->getPageTitle() );

        return $placeholderData;
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
	include '/usr/share/selmasu-kris-kringle/core/classes.php';

    require_once '/usr/share/selmasu-framework/framework/libs/vendor/autoload.php';
	
	if( isset( $classes[$className] ) ) {
		require_once $classes[$className];
	}
    if( isset( $appClasses[$className] ) ) {
        require_once $appClasses[$className];
    }
}