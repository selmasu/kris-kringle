<?php
class EmailListView
{
    public function getContentDiv()
    {
        ob_start();
        Utils::trace("contne");
        ?>
        <div id="mainContentDiv">
            <script type="text/javascript">
                var _emailId = 0;

                $( document ).ready( function() {
                    $( "#mainContentDiv" ).load( "/ajax/emailList" );
                });
            </script>
        </div>
        <?php
        return ob_get_clean();
    }

	public function getEmailList()
	{
		ob_start();
		?>
        <div class="row">
            <div class="col-xs-12">
                <div class="page-title-box">
                    <ol class="breadcrumb p-0 m-0">
                        <li>
                            <a href="/admin">LeR&ecirc;ve</a>
                        </li>
                        <li class="active">
                            Emails
                        </li>
                    </ol>
                    <div class="clearfix"></div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-xs-12">
				<div id="emailGrid"></div>
			</div>
        </div>
		<script src="/scripts/apps/emails/EmailList.js" type="text/javascript"></script>
		<?php
		return ob_get_clean();
	}
}