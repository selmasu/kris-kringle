<?php
class DefaultController extends Controller implements ContentGenerator {
    const REQUEST_TRIP              = 'trip';
    const REQUEST_USERS             = 'users';
    const REQUEST_EMAILS            = 'emails';
    const REQUEST_PROFILE           = 'profile';
    const REQUEST_FILES             = 'files';
    const REQUEST_APPLE_SIGN_IN     = 'apple-sign-in';
    const REQUEST_PRIVACY           = 'privacy';
    const REQUEST_DELETE_ACCOUNT    = 'delete-account';

    /** @var DefaultView */
    private $v;

    /**
     * @param Selmasu $s
     */
    public function __construct(Selmasu $s) {
        parent::__construct($s);

        $this->v = new DefaultView();
    }

    public function getContent() {
        $app = $this->getApp();

        switch ($app) {
            case self::REQUEST_APPLE_SIGN_IN:
                return $this->appleSignIn();

            case self::REQUEST_TRIP:
                return TripController::create($this->s)->getContent();

            default:
                return $this->v->getDefaultView();
        }
    }

    private function getApp() {
    	return !$this->s->pageRequest()->request() ? self::REQUEST_TRIP : $this->s->pageRequest()->request();
    }

    public function getPublicContent() {
        switch ($this->s->pageRequest()->request()) {
            case self::REQUEST_DELETE_ACCOUNT:
                return $this->v->deleteAccount();

            case self::REQUEST_PRIVACY:
                return $this->v->privacy();

            case self::REQUEST_APPLE_SIGN_IN:
                return $this->appleSignIn();

            default:
                return TripController::create($this->s)->getPublicContent();
        }

    }

    public function getPageTitle() {
        switch ($this->getApp()) {
            default:
                return 'simpli.travel';
        }
    }

    public function execAjax() {

    }

    /**
     * @return array
     */
    public static function publicRequests(){
    	return [];
    }

    private function appleSignIn(){
        $code = $this->s()->pageRequest()->stringVar('code');
        $state = $this->s()->pageRequest()->stringVar('state');
        $idToken = $this->s()->pageRequest()->stringVar('id_token');

        $response = AuthenticationService::create($this->s())->loginApple($code, Settings::$appleServicesId, $state, "", "");
        if( $response instanceof User ) {
            $this->s()->setUser( $response );
            $token = $this->s()->session()->createUserSession( $this->s()->user()->userId()->get(), true );
            $this->s()->user()->selmasuToken()->set($token);

            header("Location: /");
            exit;
        }
    }

    /**
     * @param Selmasu $s
     * @return DefaultController
     */
    public static function create(Selmasu $s) {
        return new DefaultController($s);
    }
}