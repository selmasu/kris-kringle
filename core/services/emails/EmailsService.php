<?php
namespace simpli;
class EmailsService extends EmailsModel
{
    /**
     * @param \ListArguments $listArguments
     * @return array
     */
    public function getEmails( \ListArguments $listArguments )
    {
        $where = '';

        if( $listArguments->filters()->stringVal( 'search' ) )
        {
            $search = $listArguments->filters()->stringVal( 'search' );
            $where .= " AND ( email.name LIKE '%" . $this->adb->escapeString( $search ) . "%'
							OR email.subject LIKE '%" . $this->adb->escapeString( $search ) . "%'
							OR email.fromAddress LIKE '%" . $this->adb->escapeString( $search ) . "%' )";
        }

        $query = \SqlQueryBits::create()
            ->limit()->set( $listArguments->take()->get() )
            ->offset()->set( $listArguments->skip()->get() )
            ->orderBy()->set( $this->sortToString( $listArguments->sort()->get() ) )
            ->where()->set( $where );

        return array( 'data' => $this->getEmailsFromDB( $query ), 'total' => $this->getEmailsTotalFromDB( $query ) );
    }

    /**
     * Gets an email
     * @param int $emailId
     * @return array
     */
    public function getEmail( $emailId )
    {
        $query = \SqlQueryBits::create()
            ->limit()->set( 1 )
            ->offset()->set( 0 )
            ->where()->set( ' AND email.emailId = ' . (int)$emailId );
        foreach( $this->getEmailsFromDB( $query ) as $email )
        {
            return $email;
        }

        return null;
    }

    /**
     * Gets an email
     * @param int $emailId
     * @return \simpli\Email
     */
    public function getEmailObject( $emailId )
    {
        $fileService = \FileService::create($this->s());
        $query = \SqlQueryBits::create()
            ->limit()->set( 1 )
            ->offset()->set( 0 )
            ->where()->set( ' AND email.emailId = ' . (int)$emailId );
        foreach( $this->getEmailsFromDB( $query ) as $email )
        {
            $email = \simpli\Email::create()->fromArray( $email );
            $attachments = array();
            /** @var \File $attachment */
            foreach( $this->getEmailAttachments($emailId) as $attachment ){
                $cachePath = $fileService->cacheFile($attachment);
                $attachments[] = \EmailAttachment::create()
                    ->path()->set($cachePath)
                    ->name()->set($attachment->originalName()->get());
            }
            $email->attachments()->set($attachments);
            return $email;
        }

        return null;
    }

    /**
     * @param int $emailId
     * @return int
     */
    public function createEmail($emailId){
        $organisation = \OrganisationService::create($this->s())->getUserOrg();
        $email = \simpli\Email::create();
        $email->emailId()->set($emailId);
        $email->subject()->set($email->defaultSubject($emailId));
        $email->htmlBody()->set($email->defaultBody($emailId, $organisation->logoUrl()->get()));
        $email->active()->set(true);
        $email->dynamicTo()->set($email->defaultDynamicTo($emailId));
        $email->fromAddress()->set($organisation->email()->get());
        $email->fromName()->set($organisation->name()->get());

        return $this->createEmailInDB($email);
    }

    /**
     * @param Email $email
     * @return int
     */
    public function save( $email )
    {
        return $this->saveToDB( $email );
    }

    /**
     * Saves an emails's details
     * @param array $data
     * @return int
     */
    public function saveFromArray( $data )
    {
        return $this->save( Email::create()->fromArray( $data ) );
    }

    /**
     * @param int $emailId
     * @param int $fileId
     */
    public function deleteAttachment( $emailId, $fileId  )
    {
        if( $email = $this->getEmailObject( $emailId ) )
        {
            $this->deleteAttachmentFromDB( $emailId, $fileId );
        }
    }

    public function addAttachment( $emailId, $fileId )
    {
        $this->addAttachmentToDB( $emailId, $fileId );
    }

    public function getAttachments( \ListArguments $listArguments, $emailId )
    {
        $where = ' AND email.emailId = ' . (int)$emailId;

        $query = \SqlQueryBits::create()
            ->limit()->set( $listArguments->take()->get() )
            ->offset()->set( $listArguments->skip()->get() )
            ->orderBy()->set( $this->sortToString( $listArguments->sort()->get() ) )
            ->where()->set( $where );

        $data = array( 'data' => $this->getAttachmentsFromDB( $query, \Model::RETURN_TYPE_ARRAY ), 'total' => $this->getAttachmentsTotalFromDB( $query ) );

        return $data;
    }

    /**
     * @param $emailId
     * @return File[]
     */
    private function getEmailAttachments( $emailId )
    {
        $where = ' AND email.emailId = ' . (int)$emailId;

        $query = \SqlQueryBits::create()
            ->where()->set( $where );

        return $this->getAttachmentsFromDB( $query, \Model::RETURN_TYPE_OBJECT );
    }

    public function sendBackupFailedEmail()
    {
        if( $email = $this->getEmailObject( Email::TYPE_BACKUP_FAILED ) )
        {
            $email
                ->id()->set( Email::TYPE_BACKUP_FAILED . '_' . date( 'YmdHi' ) )
                ->app()->set( 'core' );
            \EmailService::create( $this->s() )->sendMail( $email );
        }
    }

    /**
     * @param \ListArguments $listArguments
     * @return array
     */
    public function getLoggedEmails( \ListArguments $listArguments )
    {
        $where = '';

        if($listArguments->filters()->escapedStringVal( 'app' )){
            $where .= " AND emailLog.app = '" . $listArguments->filters()->escapedStringVal( 'app' ) . "'";
        }
        if($listArguments->filters()->intVal( 'logId' )){
            $where .= " AND emailLog.logId = " . (int)$listArguments->filters()->intVal( 'logId' );
        }

        $query = \SqlQueryBits::create();
        $query->limit()->set( $listArguments->take()->get() );
        $query->offset()->set( $listArguments->skip()->get() );
        $query->orderBy()->set( $this->sortToString( $listArguments->sort()->get() ) );
        $query->where()->set( $where );

        return ['data' => $this->getLoggedEmailsFromDB( $query ), 'total' => $this->getLoggedEmailsTotalFromDB( $query )];
    }
	
	/**
	 * Static creator
	 * @param Selmasu $s
	 * @return \simpli\EmailsService
	 */
	public static function create( \Selmasu $s )
	{
		return new self( $s );
	}

}