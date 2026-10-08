<?php
namespace simpli;
class Email extends \Email {
    //Types correspond with emailId
    const TYPE_NEW_TICKET                           = 1;
    const TYPE_TICKET_ASSIGNED                      = 2;
    const TYPE_TICKET_DISCUSSION                    = 3;
    const TYPE_QUOTE                                = 4;
    const TYPE_ACCOUNT_INVOICE                      = 5;
    const TYPE_PAYMENT_FAILED                       = 6;
    const TYPE_ACCOUNT_SUSPEND                      = 7;
    const TYPE_SHARE_FILES                          = 8;

    protected $emailId = null;
    protected $active = null;
    protected $dynamicTo = null;
    protected $name = null;

    public function emailId() {
        return $this->intProperty( $this->emailId );
    }

    public function active() {
        return $this->boolProperty( $this->active );
    }

    public function dynamicTo() {
        return $this->boolProperty( $this->dynamicTo );
    }

    public function name() {
        return $this->stringProperty( $this->name );
    }

    /**
     * Sets data from an array
     * @param array $data
     * @return \simpli\Email
     */
    public function fromArray( $data = array() ) {
        $this->setValuesFromArray( $data );
        return $this;
    }

    public function toArray() {
        return $this->valuesToArray();
    }

    /**
     * Returns the set part of an SQL string
     * @return string
     */
    public function sqlSet() {
        $setArray = $this->sqlUpdateArray();

        return implode( ",", $setArray );
    }

    /**
     * @param $emailId
     * @return bool
     */
    public function defaultDynamicTo($emailId){
        return true;
    }

    /**
     * @param $emailId
     * @return string
     */
    public function defaultSubject($emailId){
        if($emailId == self::TYPE_NEW_TICKET){
            return 'New [[Type]] Ticket #[[Ticket ID]]';
        } else if($emailId == self::TYPE_TICKET_ASSIGNED){
            return 'You have been assigned Support Ticket #[[Ticket ID]]';
        } else if($emailId == self::TYPE_TICKET_DISCUSSION){
            return 'Comment added to Support Ticket #[[Ticket ID]]';
        }

        return '';
    }

    /**
     * @param $emailId
     * @return string
     */
    public function defaultBody($emailId, $logo){
        $default = '<table style="background-color:#f7f7f7; width:100%">
	<tbody>
		<tr>
			<td>
			<table align="center" cellpadding="10" style="background-color:#fff;margin-top:50px;width:600px;">
				<tbody>
					<tr>
						<td style="text-align:right"><img alt="" src="' . $logo . '" style="width: 150px; height: 150px; float: left;" /></td>
						<td>
						<h2 style="text-align: right;">[[Type]]</h2>
						</td>
					</tr>
				</tbody>
			</table>

			<table align="center" cellpadding="10" style="background-color:#fff;margin-bottom:50px;width:600px;">
				<tbody>
					[[Table Body]]
				</tbody>
			</table>
			</td>
		</tr>
	</tbody>
</table>';

        $defaultTableBody = '<tr>
    <td>
    <p>[[Message]]</p>
    </td>
</tr>';

        $ticketBody = '<tr>
    <td colspan="4" style="background-color:rgb(255, 255, 255); text-align:center">
    <h2><span style="font-family:trebuchet ms,helvetica,sans-serif;"><span style="color:rgb(105, 105, 105)">[[Ticket Heading]]</span></span></h2>
    <p><em>[[Comment]]</em></p>
    <hr /></td>
</tr>
<tr>
    <td style="background-color:rgb(255, 255, 255); text-align:right; width:105px"><strong>Opened Date</strong></td>
    <td colspan="3" rowspan="1" style="background-color:rgb(255, 255, 255); width:449px">[[Opened Date]]</td>
</tr>
<tr>
    <td style="background-color:rgb(255, 255, 255); text-align:right; width:105px"><strong>Type</strong></td>
    <td colspan="3" rowspan="1" style="background-color:rgb(255, 255, 255); width:449px">[[Type]]</td>
</tr>
<tr>
    <td style="background-color:rgb(255, 255, 255); text-align:right; width:105px"><strong>Status</strong></td>
    <td colspan="3" rowspan="1" style="background-color:rgb(255, 255, 255); width:449px">[[Status]]</td>
</tr>
<tr>
    <td style="background-color:rgb(255, 255, 255); text-align:right; width:105px"><strong>Importance</strong></td>
    <td colspan="3" rowspan="1" style="background-color:rgb(255, 255, 255); width:449px">[[Importance]]</td>
</tr>
<tr>
    <td style="background-color:rgb(255, 255, 255); text-align:right; vertical-align:top; width:105px"><strong>Detail</strong></td>
    <td colspan="3" rowspan="1" style="background-color:rgb(255, 255, 255); width:449px">[[Detail]]</td>
</tr>
<tr>
    <td colspan="4" style="background-color:rgb(255, 255, 255); width:170px">
    <hr /></td>
</tr>';

        if($emailId == self::TYPE_NEW_TICKET){
            $body = str_replace("[[Type]]", "Ticket #[[Ticket ID]]", $default);
            $body = str_replace("[[Table Body]]", $ticketBody, $body);
            $body = str_replace("<p><em>[[Comment]]</em></p>", "", $body);
            return str_replace("[[Ticket Heading]]", "[[Opened By]] has opened a new ticket", $body);
        } else if($emailId == self::TYPE_TICKET_ASSIGNED){
            $body = str_replace("[[Type]]", "Ticket #[[Ticket ID]]", $default);
            $body = str_replace("[[Table Body]]", $ticketBody, $body);
            $body = str_replace("<p><em>[[Comment]]</em></p>", "", $body);
            return str_replace("[[Ticket Heading]]", "A ticket opened by [[Opened By]] has been assigned to you", $body);
        } else if($emailId == self::TYPE_TICKET_DISCUSSION){
            $body = str_replace("[[Type]]", "Ticket #[[Ticket ID]]", $default);
            $body = str_replace("[[Table Body]]", $ticketBody, $body);
            return str_replace("[[Ticket Heading]]", "[[Comment By]] added a comment", $body);
        }

        $body = str_replace("[[Type]]", "", $default);
        return str_replace("[[Table Body]]", $defaultTableBody, $body);
    }

    /**
     * Static creator
     * @return \simpli\Email
     */
    public static function create() {
        return new self;
    }
}