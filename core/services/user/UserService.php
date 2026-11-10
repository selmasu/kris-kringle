<?php
/**
 * Legacy service called by framework
 * Class UserService
 */
class UserService extends UsersService {

    public function appleSignInPolicy(): array
    {
        return [
            'nativeClientId' => 'com.selmasu.Kris-Kringle',
            'allowAccountCreation' => false,
        ];
    }

    /**
     * @param Selmasu $s
     * @return UserService
     */
    public static function create(Selmasu $s): UsersService
    {
        return new self($s);
    }

}