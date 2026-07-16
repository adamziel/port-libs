# PRAGMA index_xinfo foreign_key current-source next799-814

Extends the existing PRAGMA index_xinfo/foreign-key current-source action
relationship diagnostic wrappers through `page799()` to `page814()`.

The slice reuses `actionRelationshipDiagnosticPage311()` and repeats the
accepted mixed update/delete action matrix from next783-798:

- order mismatch child lookup indexes for CASCADE/RESTRICT, NO ACTION/SET NULL,
  and SET DEFAULT/CASCADE action pairs
- collation mismatch child lookup indexes for the same action pairs
- DESC mismatch child lookup indexes for RESTRICT/NO ACTION and
  SET NULL/SET DEFAULT action pairs

Validation:

```sh
php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php
php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext799814Test.php
php -l lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next799-814.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext783798Test.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext799814Test.php
php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next799-814.php --self-test
git diff --check
```

Scope is limited to the consolidated SQLite PRAGMA index_xinfo/foreign-key
current-source lane and directly corresponding next799-814 test, example, and
note artifacts.
