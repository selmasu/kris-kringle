<?php
namespace KrisKringle;
class Email extends \Email {
    //Types correspond with emailId
    const TYPE_ONE_TIME_CODE                        = 1;

    protected $emailId = null;
    protected $active = null;
    protected $dynamicTo = null;
    protected $name = null;

    public function emailId() {
        return $this->intProperty( $this->emailId );
    }

    public function active() {
        return $this->boolProperty( $this->active );
    }

    public function dynamicTo() {
        return $this->boolProperty( $this->dynamicTo );
    }

    public function name() {
        return $this->stringProperty( $this->name );
    }

    /**
     * Sets data from an array
     * @param array $data
     * @return Email
     */
    public function fromArray( $data = array() ) {
        $this->setValuesFromArray( $data );
        return $this;
    }

    public function toArray() {
        return $this->valuesToArray();
    }

    /**
     * Returns the set part of an SQL string
     * @return string
     */
    public function sqlSet() {
        $setArray = $this->sqlUpdateArray();

        return implode( ",", $setArray );
    }

    /**
     * Static creator
     * @return Email
     */
    public static function create():Email {
        return new self;
    }
}