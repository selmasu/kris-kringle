<?php
class App extends ValueObject{

    const TRIP          = 1;
    const USERS         = 2;
    const PAGES         = 3;
    const ARTICLES      = 4;
    const CONTENT_AREAS = 5;
    const FILE_REPO     = 6;
    const GALLERIES     = 7;
    const TEMPLATES     = 8;
    const PDF_TEMPLATES = 9;
    const POST          = 10;

	protected $appId = null;
	public function appId(){
		return $this->intProperty( $this->appId );
	}

    protected $name = null;
    public function name(){
        return $this->stringProperty( $this->name );
    }

    /**
     * Sets data from an array
     * @param array $data
     * @return App
     */
    public function fromArray( $data = array() ){
        $this->setValuesFromArray( $data );
        return $this;
    }

    /**
     * Returns the set part of an SQL string
     * @return string
     */
    public function sqlSet(){
        $setArray = $this->sqlUpdateArray();

        return implode( ",", $setArray );
    }
	
	/**
	 * Static creator
	 * @return App
	 */
	public static function create(){
		return new self;
	}
}