<?php

final class Tables
{
    /**
     * @var array
     */
    private $tables;

    /**
     * @var array
     */
    private $tableSchemas;

    /**
     * @var Selmasu
     */
    private $s;

    public function __construct(Selmasu $s)
    {
        $this->s = $s;

        $this->tables = array_merge($this->appTables(), CoreTables::create($s)->coreTables());
    }

    private function appTables()
    {
        return [
            'trip' => 'trip',
            'destination' => 'destination',
            'day' => 'day',
            'place' => 'place',
            'toDo' => 'toDo',
            'item' => 'item',
            'follow' => 'follow',
            'followTrip' => 'followTrip',
            'followRequest' => 'followRequest',
            'followInvite' => 'followInvite',
            'fellowTraveller' => 'fellowTraveller',
            'post' => 'post',
            'postMedia' => 'postMedia',
            'comment' => 'comment',
            'token' => 'token'
        ];
    }

    private function setTableSchemas()
    {
        $this->tableSchemas = array_merge($this->appTableSchemas(), CoreTables::create($this->s)->coreTableSchemas());
    }

    private function appTableSchemas()
    {
        return [
            'trip' => $this->tripSchema(),
            'destination' => $this->destinationSchema(),
            'day' => $this->daySchema(),
            'place' => $this->placeSchema(),
            'toDo' => $this->toDoSchema(),
            'item' => $this->itemSchema(),
            'follow' => $this->followSchema(),
            'followTrip' => $this->followTripSchema(),
            'followRequest' => $this->followRequestSchema(),
            'followInvite' => $this->followInviteSchema(),
            'fellowTraveller' => $this->fellowTravellerSchema(),
            'post' => $this->postSchema(),
            'postMedia' => $this->postMediaSchema(),
            'comment' => $this->commentSchema(),
            'token' => $this->tokenSchema()
        ];
    }

    public function tables(){
        return $this->tables;
    }

    /** App Tables */

    public function trip(){
        return $this->tables['trip'];
    }

    public function destination(){
        return $this->tables['destination'];
    }

    public function day(){
        return $this->tables['day'];
    }

    public function place(){
        return $this->tables['place'];
    }

    public function toDo(){
        return $this->tables['toDo'];
    }

    public function item(){
        return $this->tables['item'];
    }

    public function follow(){
        return $this->tables['follow'];
    }

    public function followTrip(){
        return $this->tables['followTrip'];
    }

    public function followRequest(){
        return $this->tables['followRequest'];
    }

    public function followInvite(){
        return $this->tables['followInvite'];
    }

    public function fellowTraveller(){
        return $this->tables['fellowTraveller'];
    }

    public function post(){
        return $this->tables['post'];
    }

    public function postMedia(){
        return $this->tables['postMedia'];
    }

    public function comment(){
        return $this->tables['comment'];
    }

    public function token(){
        return $this->tables['token'];
    }

    public function getSchema($tableName){
        $this->setTableSchemas();

        if (isset($this->tableSchemas[$tableName])) {
            return $this->tableSchemas[$tableName];
        }

        return '';
    }

    private function tripSchema(){
        return TableSchema::create()
            ->name()->set('trip')
            ->addField(TableField::create()
                ->name()->set('tripId')
                ->type()->set(TableField::TYPE_BIGINT)
                ->autoIncrement()->set(TRUE)
                ->primaryKey()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('deleted')
                ->type()->set(TableField::TYPE_TINYINT))
            ->addField(TableField::create()
                ->name()->set('current')
                ->type()->set(TableField::TYPE_TINYINT))
            ->addField(TableField::create()
                ->name()->set('createdBy')
                ->type()->set(TableField::TYPE_BIGINT)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('createdDate')
                ->type()->set(TableField::TYPE_DATETIME))
            ->addField(TableField::create()
                ->name()->set('updatedBy')
                ->type()->set(TableField::TYPE_BIGINT)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('updatedDate')
                ->type()->set(TableField::TYPE_DATETIME))
            ->addField(TableField::create()
                ->name()->set('date')
                ->type()->set(TableField::TYPE_DATE)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('token')
                ->type()->set(TableField::TYPE_VARCHAR_10)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('accessToken')
                ->type()->set(TableField::TYPE_VARCHAR_50)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('name')
                ->type()->set(TableField::TYPE_VARCHAR_100));
    }

