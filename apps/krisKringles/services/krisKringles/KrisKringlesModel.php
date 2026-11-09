<?php
class KrisKringleModel extends Model
{
    protected function getKrisKringlesFromDB(SqlQueryBits $query, int $returnType = Model::RETURN_TYPE_ARRAY):array
    {
        $sql = "SELECT
                    krisKringle.krisKringleId,
                    krisKringle.name,
                    krisKringle.year,
                    krisKringle.createdBy,
                    krisKringle.pictureVersion "
            . $this->visibleGroupsFromWhere() . "
            " . $query->orderByString() . "
            " . $query->limitString();

        return array_map(function ($row) use ($returnType) {
            $group = KrisKringle::create()->fromArray($row);
            return $returnType === Model::RETURN_TYPE_OBJECT ? $group : $group->toArray();
        }, $this->adb->fetchAll($sql));
    }

    protected function getKrisKringlesTotalFromDB():int
    {
        return (int)$this->adb->fetchValue('total', 'SELECT COUNT(*) AS total ' . $this->visibleGroupsFromWhere());
    }

    private function visibleGroupsFromWhere():string
    {
        $userId = (int)$this->s()->userId();
        return "FROM " . $this->tables()->krisKringle() . " AS krisKringle
            WHERE krisKringle.deleted = 0
              AND (krisKringle.createdBy = " . $userId . " OR EXISTS (
                    SELECT 1 FROM " . $this->tables()->krisKringleMember() . " AS member
                    WHERE member.krisKringleId = krisKringle.krisKringleId AND member.userId = " . $userId . "
                ))";
    }

    protected function createKrisKringleInDB(string $name, int $year, int $createdBy, ?string $picture = null):int
    {
        $version = $picture === null ? '' : hash('sha256', $picture);
        $image = $picture === null ? 'NULL' : "X'" . bin2hex($picture) . "'";
        return (int)$this->adb->insertId("INSERT INTO " . $this->tables()->krisKringle() . "
            (name, `year`, createdBy, createdDate, deleted, pictureVersion, pictureData)
            VALUES ('" . $this->adb->escapeString($name) . "', " . $year . ", " . $createdBy . ", NOW(), 0, '" . $version . "', " . $image . ")");
    }

    protected function getOwnedKrisKringleFromDB(int $id, int $owner): ?array
    {
        return $this->adb->fetchAssoc("SELECT krisKringleId, name, `year`, createdBy, pictureVersion
            FROM " . $this->tables()->krisKringle() . "
            WHERE krisKringleId = " . $id . " AND createdBy = " . $owner . " AND deleted = 0") ?: null;
    }

    protected function updateKrisKringlePictureInDB(int $id, int $owner, string $picture):void
    {
        $this->adb->query("UPDATE " . $this->tables()->krisKringle() . "
            SET pictureData = X'" . bin2hex($picture) . "', pictureVersion = '" . hash('sha256', $picture) . "',
                updatedBy = " . $owner . ", updatedDate = NOW()
            WHERE krisKringleId = " . $id . " AND createdBy = " . $owner . " AND deleted = 0");
    }

    protected function getVisibleKrisKringlePictureFromDB(int $id): ?string
    {
        return $this->adb->fetchValue('pictureData', 'SELECT krisKringle.pictureData '
            . $this->visibleGroupsFromWhere() . ' AND krisKringle.krisKringleId = ' . $id) ?: null;
    }
}
