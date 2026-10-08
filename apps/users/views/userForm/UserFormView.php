<?php
class UserFormView
{		
	public function getUserForm()
	{
		ob_start();
		?>
        <div id="user" style="display: none">
            <div class="app-menu">
                <div class="container">
                    <div class="row">
                        <div class="col-sm-12">
                            <div class="page-title-box">
                                <ol class="breadcrumb float-right p-0 m-0">
                                    <li>
                                        <a href="/team">team members</a>
                                    </li>
                                    <li class="active">
                                        team member
                                    </li>
                                </ol>
                                <h4 class="page-title m-0" data-bind="html: headerLabel">team member</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="wrapper">
                <div class="container">
                    <div class="row">
                        <div class="col-md-12">
                            <div id="userTabs">
                                <ul>
                                    <li class="k-state-active" id="userTab">Profile</li>
                                    <li data-bind="invisible: isNewUser" id="accessTab">Access</li>
                                    <li data-bind="invisible: isNewUser" id="filesTab">Files</li>
                                    <li data-bind="invisible: isNewUser" id="supportTab">Support</li>
                                    <li data-bind="invisible: isNewUser" id="discussionsTab">Discussion</li>
                                    <li data-bind="invisible: isNewUser" id="timeSheetTab">Time</li>
                                </ul>
                                <div>
                                    <div class="row">
                                        <div class="col-md-7">
                                            <div class="form-wrap">

                                                <form id="userForm">
                                                    <input data-bind="value: userId" name="userId" type="hidden"/>
                                                    <input data-bind="value: profilePhotoId" name="profilePhotoId" type="hidden"/>
                                                    <input data-bind="value: miniProfilePhotoId" name="miniProfilePhotoId" type="hidden"/>
                                                    <div class="form-group row">
                                                        <label for="firstName" class="required col-sm-3">First Name</label>
                                                        <div class="col-sm-9">
                                                            <input type="text" data-bind="value: firstName" id="firstName" name="firstName" class="form-control" required validationMessage="Enter first name" placeholder="First name" autocomplete="new-password"/>
                                                        </div>
                                                    </div>
                                                    <div class="form-group row">
                                                        <label for="lastName" class="required col-sm-3">Last Name</label>
                                                        <div class="col-sm-9">
                                                            <input type="text" data-bind="value: lastName" id="lastName" name="lastName" class="form-control" required validationMessage="Enter last name" placeholder="Last name" autocomplete="new-password"/>
                                                        </div>
                                                    </div>
                                                    <div class="form-group row">
                                                        <label for="email" class="required col-sm-3">Email</label>
                                                        <div class="col-sm-9">
                                                            <input type="email" data-bind="value: email" id="email" name="email" class="form-control" required validationMessage="Enter email" placeholder="Email" autocomplete="new-password"/>
                                                        </div>
                                                    </div>
                                                    <div class="form-group row">
                                                        <label for="mobile" class="col-sm-3">Mobile</label>
                                                        <div class="col-sm-9">
                                                            <input type="tel" data-bind="value: mobile" id="mobile" name="mobile" class="form-control" validationMessage="Enter mobile" placeholder="Mobile" autocomplete="new-password"/>
                                                        </div>
                                                    </div>
                                                    <div class="form-group row">
                                                        <label for="password" class="col-sm-3">Password</label>
                                                        <div class="col-sm-9">
                                                            <input type="password" id="password" name="password" class="form-control" placeholder="Password" autocomplete="new-password"/>
                                                        </div>
                                                    </div>
                                                    <div class="form-group row">
                                                        <label for="userGroupId" class="col-sm-3">Group</label>
                                                        <div class="col-sm-9">
                                                            <input data-bind="value: userGroupId" id="userGroupId" name="userGroupId" class="form-control" placeholder="Group" />
                                                            <span data-for="userGroupId" class="k-invalid-msg"></span>
                                                        </div>
                                                    </div>
                                                    <div class="form-group row">
                                                        <label for="timeZone" class="required col-sm-3">Time Zone</label>
                                                        <div class="col-sm-9">
                                                            <select id="timeZone" name="timeZone" data-bind="value: timeZone" class="form-control">
                                                                <?php
                                                                foreach(Utils::timeZoneList() as $tz => $label){
                                                                    ?>
                                                                    <option value="<?php echo $tz;?>"><?php echo $label;?></option>
                                                                    <?php
                                                                }
                                                                ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="form-group row">
                                                        <label for="locale" class="required col-sm-3">Date Format</label>
                                                        <div class="col-sm-9">
                                                            <select id="locale" name="dateFormat" data-bind="value: dateFormat" class="form-control" required validationMessage="Select date format">
                                                                <?php
                                                                foreach(Utils::dateFormats() as $format => $label){
                                                                    ?>
                                                                    <option value="<?php echo $format;?>"><?php echo $label;?></option>
                                                                    <?php
                                                                }
                                                                ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="form-group row">
                                                        <label for="costRate" class="col-sm-3">Cost Rate</label>
                                                        <div class="col-sm-9">
                                                            <input type="number" data-bind="value: costRate" id="costRate" name="costRate" placeholder="Cost Rate" /> / hour
                                                        </div>
                                                    </div>
                                                    <div class="form-group row">
                                                        <label for="billableRate" class="col-sm-3">Billable Rate</label>
                                                        <div class="col-sm-9">
                                                            <input type="number" data-bind="value: billableRate" id="billableRate" name="billableRate" placeholder="Billable Rate" /> / hour
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                        <div class="col-md-5 text-center">
                                            <i class="fas fa-user" style="font-size: 200px; color: rgba(200,200,200,1); margin: 20px" id="missingProfileImage"></i>
                                            <img class="img-fluid rounded-circle" id="profileImage"/>
                                            <input id="profilePhoto" name="profilePhoto" type="file"/>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="actionBar">
                                                <div class="float-left">
                                                    <button class="btn btn-light w-md waves-effect float-left cancelButton" type="button" id="cancelButton">Cancel</button>
                                                    <div class="dropdown float-left m-l-5" data-bind="invisible: isNewUser">
                                                        <button class="btn btn-inverse w-md waves-effect waves-light dropdown-toggle" type="button" id="actionMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                            Actions
                                                        </button>
                                                        <div class="dropdown-menu" aria-labelledby="actionMenuButton">
                                                            <a class="dropdown-item" href="#" id="archiveUser">Archive Team Member</a>
                                                            <a class="dropdown-item" href="#" id="deleteUser">Delete Team Member</a>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="float-right">
                                                    <span class="status"></span>
                                                    <button type="button" class="btn btn-link w-md waves-effect waves-light saveButton" id="saveButton">Save</button>
                                                    <button type="button" class="btn btn-custom w-md waves-effect waves-light doneButton" id="doneButton">Done</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-wrap">

                                                <form id="accessForm">
                                                    <div class="form-group row">
                                                        <label class="col-sm-3">Access Settings</label>
                                                        <div class="col-sm-9">
                                                            <input type="checkbox" id="settingsAccess" name="settingsAccess" value="1"/>
                                                            <small>Update system settings, templates</small>
                                                        </div>
                                                    </div>
                                                    <div class="form-group row">
                                                        <label class="col-sm-3">Manage Team</label>
                                                        <div class="col-sm-9">
                                                            <input type="checkbox" id="usersManage" name="usersManage" value="1"/>
                                                            <small>Create and update team members</small>
                                                        </div>
                                                    </div>
                                                    <div class="form-group row">
                                                        <label class="col-sm-3">Manage Leads</label>
                                                        <div class="col-sm-9">
                                                            <input type="checkbox" id="leadsManage" name="leadsManage" value="1"/>
                                                            <small>Create and update leads, view leads dashboard info</small>
                                                        </div>
                                                    </div>
                                                    <div class="form-group row">
                                                        <label class="col-sm-3">Access Quotes</label>
                                                        <div class="col-sm-9">
                                                            <input type="checkbox" id="quotesAccess" name="quotesAccess" value="1"/>
                                                            <small>Create and update quotes, view quotes dashboard info</small>
                                                        </div>
                                                    </div>
                                                    <div class="form-group row">
                                                        <label class="col-sm-3">Approve Quotes</label>
                                                        <div class="col-sm-9">
                                                            <input type="checkbox" id="quotesApprove" name="quotesApprove" value="1"/>
                                                            <small>Approve quotes</small>
                                                        </div>
                                                    </div>
                                                    <div class="form-group row">
                                                        <label class="col-sm-3">Manage Projects</label>
                                                        <div class="col-sm-9">
                                                            <input type="checkbox" id="projectsManage" name="projectsManage" value="1"/>
                                                            <small>Create and update projects and tasks, view projects dashboard info</small>
                                                        </div>
                                                    </div>
                                                    <div class="form-group row">
                                                        <label class="col-sm-3">Manage Customers</label>
                                                        <div class="col-sm-9">
                                                            <input type="checkbox" id="customersManage" name="customersManage" value="1"/>
                                                            <small>Create and update customers</small>
                                                        </div>
                                                    </div>
                                                    <div class="form-group row">
                                                        <label class="col-sm-3">Manage Support</label>
                                                        <div class="col-sm-9">
                                                            <input type="checkbox" id="ticketsManage" name="ticketsManage" value="1"/>
                                                            <small>Set ticket importance, allocate tickets to team</small>
                                                        </div>
                                                    </div>
                                                    <div class="form-group row">
                                                        <label class="col-sm-3">Manage Invoices</label>
                                                        <div class="col-sm-9">
                                                            <input type="checkbox" id="invoicesManage" name="invoicesManage" value="1"/>
                                                            <small>Create and update invoices</small>
                                                        </div>
                                                    </div>
                                                    <div class="form-group row">
                                                        <label class="col-sm-3">Manage Files</label>
                                                        <div class="col-sm-9">
                                                            <input type="checkbox" id="filesManage" name="filesManage" value="1"/>
                                                            <small>Manage all files (delete files of others)</small>
                                                        </div>
                                                    </div>
                                                    <div class="form-group row">
                                                        <label class="col-sm-3">View Reports</label>
                                                        <div class="col-sm-9">
                                                            <input type="checkbox" id="reportsAccess" name="reportsAccess" value="1"/>
                                                            <small>View reports based on above access level</small>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="actionBar">
                                                <div class="float-left">
                                                    <button class="btn btn-light w-md waves-effect float-left" type="button" id="cancelButton">Cancel</button>
                                                    <div class="dropdown float-left m-l-5" data-bind="invisible: isNewUser">
                                                        <button class="btn btn-inverse w-md waves-effect waves-light dropdown-toggle" type="button" id="actionMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                            Actions
                                                        </button>
                                                        <div class="dropdown-menu" aria-labelledby="actionMenuButton">
                                                            <a class="dropdown-item" href="#" id="archiveUser">Archive Team Member</a>
                                                            <a class="dropdown-item" href="#" id="deleteUser">Delete Team Member</a>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="float-right">
                                                    <span class="status"></span>
                                                    <button type="button" class="btn btn-link w-md waves-effect waves-light" id="saveButton">Save</button>
                                                    <button type="button" class="btn btn-custom w-md waves-effect waves-light" id="doneButton">Done</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div>

                                </div>
                                <div>

                                </div>
                                <div>

                                </div>
                                <div>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div id="loading"></div>
        <script id="noGroupTemplate" type="text/x-kendo-tmpl">
            <div>
                No group found. Do you want to add new group '#:instance.text()#'?
            </div>
            <br />
            <button class="btn btn-light" onclick="UserForm.addGroup('#:instance.text()#')">Add new group</button>
        </script>
        <script>
            $(document).ready(UserForm.init);
        </script>
		<?php
		return ob_get_clean();
	}