    private function destinationSchema(){
        return TableSchema::create()
            ->name()->set('destination')
            ->addField(TableField::create()
                ->name()->set('destinationId')
                ->type()->set(TableField::TYPE_BIGINT)
                ->autoIncrement()->set(TRUE)
                ->primaryKey()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('placeId')
                ->type()->set(TableField::TYPE_VARCHAR_255))
            ->addField(TableField::create()
                ->name()->set('priority')
                ->type()->set(TableField::TYPE_INT)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('deleted')
                ->type()->set(TableField::TYPE_TINYINT))
            ->addField(TableField::create()
                ->name()->set('travelMethodId')
                ->type()->set(TableField::TYPE_TINYINT))
            ->addField(TableField::create()
                ->name()->set('booked')
                ->type()->set(TableField::TYPE_TINYINT))
            ->addField(TableField::create()
                ->name()->set('createdBy')
                ->type()->set(TableField::TYPE_BIGINT)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('createdDate')
                ->type()->set(TableField::TYPE_DATETIME))
            ->addField(TableField::create()
                ->name()->set('updatedBy')
                ->type()->set(TableField::TYPE_BIGINT)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('updatedDate')
                ->type()->set(TableField::TYPE_DATETIME))
            ->addField(TableField::create()
                ->name()->set('tripId')
                ->type()->set(TableField::TYPE_BIGINT)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('dayId')
                ->type()->set(TableField::TYPE_BIGINT)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('price')
                ->type()->set(TableField::TYPE_INT))
            ->addField(TableField::create()
                ->name()->set('distance')
                ->type()->set(TableField::TYPE_INT))
            ->addField(TableField::create()
                ->name()->set('duration')
                ->type()->set(TableField::TYPE_INT))
            ->addField(TableField::create()
                ->name()->set('lat')
                ->type()->set(TableField::TYPE_VARCHAR_10))
            ->addField(TableField::create()
                ->name()->set('lng')
                ->type()->set(TableField::TYPE_VARCHAR_10))
            ->addField(TableField::create()
                ->name()->set('name')
                ->type()->set(TableField::TYPE_VARCHAR_100))
            ->addField(TableField::create()
                ->name()->set('address')
                ->type()->set(TableField::TYPE_VARCHAR_100))
            ->addField(TableField::create()
                ->name()->set('web')
                ->type()->set(TableField::TYPE_VARCHAR_100))
            ->addField(TableField::create()
                ->name()->set('directions')
                ->type()->set(TableField::TYPE_LONGTEXT));
    }

    private function placeSchema(){
        return TableSchema::create()
            ->name()->set('place')
            ->addField(TableField::create()
                ->name()->set('placeId')
                ->type()->set(TableField::TYPE_BIGINT)
                ->autoIncrement()->set(TRUE)
                ->primaryKey()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('deleted')
                ->type()->set(TableField::TYPE_TINYINT))
            ->addField(TableField::create()
                ->name()->set('createdBy')
                ->type()->set(TableField::TYPE_BIGINT)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('createdDate')
                ->type()->set(TableField::TYPE_DATETIME))
            ->addField(TableField::create()
                ->name()->set('updatedBy')
                ->type()->set(TableField::TYPE_BIGINT)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('updatedDate')
                ->type()->set(TableField::TYPE_DATETIME))
            ->addField(TableField::create()
                ->name()->set('tripId')
                ->type()->set(TableField::TYPE_BIGINT)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('lat')
                ->type()->set(TableField::TYPE_VARCHAR_10))
            ->addField(TableField::create()
                ->name()->set('lng')
                ->type()->set(TableField::TYPE_VARCHAR_10))
            ->addField(TableField::create()
                ->name()->set('name')
                ->type()->set(TableField::TYPE_VARCHAR_100))
            ->addField(TableField::create()
                ->name()->set('address')
                ->type()->set(TableField::TYPE_VARCHAR_100))
            ->addField(TableField::create()
                ->name()->set('reason')
                ->type()->set(TableField::TYPE_TEXT));
    }

