<?php
class EmailFormView
{		
	public function getEmailForm()
	{
		ob_start();
		?>
			<div id="email">
                <div class="row">
                    <div class="col-xs-12">
                        <div class="page-title-box">
                            <h4 class="page-title">System Email</h4>
                            <ol class="breadcrumb p-0 m-0">
                                <li>
                                    <a href="/admin">LeR&ecirc;ve</a>
                                </li>
                                <li>
                                    <a href="/admin/settings">Settings</a>
                                </li>
                                <li>
                                    <a href="/admin/settings/emails">Emails</a>
                                </li>
                                <li class="active">
                                    <span data-bind="html: name"></span>
                                </li>
                            </ol>
                            <div class="clearfix"></div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-xs-12">

                        <div class="formWrap">
                            <h3>Email Details</h3>
                            <form id="emailForm">
                                <input data-bind="value: emailId" name="emailId" type="hidden"/>
                                <fieldset>
                                    <ul>
                                        <li class="formItem">
                                            <label for="name" class="required">Name</label>
                                            <span class="text" data-bind="html: name" />
                                        </li>
                                        <li class="formItem">
                                            <label for="active">Active</label>
                                            <input type="hidden" name="active" value="0"/>
                                            <input type="checkbox" data-bind="checked: active" value="1" name="active" id="active"/>
                                        </li>
                                        <li class="formItem">
                                            <label for="subject" class="required">Subject</label>
                                            <input data-bind="value: subject" type="text" id="subject" name="subject" class="k-textbox textBoxLarge" required validationMessage="Please enter subject" placeholder="Email Subject"/>
                                        </li>
                                        <li class="formItem">
                                            <label for="fromName" class="required">From Name</label>
                                            <input data-bind="value: fromName" type="text" id="fromName" name="fromName" class="k-textbox textBoxLarge" placeholder="From Name" required validationMessage="Please enter from name"/>
                                        </li>
                                        <li class="formItem">
                                            <label for="fromAddress" class="required">From Email</label>
                                            <input data-bind="value: fromAddress" type="email" id="fromAddress" name="fromAddress" class="k-textbox textBoxLarge" placeholder="From Email Address" required data-email-msg="Email format is not valid" validationMessage="Please enter from email address"/>
                                        </li>
                                        <li data-bind="invisible: isDynamic" id="toAddressField"class="formItem">
                                            <label for="toAddress">To</label>
                                            <select data-bind="value: toAddress" id="toAddress" name="toAddress"></select>
                                            <div class="clear"></div>
                                        </li>
                                        <li class="formItem">
                                            <label for="ccAddress">CC</label>
                                            <select data-bind="value: ccAddress" id="ccAddress" name="ccAddress"></select>
                                            <div class="clear"></div>
                                        </li>
                                        <li class="formItem">
                                            <label for="bccAddress">BCC</label>
                                            <select data-bind="value: bccAddress" id="bccAddress" name="bccAddress"></select>
                                            <div class="clear"></div>
                                        </li>
                                        <li>
                                            <label for="htmlBody" class="label-top">Body</label>
                                            <div class="ckwrap">
                                                <textarea data-bind="value: htmlBody" id="htmlBody" name="htmlBody"></textarea>
                                            </div>
                                        </li>
                                    </ul>
                                </fieldset>
                            </form>
                        </div>
                        <div class="actionBar">
                            <div class="pull-left">
                                <button class="k-button" type="button" id="cancelButton">Cancel</button>
                            </div>
                            <div class="pull-right">
                                <span class="status"></span>
                                <button class="k-button" type="button" id="saveButton">Save</button>
                                <button class="k-button" type="button" id="doneButton">Done</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
			<script src="/scripts/apps/emails/EmailForm.js" type="text/javascript"></script>
		<?php
		return ob_get_clean();
	}
}