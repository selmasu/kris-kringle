<?php
class KrisKringle extends ValueObject {
    protected $krisKringleId = null;
    public function krisKringleId():IntProperty {
        return $this->intProperty( $this->krisKringleId );
    }

    protected $createdBy = null;
    public function createdBy():IntProperty {
        return $this->intProperty( $this->createdBy );
    }

    protected $name = null;
    public function name():StringProperty {
        return $this->stringProperty( $this->name );
    }

    protected $year = null;
    public function year():IntProperty {
        return $this->intProperty( $this->year );
    }

    protected $picture = null;
    public function picture():StringProperty {
        return $this->stringProperty( $this->picture );
    }

    /**
     * Sets data from an array
     * @param array $data
     * @return KrisKringle
     */
    public function fromArray( array $data = [] ):KrisKringle {
        $this->setValuesFromArray( $data );
        return $this;
    }

    public function toArray():array {
        return $this->valuesToArray();
    }

    /**
     * Returns the set part of an SQL string
     * @return string
     */
    public function sqlSet():string {
        $setArray = $this->sqlUpdateArray();

        return implode( ",", $setArray );
    }

    public function excludeFromSql():array {
        return [];
    }

    public static function create():KrisKringle {
        return new self;
    }
}