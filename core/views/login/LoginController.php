<?php

use services\user\UserService;

class LoginController extends Controller implements ContentGenerator
{
	/**
	 * 
	 * @var LoginView
	 */
	private $v;
	
	/**
	 * 
	 * @param Selmasu $s
	 */
	public function __construct( Selmasu $s )
	{
		parent::__construct( $s );
		
		$this->v = new LoginView();
	}
	
	public function getContent()
	{
		switch( $this->s->pageRequest()->page() )
		{
            case 'forgotten-password':
                return $this->v->getRetrievePasswordForm();

            case 'reset-password':
                return $this->v->getResetPasswordForm( $this->s->pageRequest()->action() );
				
			default:
				return $this->v->getLoginForm($this->s());
		}
	}
	
	public function execAjax()
	{
        switch( $this->s()->pageRequest()->action() )
        {
            case 'reset-password-check':
                if($this->formCheck()){
                    if(AuthenticationService::create($this->s())->checkEmail($this->s()->pageRequest()->stringVar('email'))){
                        echo json_encode(['error' => '', 'ok' => true]);
                        exit;
                    }

                    echo json_encode(['error' => 'Email address not recognised', 'ok' => false]);
                    exit;
                }

                echo json_encode(['error' => 'reCAPTCHA error', 'ok' => false]);
                exit;

            case 'reset-password-confirm':
                $email = $this->s()->pageRequest()->argument();
                if( AuthenticationService::create( $this->s() )->checkEmail( $email ) ) {
                    $userService = UserService::create( $this->s() );
                    $token = $userService->setResetPasswordToken( $email );
                    $userService->sendResetPasswordEmail( $email, $token );
                    echo $this->v->getResetPasswordConfirmation();
                }
                break;

            case 'password-reset-successful':
                echo $this->v->passwordResetSuccess();
                break;
        }
	}

    private function formCheck(){
        if(in_array($this->s()->server()->remoteAddress(), Constants::CAPTCHA_EXCLUDED_IPS)){
            return true;
        }

        if(!strlen( $this->s()->pageRequest()->stringVar( 'g-recaptcha-response' ) )){
            Utils::trace('No recaptcha data');
            return false;
        }

        $verifyResponse = file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret=' . Constants::RECAPTCHA_SECRET . '&response=' . $this->s()->pageRequest()->stringVar( 'g-recaptcha-response' ));
        $responseData = json_decode($verifyResponse);
        if(!$responseData->success){
            Utils::trace('Recaptcha verification failed');
            return false;
        }

        return true;
    }
	
	/**
	 * 
	 * @param Selmasu $s
	 */
	public static function create( Selmasu $s )
	{
		return new LoginController( $s );
	}
}