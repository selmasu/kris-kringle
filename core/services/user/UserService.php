<?php

/**
 * Legacy service called by framework
 * Class UserService
 */
class UserService extends UsersService {

	/**
	 * @param Selmasu $s
	 * @return UserService
	 */
	public static function create( Selmasu $s ):UsersService {
		return new self( $s );
	}

}