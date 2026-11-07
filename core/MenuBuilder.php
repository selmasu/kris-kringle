<?php
class MenuBuilder {
	/** @var Selmasu */
	private $s;
	
	public function __construct( Selmasu $s ){
		$this->s = $s;
	}
	
	/**
	 * Returns the top bar buttons (they're kindof a menu, yeah?)
	 * @return string
	 */
    public function topBarButtons(){
        if( $this->s->loggedIn() ) {
            ob_start();
            ?>
            <ul class="list-unstyled topnav-menu float-end mb-0">
                <?php
                if($this->s->user()->admin()->get()){
                    ?>
                    <li class="dropdown notification-list">
                        <a href="/settings" class="nav-link right-bar-toggle waves-effect waves-light"><i class="fe-settings noti-icon"></i></a>
                    </li>
                    <?php
                }
                ?>
                <li class="dropdown notification-list topbar-dropdown">
                    <a class="nav-link dropdown-toggle waves-effect waves-light" data-bs-toggle="dropdown" href="#" role="button" aria-haspopup="false" aria-expanded="false">
                        <i class="fe-bell noti-icon"></i>
                        <span class="badge bg-danger rounded-circle noti-icon-badge">5</span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end dropdown-lg">
                        <!-- item-->
                        <div class="dropdown-item noti-title">
                            <h5 class="m-0">
                                <span class="float-end">
                                    <a href="" class="text-dark">
                                        <small>Clear All</small>
                                    </a>
                                </span>Notification
                            </h5>
                        </div>

                        <div class="noti-scroll" data-simplebar="init">
                            <div class="simplebar-wrapper" style="margin: 0px;">
                                <div class="simplebar-height-auto-observer-wrapper">
                                    <div class="simplebar-height-auto-observer"></div>
                                </div>
                                <div class="simplebar-mask">
                                    <div class="simplebar-offset" style="right: 0px; bottom: 0px;">
                                        <div class="simplebar-content-wrapper" tabindex="0" role="region" aria-label="scrollable content" style="height: auto; overflow: hidden;">
                                            <div class="simplebar-content" style="padding: 0px;">
                                                <!-- item-->
                                                <a href="javascript:void(0);" class="dropdown-item notify-item active">
                                                    <div class="notify-icon">
                                                        <img src="assets/images/users/user-1.jpg" class="img-fluid rounded-circle" alt=""> </div>
                                                    <p class="notify-details">Cristina Pride</p>
                                                    <p class="text-muted mb-0 user-msg">
                                                        <small>Hi, How are you? What about our next meeting</small>
                                                    </p>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="simplebar-placeholder" style="width: 0px; height: 0px;"></div>
                            </div>
                            <div class="simplebar-track simplebar-horizontal" style="visibility: hidden;">
                                <div class="simplebar-scrollbar" style="width: 0px; display: none;"></div>
                            </div>
                            <div class="simplebar-track simplebar-vertical" style="visibility: hidden;">
                                <div class="simplebar-scrollbar" style="height: 0px; display: none;"></div>
                            </div>
                        </div>
                        <!-- All-->
                        <a href="javascript:void(0);" class="dropdown-item text-center text-primary notify-item notify-all">
                            View all
                            <i class="fe-arrow-right"></i>
                        </a>
                    </div>
                </li>

                <li class="dropdown notification-list topbar-dropdown">
                    <a href="" class="nav-link dropdown-toggle nav-user me-0 waves-effect waves-light" data-bs-toggle="dropdown" href="#" role="button" aria-haspopup="false" aria-expanded="false">
                        <?php
                        if($this->s->user()->miniProfilePhotoUrl()->get()){
                            ?>
                            <img src="<?php echo $this->s->user()->miniProfilePhotoUrl()->get();?>" alt="user-img" class="rounded-circle"/>
                            <?php
                        } else {
                            ?>
                            <div class="no-profile-photo rounded-circle text-white bg-secondary text-center" style="width: 36px; height: 36px; font-size: 24px"><?php echo $this->s->user()->firstName()->subStr(0,1);?></div>
                            <?php
                        }
                        ?>
                        <span class="pro-user-name ms-1">
                            <?php echo $this->s->user()->firstName()->get();?> <i class="mdi mdi-chevron-down"></i>
                        </span>
                    </a>

                    <div class="dropdown-menu dropdown-menu-end profile-dropdown" style="">
                        <!-- item-->
                        <div class="dropdown-header noti-title">
                            <h6 class="text-overflow m-0">Welcome!</h6>
                        </div>

                        <!-- item-->
                        <a href="/profile" class="dropdown-item notify-item">
                            <i class="fe-user"></i>
                            <span>My Account</span>
                        </a>

                        <div class="dropdown-divider"></div>

                        <!-- item-->
                        <a href="/logout" class="dropdown-item notify-item">
                            <i class="fe-log-out"></i>
                            <span>Logout</span>
                        </a>

                    </div>
                </li>
            </ul>
            <?php
            return ob_get_clean();
        }
    }
	
	/**
	 * 
	 * Returns the appropriate menu
	 * @return string
	 */
	public function menu()
	{
        ob_start();

        return ob_get_clean();
	}

	/**
	 * @return array
	 */
	private function publicMenu()
	{
		return [];
	}

	/**
	 * @param array $menuItems
	 * @return string
	 */
	private function menuToHtml( $menuItems )
	{
		return '';
	}
	
	/**
	 * 
	 * Static creator
	 * @param Selmasu $s
	 * @return MenuBuilder
	 */
	public static function create( Selmasu $s )
	{
		return new self( $s );
	}
}