    private function daySchema(){
        return TableSchema::create()
            ->name()->set('day')
            ->addField(TableField::create()
                ->name()->set('dayId')
                ->type()->set(TableField::TYPE_BIGINT)
                ->autoIncrement()->set(TRUE)
                ->primaryKey()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('deleted')
                ->type()->set(TableField::TYPE_TINYINT))
            ->addField(TableField::create()
                ->name()->set('createdBy')
                ->type()->set(TableField::TYPE_BIGINT)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('createdDate')
                ->type()->set(TableField::TYPE_DATETIME))
            ->addField(TableField::create()
                ->name()->set('updatedBy')
                ->type()->set(TableField::TYPE_BIGINT)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('updatedDate')
                ->type()->set(TableField::TYPE_DATETIME))
            ->addField(TableField::create()
                ->name()->set('tripId')
                ->type()->set(TableField::TYPE_BIGINT)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('dayNumber')
                ->type()->set(TableField::TYPE_INT)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('date')
                ->type()->set(TableField::TYPE_DATE)
                ->key()->set(TRUE));
    }

    private function toDoSchema(){
        return TableSchema::create()
            ->name()->set('toDo')
            ->addField(TableField::create()
                ->name()->set('toDoId')
                ->type()->set(TableField::TYPE_BIGINT)
                ->autoIncrement()->set(TRUE)
                ->primaryKey()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('deleted')
                ->type()->set(TableField::TYPE_TINYINT))
            ->addField(TableField::create()
                ->name()->set('completed')
                ->type()->set(TableField::TYPE_TINYINT))
            ->addField(TableField::create()
                ->name()->set('createdBy')
                ->type()->set(TableField::TYPE_BIGINT)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('createdDate')
                ->type()->set(TableField::TYPE_DATETIME))
            ->addField(TableField::create()
                ->name()->set('updatedBy')
                ->type()->set(TableField::TYPE_BIGINT)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('updatedDate')
                ->type()->set(TableField::TYPE_DATETIME))
            ->addField(TableField::create()
                ->name()->set('tripId')
                ->type()->set(TableField::TYPE_BIGINT)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('detail')
                ->type()->set(TableField::TYPE_VARCHAR_255));
    }

    private function itemSchema(){
        return TableSchema::create()
            ->name()->set('item')
            ->addField(TableField::create()
                ->name()->set('itemId')
                ->type()->set(TableField::TYPE_BIGINT)
                ->autoIncrement()->set(TRUE)
                ->primaryKey()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('deleted')
                ->type()->set(TableField::TYPE_TINYINT))
            ->addField(TableField::create()
                ->name()->set('packed')
                ->type()->set(TableField::TYPE_TINYINT))
            ->addField(TableField::create()
                ->name()->set('createdBy')
                ->type()->set(TableField::TYPE_BIGINT)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('createdDate')
                ->type()->set(TableField::TYPE_DATETIME))
            ->addField(TableField::create()
                ->name()->set('updatedBy')
                ->type()->set(TableField::TYPE_BIGINT)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('updatedDate')
                ->type()->set(TableField::TYPE_DATETIME))
            ->addField(TableField::create()
                ->name()->set('tripId')
                ->type()->set(TableField::TYPE_BIGINT)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('detail')
                ->type()->set(TableField::TYPE_VARCHAR_255));
    }

