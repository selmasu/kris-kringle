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
            'krisKringle' => 'krisKringle',
            'krisKringleGroup' => 'krisKringleGroup',
            'krisKringleAssignment' => 'krisKringleAssignment',
            'krisKringleMember' => 'krisKringleMember',
            'krisKringleProfilePhoto' => 'krisKringleProfilePhoto',
            'krisKringleAppleIdentity' => 'krisKringleAppleIdentity',
            'krisKringleGoogleIdentity' => 'krisKringleGoogleIdentity'
        ];
    }

    private function setTableSchemas()
    {
        $this->tableSchemas = array_merge($this->appTableSchemas(), CoreTables::create($this->s)->coreTableSchemas());
    }

    private function appTableSchemas()
    {
        return [
            'krisKringle' => $this->krisKringleSchema(),
            'krisKringleGroup' => $this->krisKringleGroupSchema(),
            'krisKringleAssignment' => $this->krisKringleAssignmentSchema(),
            'krisKringleMember' => $this->krisKringleMemberSchema(),
            'krisKringleProfilePhoto' => $this->krisKringleProfilePhotoSchema(),
            'krisKringleAppleIdentity' => $this->krisKringleAppleIdentitySchema(),
            'krisKringleGoogleIdentity' => $this->krisKringleGoogleIdentitySchema()
        ];
    }

    public function tables(){
        return $this->tables;
    }

    /** App Tables */

    public function krisKringle(){
        return $this->tables['krisKringle'];
    }

    public function krisKringleGroup(){
        return $this->tables['krisKringleGroup'];
    }

    public function krisKringleAssignment(){
        return $this->tables['krisKringleAssignment'];
    }

    public function krisKringleMember(){
        return $this->tables['krisKringleMember'];
    }

    public function getSchema($tableName){
        $this->setTableSchemas();

        if (isset($this->tableSchemas[$tableName])) {
            return $this->tableSchemas[$tableName];
        }

        return '';
    }

    public function krisKringleProfilePhoto(){
        return $this->tables['krisKringleProfilePhoto'];
    }

    public function krisKringleAppleIdentity(){
        return $this->tables['krisKringleAppleIdentity'];
    }

    public function krisKringleGoogleIdentity(){
        return $this->tables['krisKringleGoogleIdentity'];
    }

    private function krisKringleGoogleIdentitySchema(){
        return TableSchema::create()
            ->name()->set('krisKringleGoogleIdentity')
            ->addField(TableField::create()->name()->set('userId')->type()->set(TableField::TYPE_BIGINT)->primaryKey()->set(true))
            ->addField(TableField::create()->name()->set('subjectHash')->type()->set(TableField::TYPE_VARCHAR_100)->unique()->set(true));
    }

    private function krisKringleProfilePhotoSchema(){
        return TableSchema::create()
            ->name()->set('krisKringleProfilePhoto')
            ->addField(TableField::create()->name()->set('userId')->type()->set(TableField::TYPE_BIGINT)->primaryKey()->set(true))
            ->addField(TableField::create()->name()->set('pictureFileSerial')->type()->set(TableField::TYPE_BIGINT)->isNull()->set(true));
    }

    private function krisKringleAppleIdentitySchema(){
        return TableSchema::create()
            ->name()->set('krisKringleAppleIdentity')
            ->addField(TableField::create()->name()->set('userId')->type()->set(TableField::TYPE_BIGINT)->primaryKey()->set(true))
            ->addField(TableField::create()->name()->set('subjectHash')->type()->set(TableField::TYPE_VARCHAR_100)->isNull()->set(true)->unique()->set(true));
    }

    private function krisKringleGroupSchema(){
        return TableSchema::create()
            ->name()->set('krisKringleGroup')
            ->charset()->set('utf8mb4')
            ->addField(TableField::create()->name()->set('groupId')->type()->set(TableField::TYPE_BIGINT)->autoIncrement()->set(true)->primaryKey()->set(true))
            ->addField(TableField::create()->name()->set('krisKringleId')->type()->set(TableField::TYPE_BIGINT)->key()->set(true))
            ->addField(TableField::create()->name()->set('name')->type()->set(TableField::TYPE_VARCHAR_100));
    }

    private function krisKringleAssignmentSchema(){
        return TableSchema::create()
            ->name()->set('krisKringleAssignment')
            ->addField(TableField::create()->name()->set('giverId')->type()->set(TableField::TYPE_BIGINT)->primaryKey()->set(true))
            ->addField(TableField::create()->name()->set('recipientId')->type()->set(TableField::TYPE_BIGINT)->unique()->set(true))
            ->addField(TableField::create()->name()->set('krisKringleId')->type()->set(TableField::TYPE_BIGINT)->key()->set(true));
    }

    private function krisKringleMemberSchema(){
        return TableSchema::create()
            ->name()->set('krisKringleMember')
            ->addField(TableField::create()->name()->set('krisKringleMemberId')->type()->set(TableField::TYPE_BIGINT)->autoIncrement()->set(TRUE)->primaryKey()->set(TRUE))
            ->addField(TableField::create()->name()->set('krisKringleId')->type()->set(TableField::TYPE_BIGINT)->key()->set(TRUE))
            ->addField(TableField::create()->name()->set('userId')->type()->set(TableField::TYPE_BIGINT)->key()->set(TRUE))
            ->addField(TableField::create()->name()->set('groupId')->type()->set(TableField::TYPE_BIGINT))
            ->addField(TableField::create()->name()->set('parentUserId')->type()->set(TableField::TYPE_BIGINT)->isNull()->set(false))
            ->addField(TableField::create()->name()->set('name')->type()->set(TableField::TYPE_VARCHAR_100 . ' CHARACTER SET utf8mb4')->isNull()->set(false));
    }

    private function krisKringleSchema(){
        return TableSchema::create()
            ->name()->set('krisKringle')
            ->addField(TableField::create()->name()->set('krisKringleId')->type()->set(TableField::TYPE_BIGINT)->autoIncrement()->set(TRUE)->primaryKey()->set(TRUE))
            ->addField(TableField::create()->name()->set('deleted')->type()->set(TableField::TYPE_TINYINT))
            ->addField(TableField::create()->name()->set('year')->type()->set(TableField::TYPE_YEAR))
            ->addField(TableField::create()->name()->set('createdBy')->type()->set(TableField::TYPE_BIGINT)->key()->set(TRUE))
            ->addField(TableField::create()->name()->set('createdDate')->type()->set(TableField::TYPE_DATETIME))
            ->addField(TableField::create()->name()->set('updatedBy')->type()->set(TableField::TYPE_BIGINT)->key()->set(TRUE))
            ->addField(TableField::create()->name()->set('updatedDate')->type()->set(TableField::TYPE_DATETIME))
            ->addField(TableField::create()->name()->set('name')->type()->set(TableField::TYPE_VARCHAR_100))
            ->addField(TableField::create()->name()->set('pictureVersion')->type()->set(TableField::TYPE_VARCHAR_100)->defaultValue()->set(''))
            ->addField(TableField::create()->name()->set('pictureFileSerial')->type()->set(TableField::TYPE_BIGINT)->defaultValue()->set('0'));
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