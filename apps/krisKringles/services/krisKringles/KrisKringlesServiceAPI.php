<?php
class KrisKringleServiceAPI extends ServiceAPI
{
	//Function Names
    const GET_KRIS_KRINGLES             = 'getKrisKringles';
    const CREATE_KRIS_KRINGLE           = 'createKrisKringle';
    const UPDATE_PICTURE = 'updateKrisKringlePicture';
    const GET_PICTURE = 'getKrisKringlePicture';
	
	//Argument Names
	const DATA                          = 'data';
    const ID                            = 'id';
	
	/**
	 * @var KrisKringleService
	 */
	private $krisKringleService;
	
	public function __construct( Selmasu $s )
	{
		parent::__construct( $s );
		
		$this->krisKringleService = KrisKringleService::create( $s );
	}

    /**
     * @param string $functionToCall
     * @param array $arguments
     * @return array
     */
	public function api( $functionToCall, $arguments = array() )
	{
		$this->arguments( $arguments );
		
		switch( $functionToCall ) {
            case self::GET_KRIS_KRINGLES:
                return $this->krisKringleService->getKrisKringles(ListArguments::create()->fromArray($arguments));

            case self::CREATE_KRIS_KRINGLE:
                if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                    return ['error' => 'Creating a Kris Kringle requires a POST request.'];
                }
                $name = $arguments['name'] ?? null;
                return $this->krisKringleService->createKrisKringle(is_string($name) ? $name : '', $_FILES['picture'] ?? null);

            case self::UPDATE_PICTURE:
                if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                    return ['error' => 'Uploading a group photo requires a POST request.'];
                }
                return $this->krisKringleService->updateKrisKringlePicture($this->intArgument(self::ID), $_FILES['picture'] ?? null);

            case self::GET_PICTURE:
                $picture = $this->krisKringleService->getKrisKringlePicture($this->intArgument(self::ID));
                if ($picture === null) {
                    http_response_code(404);
                    return ['error' => 'Group photo not found.'];
                }
                header('Content-Type: image/jpeg');
                header('Content-Length: ' . strlen($picture));
                header('Cache-Control: private, no-store');
                header('X-Content-Type-Options: nosniff');
                echo $picture;
                exit;
		}
	}

	/**
	 * Static creator
	 * @param Selmasu $s
	 * @return KrisKringleServiceAPI
	 */
	public static function create( Selmasu $s ):KrisKringleServiceAPI{
		return new self( $s );
	}
}