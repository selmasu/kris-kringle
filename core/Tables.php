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
            'krisKringleMember' => 'krisKringleMember'
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
            'krisKringleMember' => $this->krisKringleMemberSchema()
        ];
    }

    public function tables(){
        return $this->tables;
    }

    /** App Tables */

    public function krisKringle(){
        return $this->tables['krisKringle'];
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

    private function krisKringleMemberSchema(){
        return TableSchema::create()
            ->name()->set('krisKringleMember')
            ->addField(TableField::create()->name()->set('krisKringleMemberId')->type()->set(TableField::TYPE_BIGINT)->autoIncrement()->set(TRUE)->primaryKey()->set(TRUE))
            ->addField(TableField::create()->name()->set('krisKringleId')->type()->set(TableField::TYPE_BIGINT)->key()->set(TRUE))
            ->addField(TableField::create()->name()->set('userId')->type()->set(TableField::TYPE_BIGINT)->key()->set(TRUE));
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
            ->addField(TableField::create()->name()->set('pictureData')->type()->set('mediumblob')->isNull()->set(false));
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