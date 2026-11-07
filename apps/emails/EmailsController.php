<?php
class EmailsController extends Controller implements ContentGenerator {
    /**
     * @param Selmasu $s
     */
    public function __construct(Selmasu $s)
    {
        parent::__construct($s);
    }

    public function getContent()
    {
        if (!$this->canManage())
            return '';

        return EmailListController::create($this->s)->getContent();
    }

    public function execAjax()
    {

    }

    private function canManage(){
        return $this->s()->user()->admin()->get();
    }

    /**
     * @param Selmasu $s
     * @return EmailsController
     */
    public static function create(Selmasu $s):EmailsController
    {
        return new EmailsController($s);
    }
}