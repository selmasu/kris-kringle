<?php

use emails\services\emails\EmailsServiceAPI;

class AppServiceAPI {
    /** services */
    const TRIP          = 'trip';
    const USERS         = 'users';
    const EMAILS        = 'emails';

    /** @var Selmasu */
    private $s;

    public function __construct(Selmasu $s){
        $this->s = $s;
    }

    /**
     * Returns a service API
     * @param string $app
     * @param string $service
     * @return ServiceAPI|void
     */
    public function getServiceAPI(string $app, string $service)
    {
        switch ($service) {
            case self::EMAILS:
                return EmailsServiceAPI::create($this->s);

            case self::USERS:
                return UsersServiceAPI::create($this->s);
        }
    }

    /**
     * @param string $app
     * @param string $service
     * @param string $function
     * @return bool
     */
    public static function getIsAllowedPublicly(string $app, string $service, string $function):bool {
        if ($service == self::USERS && UsersServiceAPI::getIsAllowedPublicly($function)) {
            return true;
        }

        return false;
    }

    /**
     * @param Selmasu $s
     * @return AppServiceAPI
     */
    public static function create(Selmasu $s):AppServiceAPI {
        return new self($s);
    }
}