    private function followSchema(){
        return TableSchema::create()
            ->name()->set('follow')
            ->addField(TableField::create()
                ->name()->set('followId')
                ->type()->set(TableField::TYPE_BIGINT)
                ->autoIncrement()->set(TRUE)
                ->primaryKey()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('userId')
                ->type()->set(TableField::TYPE_BIGINT)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('followUserId')
                ->type()->set(TableField::TYPE_BIGINT)
                ->key()->set(TRUE));
    }

    private function followTripSchema(){
        return TableSchema::create()
            ->name()->set('followTrip')
            ->addField(TableField::create()
                ->name()->set('followTripId')
                ->type()->set(TableField::TYPE_BIGINT)
                ->autoIncrement()->set(TRUE)
                ->primaryKey()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('userId')
                ->type()->set(TableField::TYPE_BIGINT)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('tripId')
                ->type()->set(TableField::TYPE_BIGINT)
                ->key()->set(TRUE));
    }

    private function followRequestSchema(){
        return TableSchema::create()
            ->name()->set('followRequest')
            ->addField(TableField::create()
                ->name()->set('followRequestId')
                ->type()->set(TableField::TYPE_BIGINT)
                ->autoIncrement()->set(TRUE)
                ->primaryKey()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('userId')
                ->type()->set(TableField::TYPE_BIGINT)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('statusId')
                ->type()->set(TableField::TYPE_TINYINT)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('requestToFollowId')
                ->type()->set(TableField::TYPE_BIGINT)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('createdDate')
                ->type()->set(TableField::TYPE_DATE_TIME)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('message')
                ->type()->set(TableField::TYPE_VARCHAR_512));
    }

    private function followInviteSchema(){
        return TableSchema::create()
            ->name()->set('followInvite')
            ->addField(TableField::create()
                ->name()->set('followInviteId')
                ->type()->set(TableField::TYPE_BIGINT)
                ->autoIncrement()->set(TRUE)
                ->primaryKey()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('userId')
                ->type()->set(TableField::TYPE_BIGINT)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('statusId')
                ->type()->set(TableField::TYPE_TINYINT)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('inviteEmail')
                ->type()->set(TableField::TYPE_VARCHAR_100)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('createdDate')
                ->type()->set(TableField::TYPE_DATE_TIME)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('message')
                ->type()->set(TableField::TYPE_VARCHAR_512));
    }

    private function fellowTravellerSchema(){
        return TableSchema::create()
            ->name()->set('fellowTraveller')
            ->addField(TableField::create()
                ->name()->set('fellowTravellerId')
                ->type()->set(TableField::TYPE_BIGINT)
                ->autoIncrement()->set(TRUE)
                ->primaryKey()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('userId')
                ->type()->set(TableField::TYPE_BIGINT)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('tripId')
                ->type()->set(TableField::TYPE_BIGINT)
                ->key()->set(TRUE));
    }

    private function postSchema(){
        return TableSchema::create()
            ->name()->set('post')
            ->addField(TableField::create()
                ->name()->set('postId')
                ->type()->set(TableField::TYPE_BIGINT)
                ->autoIncrement()->set(TRUE)
                ->primaryKey()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('deleted')
                ->type()->set(TableField::TYPE_TINYINT))
            ->addField(TableField::create()
                ->name()->set('postToTrip')
                ->type()->set(TableField::TYPE_TINYINT)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('createdBy')
                ->type()->set(TableField::TYPE_BIGINT)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('createdDate')
                ->type()->set(TableField::TYPE_DATETIME))
            ->addField(TableField::create()
                ->name()->set('updatedBy')
                ->type()->set(TableField::TYPE_BIGINT)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('updatedDate')
                ->type()->set(TableField::TYPE_DATETIME))
            ->addField(TableField::create()
                ->name()->set('tripId')
                ->type()->set(TableField::TYPE_BIGINT)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('dayId')
                ->type()->set(TableField::TYPE_BIGINT)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('youtube')
                ->type()->set(TableField::TYPE_VARCHAR_100))
            ->addField(TableField::create()
                ->name()->set('text')
                ->type()->set(TableField::TYPE_TEXT));
    }

