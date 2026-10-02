<?php namespace RestExtension\Relations;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\ConnectionInterface;

/**
 * The values of one column on the rows a model lets the caller read - its ids, or the column a
 * relation joins on - as a condition another query can use.
 *
 * Up to a limit they are fetched and written into the condition as a list, which MySQL plans
 * the same way every time. Past it they stay a sub query, so a broad filter never moves a
 * hundred thousand ids through PHP.
 */
class KeySet {

    const Alias = 'restextension_key';

    /** @var array|null the values, when they were fetched */
    public $values = null;

    /** @var string|null the query that answers them, when they were not */
    public $sql = null;

    /** @var bool whether nothing narrowed the rows: no rule, no filter, only soft deletion */
    public $everything = false;

    public static function ofValues(array $values): KeySet {
        $set = new KeySet();
        $set->values = array_values($values);
        return $set;
    }

    public static function ofSql(string $sql): KeySet {
        $set = new KeySet();
        $set->sql = $sql;
        return $set;
    }

    /**
     * The column selected as `KeySet::Alias` by $builder, fetched when there are at most $limit
     * different values, a sub query when there are more.
     */
    public static function query(BaseBuilder $builder, ConnectionInterface $db, int $limit, bool $everything): KeySet {
        // Anything preRestGet() selected on top, or a limit of its own, would not work inside
        // IN (...); a derived table keeps the one column. Plain, it is left alone, so MySQL can
        // turn it into a semi join.
        $plain = count(self::read($builder, 'QBSelect')) == 1 && !self::read($builder, 'QBLimit');
        $sql = $builder->getCompiledSelect(false);
        if ($plain) {
            $keys = $sql;
            $probe = $builder->distinct()->getCompiledSelect(false);
        } else {
            $keys = "SELECT " . self::Alias . " FROM ({$sql}) restextension_keys";
            $probe = "SELECT DISTINCT " . self::Alias . " FROM ({$sql}) restextension_keys";
        }

        $set = null;
        if ($limit > 0) {
            $rows = $db->query($probe . " LIMIT " . ($limit + 1))->getResultArray();
            if (count($rows) <= $limit) {
                $set = self::ofValues(array_column($rows, self::Alias));
            }
        }
        if (is_null($set)) {
            $set = self::ofSql($keys);
        }
        $set->everything = $everything;
        return $set;
    }

    /** @var array<string, int|null> estimated rows by table, for this process */
    private static $tableRows = [];

    /**
     * Whether a table is small enough to read through for its keys. Fetching them means the whole
     * answer: on a big table, where the caller sees only a few rows of the model, MySQL does far
     * better with a sub query it can start from those rows. On a small one the list wins, and
     * MySQL plans it the same way every time.
     *
     * The size is MySQL's estimate, asked once per table. Where it cannot be asked, the keys are
     * fetched.
     */
    public static function isSmall(ConnectionInterface $db, string $table, int $rows): bool {
        $estimate = self::tableRows($db, $table);
        return is_null($estimate) || $estimate <= $rows;
    }

    /**
     * MySQL's estimate of a table's rows, asked once per table; null where it cannot be asked.
     */
    public static function tableRows(ConnectionInterface $db, string $table): ?int {
        $key = $db->getDatabase() . '.' . $table;
        if (!array_key_exists($key, self::$tableRows)) {
            self::$tableRows[$key] = null;
            try {
                $row = $db->query('SELECT TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$db->prefixTable($table)])->getRowArray();
                if ($row && !is_null($row['TABLE_ROWS'])) {
                    self::$tableRows[$key] = (int)$row['TABLE_ROWS'];
                }
            } catch (\Throwable $e) {
            }
        }
        return self::$tableRows[$key];
    }

    /**
     * These keys without those: as a sub query, unless both are lists.
     */
    public function except(KeySet $other, ConnectionInterface $db): KeySet {
        if ($other->isEmpty()) {
            return $this;
        }
        if (!is_null($this->values) && !is_null($other->values)) {
            return self::ofValues(array_diff($this->values, $other->values));
        }
        if (!is_null($this->values)) {
            // Only sets fetched from small tables are lists; written out as rows they can still be
            // a derived table
            $rows = array_map(function ($value) use ($db) {
                return 'SELECT ' . $db->escape((string)$value) . ' AS ' . self::Alias;
            }, $this->values);
            $sql = count($rows) ? implode(' UNION ALL ', $rows) : 'SELECT NULL AS ' . self::Alias . ' FROM DUAL WHERE 1 = 0';
        } else {
            $sql = $this->sql;
        }
        $key = 'restextension_except.' . self::Alias;
        return self::ofSql("SELECT {$key} FROM ({$sql}) restextension_except WHERE " . $other->in($key, $db, true, true));
    }

    public function isEmpty(): bool {
        return !is_null($this->values) && count($this->values) == 0;
    }

    /**
     * `$column IN (...)`, or NOT IN. $column is written as it is, so it has to be quoted already.
     *
     * A sub query is `IN (...)`, which MySQL makes a semi join of where it can: a condition of
     * its own, ANDed to the rest. Anywhere else - ORed with another condition, or NOT IN - it
     * works the sub query out in full once, every key of every row the caller may read, before
     * it looks at a single row. $correlated asks for `EXISTS (...)` tied to $column instead,
     * which it looks up row by row. The sub query is a derived table there, so the names inside it - the
     * same table, joined in by a rule - never meet the ones outside.
     */
    public function in(string $column, ConnectionInterface $db, bool $not = false, bool $correlated = false): string {
        if (!is_null($this->values)) {
            if (count($this->values) == 0) {
                // Nothing is in an empty set, and everything is outside it
                return $not ? '1 = 1' : '1 = 0';
            }
            // Quoted, whatever they are: a quoted number still finds its row by the index, and an
            // unquoted one would turn a comparison with a text column into a number comparison.
            $values = implode(',', array_map(function ($value) use ($db) {
                return $db->escape((string)$value);
            }, $this->values));
            return "{$column} " . ($not ? 'NOT IN' : 'IN') . " ({$values})";
        }
        if (!$correlated) {
            return "{$column} " . ($not ? 'NOT IN' : 'IN') . " ({$this->sql})";
        }
        return ($not ? 'NOT EXISTS' : 'EXISTS') . " (SELECT 1 FROM ({$this->sql}) restextension_keys WHERE restextension_keys." . self::Alias . " = {$column})";
    }

    /**
     * A protected property of the query builder: what it selects and whether it is limited, or
     * whether it has any condition at all.
     */
    public static function read(BaseBuilder $builder, string $property) {
        return \Closure::bind(function () use ($property) {
            return $this->{$property};
        }, $builder, BaseBuilder::class)();
    }

}