    public function getAdminUserForm()
    {
        ob_start();
        ?>
        <div id="user">
            <div class="row">
                <div class="col-xs-12">
                    <div class="page-title-box">
                        <h4 class="page-title">User</h4>
                        <ol class="breadcrumb p-0 m-0">
                            <li>
                                <a href="/admin"><?php echo Settings::$companyName;?></a>
                            </li>
                            <li>
                                <a href="/admin/settings">Settings</a>
                            </li>
                            <li>
                                <a href="/admin/settings/users">Users</a>
                            </li>
                            <li class="active">
                                <span data-bind="html: breadCrumbName"></span>
                            </li>
                        </ol>
                        <div class="clearfix"></div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-xs-12">
                    <div class="formWrap">
                        <form id="userForm">
                            <input data-bind="value: userId" name="userId" type="hidden"/>
                            <div class="pull-left">
                                <ul>
                                    <li>
                                        <label for="firstName" class="required">First Name</label>
                                        <input data-bind="value: firstName" type="text" id="firstName" name="firstName" class="k-textbox" required validationMessage="Please enter first name" />
                                    </li>
                                    <li>
                                        <label for="lastName" class="required">Last Name</label>
                                        <input data-bind="value: lastName" type="text" id="lastName" name="lastName" class="k-textbox" required validationMessage="Please enter last name" />
                                    </li>
                                    <li>
                                        <label for="email" class="required">Email</label>
                                        <input data-bind="value: email" type="email" id="email" name="email" class="k-textbox" placeholder="Please enter email address"  required data-email-msg="Email format is not valid" validationMessage="Please enter email address" autocomplete="new-password"/>
                                    </li>
                                    <li>
                                        <label for="password">Password</label>
                                        <input type="password" id="password" name="password" class="k-textbox" placeholder="Password" autocomplete="new-password"/>
                                    </li>
                                    <li>
                                        <label for="mobile">Mobile</label>
                                        <input data-bind="value: mobile" type="tel" id="mobile" name="mobile" class="k-textbox"/>
                                    </li>
                                    <li>
                                        <label for="superUser">Super User</label>
                                        <input type="hidden" name="superUser" value="0" />
                                        <input type="checkbox" id="superUser" name="superUser" value="1" />
                                    </li>
                                    <li>
                                        <label for="admin">Admin</label>
                                        <input type="hidden" name="admin" value="0" />
                                        <input type="checkbox" id="admin" name="admin" value="1" />
                                    </li>
                                </ul>
                            </div>
                            <div class="pull-left" style="margin-left: 40px">
                                <ul>
                                    <li>
                                        <label></label>
                                        <strong>Permissions</strong>
                                    </li>
                                    <li>
                                        <label>Settings</label>
                                        <input data-bind="checked: settingsAccess" type="checkbox" id="settingsAccess" name="settingsAccess" value="1"/> Access
                                    </li>
                                    <li>
                                        <label>Users</label>
                                        <input data-bind="checked: usersManage" type="checkbox" id="usersManage" name="usersManage" value="1"/> Manage
                                    </li>
                                    <li>
                                        <label>Leads</label>
                                        <input data-bind="checked: leadsManage" type="checkbox" id="leadsManage" name="leadsManage" value="1"/> Manage
                                    </li>
                                    <li>
                                        <label>Quotes</label>
                                        <input data-bind="checked: quotesAccess" type="checkbox" id="quotesAccess" name="quotesAccess" value="1"/> Access
                                        <input data-bind="checked: quotesApprove" type="checkbox" id="quotesApprove" name="quotesApprove" value="1"/> Approve
                                    </li>
                                    <li>
                                        <label>Projects</label>
                                        <input data-bind="checked: projectsManage" type="checkbox" id="projectsManage" name="projectsManage" value="1"/> Manage
                                    </li>
                                    <li>
                                        <label>Customers</label>
                                        <input data-bind="checked: customersManage" type="checkbox" id="customersManage" name="customersManage" value="1"/> Manage
                                    </li>
                                    <li>
                                        <label>Tickets</label>
                                        <input data-bind="checked: ticketsManage" type="checkbox" id="ticketsManage" name="ticketsManage" value="1"/> Manage
                                    </li>
                                    <li>
                                        <label>Reports</label>
                                        <input data-bind="checked: reportsAccess" type="checkbox" id="reportsAccess" name="reportsAccess" value="1"/> Access
                                    </li>
                                    <li>
                                        <label>CMS - Articles</label>
                                        <input data-bind="checked: articlesManage" type="checkbox" id="articlesManage" name="articlesManage" value="1"/> Manage
                                    </li>
                                    <li>
                                        <label>CMS - Content Area</label>
                                        <input data-bind="checked: contentAreasManage" type="checkbox" id="contentAreasManage" name="contentAreasManage" value="1"/> Manage
                                    </li>
                                    <li>
                                        <label>CMS - Galleries</label>
                                        <input data-bind="checked: galleriesManage" type="checkbox" id="galleriesManage" name="galleriesManage" value="1"/> Manage
                                    </li>
                                    <li>
                                        <label>CMS - File Repo</label>
                                        <input data-bind="checked: fileRepoManage" type="checkbox" id="fileRepoManage" name="fileRepoManage" value="1"/> Manage
                                    </li>
                                    <li>
                                        <label>CMS - Pages</label>
                                        <input data-bind="checked: pagesManage" type="checkbox" id="pagesManage" name="pagesManage" value="1"/> Manage
                                    </li>
                                    <li>
                                        <label>CMS - Templates</label>
                                        <input data-bind="checked: templatesManage" type="checkbox" id="templatesManage" name="templatesManage" value="1"/> Manage
                                    </li>
                                    <li>
                                        <label>PDF Templates</label>
                                        <input data-bind="checked: pdfTemplatesManage" type="checkbox" id="pdfTemplatesManage" name="pdfTemplatesManage" value="1"/> Manage
                                    </li>
                                </ul>
                            </div>
                            <div class="clearfix"></div>
                        </form>
                    </div>
                    <div class="actionBar">
                        <div class="pull-left">
                            <button class="k-button" type="button" id="cancelButton">Cancel</button>
                        </div>
                        <div class="pull-right">
                            <button class="k-button" type="button" id="becomeButton">Become User</button>
                            <button class="k-button" type="button" id="saveButton">Save</button>
                            <button class="k-button" type="button" id="doneButton">Done</button>
                        </div>
                        <div class="pull-right">
                            <span class="status"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script type="text/javascript">
            $(UserForm.init)
        </script>
        <?php
        return ob_get_clean();
    }
}