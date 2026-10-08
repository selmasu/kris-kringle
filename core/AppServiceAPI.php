<?php
class AppServiceAPI {
    /** services */
    const TRIP          = 'trip';
    const USERS         = 'users';
    const POST          = 'post';

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
            case self::TRIP:
                return TripServiceAPI::create($this->s);

            case self::USERS:
                return UsersServiceAPI::create($this->s);

            case self::POST:
                return PostServiceAPI::create($this->s);
        }
    }

    /**
     * @param string $app
     * @param string $service
     * @param string $function
     * @return bool
     */
    public static function getIsAllowedPublicly(string $app, string $service, string $function):bool {
        if ($service == self::TRIP && TripServiceAPI::getIsAllowedPublicly($function)) {
            return true;
        }

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