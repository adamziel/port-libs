# PRAGMA index_xinfo / foreign_key deferral current-source next169

## Behavior

- Adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`, layered on the accepted next165 current/next PRAGMA page.
- Preserves `PRAGMA index_xinfo`, parent-index admission, `foreign_key_check`, action/match metadata, pagination, and stale-cursor validation.
- Adds SQLite DDL-derived foreign-key deferral metadata that `PRAGMA foreign_key_list` does not expose: `deferrable`, `initially_deferred`, `deferred_until_commit`, row-level `deferral_summary`, and source-level deferral counts.
- Covers inline `REFERENCES ... DEFERRABLE INITIALLY DEFERRED`, table-level `FOREIGN KEY ... DEFERRABLE INITIALLY IMMEDIATE`, and immediate / `NOT DEFERRABLE` constraints for copied WordPress `wp_options` import repair flows.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
  - `1 test files, 64 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next169.php --self-test`
  - `wordpress-pragma-index-xinfo-foreignkey-current-source-next169 self-test passed`

## Non-Overlap

This avoids accepted next165 action/match metadata, next164 case-folded table/column keys, next161/163 catalog-derived FK columns and implicit parent keys, accepted PRAGMA optimize/index_xinfo/table_info analysis, and accepted PRAGMA recursive foreign-key catalog output. The new surface is the missing `DEFERRABLE` / `INITIALLY` DDL classification beside current-source `index_xinfo` and `foreign_key_check` rows.

## Dependency Closure

No new support component is needed. The slice reuses `SQLitePragmaSchemaCatalog`, existing PRAGMA index/FK collectors, and a bounded table-DDL splitter local to the next169 wrapper.
