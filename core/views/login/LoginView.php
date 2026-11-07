<?php
class LoginView
{
    public function getLoginForm(Selmasu $s)
    {
        ob_start();
        ?>
        <div class="account-content">
            <form class="form-horizontal" id="login">
                <input id="r" value="<?php echo $s->pageRequest()->stringVar('r');?>" type="hidden"/>
                <input id="mfa" value="<?php echo Settings::$mfa;?>" type="hidden"/>
                <?php
                if(!$s->loggedIn()){
                    ?>
                    <div class="form-group m-b-25">
                        <div class="col-xs-12">
                            <label for="email">Email address</label>
                            <input class="form-control input-lg" type="email" id="email" required="" placeholder="john@deo.com">
                        </div>
                    </div>

                    <div class="form-group m-b-25" id="password-row">
                        <div class="col-xs-12">
                            <a href="/login/forgotten-password" class="text-muted pull-right font-14">Forgot your password?</a>
                            <label for="password">Password</label>
                            <input class="form-control input-lg" type="password" required="" id="password" placeholder="Enter your password">
                        </div>
                    </div>

                    <div class="form-group m-b-25" id="one-time-code-row" style="display: none">
                        <div class="col-xs-12">
                            <label for="oneTimeCode">One Time Code</label>
                            <input class="form-control input-lg" type="text" id="oneTimeCode" placeholder="Enter your one time code">
                        </div>
                    </div>

                    <div class="form-group m-b-20" id="remember-me-row">
                        <div class="col-xs-12">
                            <div class="checkbox checkbox-custom">
                                <label>
                                    <input type="checkbox" value="" id="rememberMe">
                                    <span class="cr"><i class="cr-icon fa fa-check"></i></span>
                                    Remember me for 30 days
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group text-center m-t-10">
                        <div class="col-xs-12">
                            <button class="btn w-lg btn-rounded btn-lg btn-primary waves-effect waves-light" type="button" id="sign-in">Sign In</button>
                        </div>
                    </div>
                    <div class="form-group text-center m-t-10" id="send-one-time-code-row">
                        <div class="col-xs-12">
                            <button class="btn w-lg btn-rounded btn-lg btn-link waves-effect waves-light" type="button" id="one-time-code">Send One Time Code</button>
                        </div>
                    </div>
                    <?php
                } else {
                    ?>
                    <div class="form-group m-b-25">
                        <div class="col-xs-12">
                            <label for="mfaCode">Enter Two-Factor Authentication Code</label>
                            <input class="form-control input-lg" type="text" required="" id="mfaCode" placeholder="Enter your six digit code">
                        </div>
                    </div>

                    <div class="form-group account-btn text-center m-t-10">
                        <div class="col-xs-12">
                            <button class="btn w-lg btn-rounded btn-lg btn-primary waves-effect waves-light" type="button" id="sign-in">Sign In</button>
                            <div class="m-t-10">
                                <a href="#" class="text-muted font-14" id="resendCode">Resend Code</a>
                            </div>
                        </div>
                    </div>
                    <?php
                }
                ?>
            </form>

            <div class="clearfix"></div>

        </div>
        <?php
        return ob_get_clean();
    }

    public function getRetrievePasswordForm()
    {
        ob_start();
        ?>
        <div class="account-content">
            <form class="form-horizontal" id="reset-password-request">
                <div class="form-group m-b-25">
                    <div class="col-xs-12">
                        <p class="text-center">Having trouble logging in? Enter your email below to set a new password.</p>
                    </div>
                </div>
                <div class="form-group m-b-25">
                    <div class="col-xs-12">
                        <label for="email">Email address</label>
                        <input class="form-control input-lg" type="email" id="email" required="" placeholder="john@deo.com">
                    </div>
                </div>

                <div class="form-group account-btn text-center m-t-10">
                    <div class="col-xs-12">
                        <button class="btn w-lg btn-rounded btn-lg btn-primary waves-effect waves-light" type="button">Reset Password</button>
                    </div>
                </div>

            </form>

            <div class="clearfix"></div>

        </div>
        <?php
        return ob_get_clean();
    }

    public function getResetPasswordForm($token)
    {
        ob_start();
        ?>
        <div class="account-content">
            <form class="form-horizontal" id="reset-password">
                <input type="hidden" id="resetToken" value="<?php echo $token;?>"/>
                <div class="form-group m-b-25">
                    <div class="col-xs-12">
                        <p class="text-center">Enter your new password below</p>
                    </div>
                </div>
                <div class="form-group m-b-25">
                    <div class="col-xs-12">
                        <label for="password">Password</label>
                        <input class="form-control input-lg" type="password" required="" id="password" placeholder="Enter your password">
                    </div>
                </div>

                <div class="form-group account-btn text-center m-t-10">
                    <div class="col-xs-12">
                        <button class="btn w-lg btn-rounded btn-lg btn-primary waves-effect waves-light" type="button">Reset Password</button>
                    </div>
                </div>

            </form>

            <div class="clearfix"></div>

        </div>
        <?php
        return ob_get_clean();
    }

    public function getResetPasswordConfirmation()
    {
        ob_start();
        ?>
        <div class="form-group m-b-25">
            <div class="col-xs-12">
                <h3 class="text-center">Retrieve Password Confirmation</h3>
            </div>
        </div>
        <div class="form-group m-b-25">
            <div class="col-xs-12">
                <p class="text-center">We've sent you an email with a reset password link. Click the link in the email, and a new password will be emailed to you.</p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function passwordResetSuccess()
    {
        ob_start();
        ?>
        <div class="form-group m-b-25">
            <div class="col-xs-12">
                <h3 class="text-center">Password Reset Success</h3>
            </div>
        </div>
        <div class="form-group m-b-25">
            <div class="col-xs-12">
                <p class="text-center">Your password has been successfully reset.</p>
            </div>
        </div>
        <div class="form-group m-b-25">
            <div class="col-xs-12 text-center">
                <a class="btn w-lg btn-rounded btn-lg btn-primary waves-effect waves-light" href="/">Return to Home Page</a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}