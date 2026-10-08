<?php
class KrisKringleModel extends Model
{
    /**
     * Gets all krisKringle details for a given query
     * @param SqlQueryBits $query
     * @param int $returnType
     * @return array[]|KrisKringle[]
     */
    protected function getKrisKringlesFromDB( SqlQueryBits $query, int $returnType = Model::RETURN_TYPE_ARRAY):array
    {
        $sql = "
			SELECT
				krisKringle.*,
			    IF(user.displayName != '', user.displayName, CONCAT(user.firstName, ' ', user.lastName)) AS name,
			    user.picture
			    " . $query->select()->get() . "
			FROM
				" . $this->tables()->krisKringle() . " AS krisKringle
				LEFT JOIN " . $this->coreTables()->user() . " AS user
				    ON user.userId = krisKringle.createdBy
				LEFT JOIN " . $this->tables()->krisKringleMember() . " AS krisKringleMember
				    ON krisKringleMember.krisKringleId = krisKringle.krisKringleId
				        AND krisKringleMember.userId = " . (int)$this->s()->userId() . "
				" . $query->join()->get() . "
			WHERE
			    krisKringle.deleted = 0
				" . $this->permissionWhere() . "
				" . $query->where()->get() . "
			GROUP BY
			    krisKringle.krisKringleId
			" . $query->orderByString() . "
			" . $query->limitString();
        $krisKringles = [];
        while( $row = $this->adb->fetchAssoc( $sql ) ) {
            $krisKringles[] = $returnType == Model::RETURN_TYPE_OBJECT ? KrisKringle::create()->fromArray($row) : KrisKringle::create()->fromArray($row)->toArray();
        }

        return $krisKringles;
    }

    /**
     * Gets the total customer count for a given query
     * @param SqlQueryBits $query
     * @return int
     */
    protected function getKrisKringlesTotalFromDB( SqlQueryBits $query ):int
    {
        $sql = "
			SELECT
				COUNT(DISTINCT(krisKringleId)) AS total
			FROM
			    " . $this->tables()->krisKringle() . " AS krisKringle
				LEFT JOIN " . $this->coreTables()->user() . " AS user
				    ON user.userId = krisKringle.createdBy
				LEFT JOIN " . $this->tables()->krisKringleMember() . " AS krisKringleMember
				    ON krisKringleMember.krisKringleId = krisKringle.krisKringleId
				        AND krisKringleMember.userId = " . (int)$this->s()->userId() . "
				" . $query->join()->get() . "
            WHERE
                krisKringle.deleted = 0
                " . $this->permissionWhere() . "
                " . $query->where()->get();
        return (int)$this->adb->fetchValue( 'total', $sql );
    }

    private function permissionWhere():string {
        return "AND (krisKringle.createdBy = " . (int)$this->s()->userId() . "
                    OR krisKringleMember.krisKringleMemberId IS NOT NULL)";
    }
}