<?php
class UserListView
{
    public function getContentDiv()
    {
        ob_start();
        ?>
        <div id="mainContentDiv">
            <script type="text/javascript">
                var _userId = 0;

                $( document ).ready( function() {
                    $( "#mainContentDiv" ).load( "/ajax/users/list" );
                });
            </script>
        </div>
        <script src="/scripts/apps/users/UserList.js" type="text/javascript"></script>
        <script src="/scripts/apps/users/UserForm.js" type="text/javascript"></script>
        <?php
        return ob_get_clean();
    }

	public function getUserList(Selmasu $s)
	{
		ob_start();
		?>
        <div class="app-menu">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-10">
                        <?php
                        if($s->user()->permissions()->users()->canManage()) {
                            ?>
                            <button type="button" class="btn btn-custom w-md waves-effect waves-light" id="addButton">
                                Add Team Member
                            </button>
                            <?php
                        }
                        ?>
                    </div>
                    <div class="col-2">
                        <div class="app-search">
                            <input type="text" placeholder="Search..." class="form-control" id="headerSearch">
                            <a href="" id="headerSearchIcon"><i class="fa fa-search"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="wrapper">
            <div class="container-fluid">
                <div id="list"></div>
            </div>
        </div>
        <script type="text/x-kendo-template" id="userTemplate">
            <div class="user" data-id="#:userId#">
                <div class="card">
                    #if(profilePhotoUrl) {#<img class="card-img-top" src="#:profilePhotoUrl#" alt="Card image cap">#}else{#<div class="text-white bg-secondary text-center no-photo"><div class="initials"><span class="align-middle">#:firstName.substr(0,1)# #:lastName.substr(0,1)#</span></div></div>#}#
                    #if(archived == 1){#<span class="badge badge-warning">archived</span>#}#
                    <div class="card-body">
                        <h5 class="card-title">#:name#</h5>
                        <div class="card-text">#:email#</div>
                        <div class="card-text">#:mobile#</div>
                    </div>
                </div>
            </div>
        </script>
        <script type="text/javascript">
            $( document ).ready(UserList.init);
        </script>
		<?php
		return ob_get_clean();
	}

    public function getAdminContentDiv()
    {
        ob_start();
        ?>
        <div id="mainContentDiv">
            <script type="text/javascript">
                var _userId = 0;

                $( document ).ready( function() {
                    $( "#mainContentDiv" ).load( "/ajax/users/adminUserList" );
                });
            </script>
        </div>
        <script src="/scripts/apps/users/AdminUserList.js" type="text/javascript"></script>
        <script src="/scripts/apps/users/AdminUserForm.js" type="text/javascript"></script>
        <?php
        return ob_get_clean();
    }

    public function getAdminUserList(Selmasu $s)
    {
        ob_start();
        ?>
        <div class="row">
            <div class="col-xs-12">
                <div class="page-title-box">
                    <a class="k-button k-state-disabled" id="editButton">
                        <i class="fa fa-pencil"></i>
                        <span>Edit</span>
                    </a>
                    <ol class="breadcrumb p-0 m-0">
                        <li>
                            <a href="/admin"><?php echo Settings::$companyName;?></a>
                        </li>
                        <li>
                            <a href="/admin/settings">Settings</a>
                        </li>
                        <li class="active">
                            Users
                        </li>
                    </ol>
                    <div class="clearfix"></div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-xs-12">
                <div id="userGrid"></div>
            </div>
        </div>
        <script type="text/javascript">
            $(UserList.init)
        </script>
        <?php
        return ob_get_clean();
    }
}