    private function postMediaSchema(){
        return TableSchema::create()
            ->name()->set('postMedia')
            ->addField(TableField::create()
                ->name()->set('postMediaId')
                ->type()->set(TableField::TYPE_BIGINT)
                ->autoIncrement()->set(TRUE)
                ->primaryKey()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('priority')
                ->type()->set(TableField::TYPE_INT)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('postId')
                ->type()->set(TableField::TYPE_BIGINT)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('fileId')
                ->type()->set(TableField::TYPE_BIGINT)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('thumbId')
                ->type()->set(TableField::TYPE_BIGINT)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('createdBy')
                ->type()->set(TableField::TYPE_BIGINT)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('createdDate')
                ->type()->set(TableField::TYPE_DATETIME))
            ->addField(TableField::create()
                ->name()->set('updatedBy')
                ->type()->set(TableField::TYPE_BIGINT)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('updatedDate')
                ->type()->set(TableField::TYPE_DATETIME))
            ->addField(TableField::create()
                ->name()->set('latitude')
                ->type()->set(TableField::TYPE_VARCHAR_20))
            ->addField(TableField::create()
                ->name()->set('longitude')
                ->type()->set(TableField::TYPE_VARCHAR_20))
            ->addField(TableField::create()
                ->name()->set('location')
                ->type()->set(TableField::TYPE_VARCHAR_100))
            ->addField(TableField::create()
                ->name()->set('caption')
                ->type()->set(TableField::TYPE_VARCHAR_100));
    }

    private function commentSchema(){
        return TableSchema::create()
            ->name()->set('comment')
            ->addField(TableField::create()
                ->name()->set('commentId')
                ->type()->set(TableField::TYPE_BIGINT)
                ->autoIncrement()->set(TRUE)
                ->primaryKey()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('deleted')
                ->type()->set(TableField::TYPE_TINYINT))
            ->addField(TableField::create()
                ->name()->set('createdBy')
                ->type()->set(TableField::TYPE_BIGINT)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('createdDate')
                ->type()->set(TableField::TYPE_DATETIME))
            ->addField(TableField::create()
                ->name()->set('updatedBy')
                ->type()->set(TableField::TYPE_BIGINT)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('updatedDate')
                ->type()->set(TableField::TYPE_DATETIME))
            ->addField(TableField::create()
                ->name()->set('postId')
                ->type()->set(TableField::TYPE_BIGINT)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('parentCommentId')
                ->type()->set(TableField::TYPE_BIGINT)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('text')
                ->type()->set(TableField::TYPE_TEXT));
    }

    private function tokenSchema(){
        return TableSchema::create()
            ->name()->set('token')
            ->addField(TableField::create()
                ->name()->set('tokenId')
                ->type()->set(TableField::TYPE_BIGINT)
                ->autoIncrement()->set(TRUE)
                ->primaryKey()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('deleted')
                ->type()->set(TableField::TYPE_TINYINT))
            ->addField(TableField::create()
                ->name()->set('createdBy')
                ->type()->set(TableField::TYPE_BIGINT)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('createdDate')
                ->type()->set(TableField::TYPE_DATETIME))
            ->addField(TableField::create()
                ->name()->set('updatedBy')
                ->type()->set(TableField::TYPE_BIGINT)
                ->key()->set(TRUE))
            ->addField(TableField::create()
                ->name()->set('updatedDate')
                ->type()->set(TableField::TYPE_DATETIME))
            ->addField(TableField::create()
                ->name()->set('tripId')
                ->type()->set(TableField::TYPE_BIGINT)
                ->key()->set(TRUE));
    }

    /**
     * Static creator
     * @param Selmasu $s
     * @return Tables
     */
    public static function create(Selmasu $s)
    {
        return new self($s);
    }
}