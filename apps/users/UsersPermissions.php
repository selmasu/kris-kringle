<?php
class UsersPermissions extends Permissions
{
	/**
	 * Gets an array of permissions for this app
	 * @return array
	 */
	public function getPermissions()
	{	
		return [parent::MANAGE => 'Account Manager'];
	}
	
	/**
	 * Sets all the permissions for this app
	 * @param int $permission
	 * @return UsersPermissions
	 */
	public function setPermissions( $permission )
	{
		$this->setCanManage( $permission );
		
		return $this;
	}
	
	/**
	 * Converts permissions to flat array
	 * @return array
	 */
	public function toArray()
	{
		return ['usersManage' => $this->canManage()];
	}
	
	/**
	 * Static creator
	 * @return UsersPermissions
	 */
	public static function create()
	{
		return new self;
	}
}