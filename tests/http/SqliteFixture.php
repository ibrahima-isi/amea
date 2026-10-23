<?php
/** Test database dialect bridge; registration's only MySQL-only statement.
 * This does not qualify MySQL/schema compatibility (deployment rehearsal does).
 */
class SqliteFixture extends PDO
{
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $query = str_replace('INSERT IGNORE INTO', 'INSERT OR IGNORE INTO', $query);
        return parent::prepare($query, $options);
    }
}
