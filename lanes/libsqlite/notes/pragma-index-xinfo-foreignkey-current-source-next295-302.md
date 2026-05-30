# PRAGMA index_xinfo/foreign_key current-source next295-302

Prepared the next PRAGMA/FK current-source slice after next287-294.

- next295: child/parent affinity mismatch across `PRAGMA foreign_key_list` and `table_info`.
- next296: child/parent declared collation mismatch.
- next297: composite child key with only a partial nullable component set.
- next298: self-referential foreign key.
- next299: cascading self-reference.
- next300: `RESTRICT` relationship without a child lookup index prefix visible through `index_xinfo`.
- next301: `NO ACTION` relationship without a child lookup index prefix.
- next302: deferrable foreign key clause.

Validation target: focused test `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext295302Test.php` and example self-test `application-pragma-index-xinfo-foreignkey-current-source-next295-302.php --self-test`.
