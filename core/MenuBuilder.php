<?php
class MenuBuilder
{
	/**
	 * @var Selmasu
	 */
	private $s;
	
	public function __construct( Selmasu $s )
	{
		$this->s = $s;
	}
	
	/**
	 * Returns the top bar buttons (they're kindof a menu, yeah?)
	 * @return string
	 */
	public function topBarButtons()
	{
		if( $this->s->loggedIn() )
		{
            $baseLink = 'admin';
			ob_start();
            ?>
            <ul class="nav navbar-nav navbar-right">
                <li>
                    <form role="search" class="app-search" onsubmit="return false;">
                        <input type="text" placeholder="Search..." class="form-control" id="header-search">
                        <a href="#" id="header-search-icon"><i class="fa fa-search"></i></a>
                    </form>
                </li>

                <li class="dropdown user-box">
                    <a href="" class="dropdown-toggle waves-effect user-link" data-toggle="dropdown" aria-expanded="true">
                        <img src="<?php echo $this->s->user()->miniProfilePhotoUrl()->get() ? $this->s->user()->miniProfilePhotoUrl()->get() : '/templates/app/assets/images/profile-icon.png';?>" alt="user-img" class="img-circle user-img">
                    </a>

                    <ul class="dropdown-menu dropdown-menu-right arrow-dropdown-menu arrow-menu-right user-list notify-list">
                        <li><a href="/logout">Logout</a></li>
                    </ul>
                </li>

            </ul>
            <?php
			return ob_get_clean();
		}
	}
	
	/**
	 * Returns the appropriate menu
	 * @return string
	 */
	public function menu()
	{
        $baseLink = 'admin';
		ob_start();
		?>
        <!-- ========== Left Sidebar Start ========== -->
        <div class="left side-menu">
            <div class="slimscroll-menu" id="remove-scroll">
                <!--- Sidemenu -->
                <div id="sidebar-menu">
                    <!-- Left Menu Start -->
                    <ul class="metisMenu nav" id="side-menu">
                        <li class="menu-title">Settings</li>
                        <li>
                            <a href="/<?php echo $baseLink;?>/users"><i class="fa fa-users"></i><span> Users </span> <span class="menu-arrow"></span></a>
                        </li>
                        <li>
                            <a href="/<?php echo $baseLink;?>/emails"><i class="fa fa-envelope-o"></i><span> System Emails </span> <span class="menu-arrow"></span></a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
		<?php
		return ob_get_clean();
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