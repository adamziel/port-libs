# PRAGMA integrity/FK pointer-map current-source next90

- Slice: `pragma-integrity-fk-pointermap-current-source-next90`
- Base: `f3fff90bf62c97408b7fcac7bc6c4cfd091e4b82`
- Behavior: added current-source cursor protection for paged `PRAGMA integrity_check` rows combined with table-valued `pragma_foreign_key_check(...)` rows.
- Non-overlap: batch88 already covered PRAGMA integrity FK/index checks; this patch adds the missing table-valued FK current-source resume path and stale cursor rejection, not another plain PRAGMA or index-integrity case.
- Focused evidence: `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIntegrityForeignKeyPointerMapCurrentSourceNext90Test.php` passed with `1 test files, 63 assertions, 0 failures` and `48` PASS lines.
- Application smoke: `php lanes/libsqlite/examples/application-pragma-integrity-fk-pointermap-current-source-next90.php --self-test` passes and covers copied `wp_options` archive diagnostics.
- Dashboard delta: expected `phpPass` `35300 -> 35348`; mapped coverage `517 / 1589 -> 518 / 1589`.
- Dependency closure: no new support component needed. Reuses `SQLitePragmaIntegrityCurrentNextYield`, `SQLitePragmaForeignKeyIntegrity`, existing pointer-map parsing, and `SQLiteAttachedSchemaCatalog`.
- Root harness: not run, isolated micro-slice.
