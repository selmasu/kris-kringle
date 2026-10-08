<?php
class KrisKringleServiceAPI extends ServiceAPI
{
	//Function Names
    const GET_KRIS_KRINGLES             = 'getKrisKingles';
	
	//Argument Names
	const DATA                          = 'data';
    const ID                            = 'id';
	
	/**
	 * @var KrisKringleService
	 */
	private $postService;
	
	public function __construct( Selmasu $s )
	{
		parent::__construct( $s );
		
		$this->postService = KrisKringleService::create( $s );
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
                return $this->postService->getKrisKringles(ListArguments::create()->fromArray($arguments));
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