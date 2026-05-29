# PRAGMA index_xinfo foreign_key current-source next751-766

This slice extends `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext` from the
integrated next735-750 coverage through next751-766 using the existing
`actionRelationshipDiagnosticPage311()` factory. The new wrappers keep the same
current/next source pagination surface while assigning fresh slice IDs for the
order, collation, and DESC child lookup mismatch diagnostics.

Focused coverage:

- next751-766 repeat the accepted mixed update/delete action relationship matrix
  from next735-750 with new method wrappers only.
- each case asserts current rows remain clean while next-source rows report the
  expected `*_mismatch_child_lookup_index` status.
- the WordPress example self-test verifies all `page751()` through `page766()`
  wrappers exist and produce `0 -> 1` diagnostic rows.

Validation:

```sh
php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php
php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext751766Test.php
php -l lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next751-766.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext735750Test.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext751766Test.php
php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next751-766.php --self-test
git diff --check
```

Non-overlap: this work is limited to the consolidated PRAGMA index_xinfo /
foreign-key current-source lane and directly corresponding next751-766 test,
example, and note artifacts. It does not add a new numbered implementation
class or touch unrelated b-tree/pointer-map current-source slices.
