<?php namespace RestExtension\Relations;

use CodeIgniter\Database\ConnectionInterface;
use OrmExtension\DataMapper\RelationLink;
use OrmExtension\Extensions\Model;

/**
 * Key sets carried across a relation's link (OrmExtension's RelationLink), from the related
 * table's side to the model's.
 */
class LinkedKeys {

    /**
     * The model's side of a set of related keys: the same set when the key is in one of the two
     * tables, the join table's other column when it is in between.
     */
    public static function reach(RelationLink $link, KeySet $related, ConnectionInterface $db): KeySet {
        if ($link->kind != RelationLink::ThroughTable) {
            return $related;
        }
        if ($related->isEmpty()) {
            return KeySet::ofValues([]);
        }
        $base = $db->protectIdentifiers("{$link->table}.{$link->tableBaseColumn}");
        $other = $db->protectIdentifiers("{$link->table}.{$link->tableRelatedColumn}");
        $table = $db->protectIdentifiers($link->table);
        return KeySet::ofSql("SELECT {$base} AS " . KeySet::Alias . " FROM {$table} WHERE {$related->in($other, $db)} AND {$base} IS NOT NULL");
    }

    /**
     * The model's keys in the join table that lead to no row: none named, or one that is not
     * there or is deleted. OrmExtension's join pairs those with an empty row.
     */
    public static function dangling(RelationLink $link, Model $related, ConnectionInterface $db): KeySet {
        $base = $db->protectIdentifiers("{$link->table}.{$link->tableBaseColumn}");
        $other = $db->protectIdentifiers("{$link->table}.{$link->tableRelatedColumn}");
        $table = $db->protectIdentifiers($link->table);
        $relatedTable = $db->protectIdentifiers($related->getTableName());
        $relatedKey = $db->protectIdentifiers("{$related->getTableName()}.{$related->getPrimaryKey()}");
        $existing = "SELECT {$relatedKey} FROM {$relatedTable}";
        $deletedField = $related->getDeletedField();
        if ($deletedField && $link->guessed) {
            $existing .= " WHERE " . $db->protectIdentifiers("{$related->getTableName()}.{$deletedField}") . " IS NULL";
        }
        return KeySet::ofSql("SELECT {$base} AS " . KeySet::Alias . " FROM {$table} WHERE {$base} IS NOT NULL AND ({$other} IS NULL OR {$other} NOT IN ({$existing}))");
    }

}
