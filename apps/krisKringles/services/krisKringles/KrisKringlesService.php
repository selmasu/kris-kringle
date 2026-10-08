<?php
class KrisKringleService extends KrisKringleModel {
    /**
     * @param ListArguments $listArguments
     * @param int $returnType
     * @return array
     */
    public function getKrisKringles( ListArguments $listArguments, int $returnType = Model::RETURN_TYPE_ARRAY ):array {
        $query = SqlQueryBits::create();
        $query->limit()->set( $listArguments->take()->get() );
        $query->offset()->set( $listArguments->skip()->get() );
        $query->orderBy()->set( $this->sortToString( $listArguments->sort()->get() ) );
        
        return ['data' => $this->getKrisKringlesFromDB( $query, $returnType ), 'total' => $this->getKrisKringlesTotalFromDB( $query )];
    }

    /**
	 * @param Selmasu $s
	 * @return KrisKringleService
	 */
	public static function create( Selmasu $s ):KrisKringleService{
		return new self( $s );
	}

}