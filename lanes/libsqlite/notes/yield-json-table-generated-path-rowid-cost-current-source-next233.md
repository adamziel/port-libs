# JSON table generated path rowid cost current-source next233

Behavior slice: `json-table-generated-path-rowid-cost-current-source-next233`

- Adds `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext233()`.
- Composes accepted next224 xCurrent yield-guard state into a bounded xNext admission profile.
- Allows xNext to resume only from remaining rowids when the current source, yield-guard fingerprint, and active rowid are still valid.
- Forces restart/reprepare for changed next-source settings, stale fingerprints, stale rowids, and empty generated-path ranges.
- Adds a copied `wp_options` smoke for plugin-rule JSON diagnostics without requiring `ext/sqlite`.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext233Test.php`
- `php -l lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next233.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext233Test.php`
  - `1 test files, 58 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next233.php --self-test`
  - `application-json-table-generated-path-rowid-cost-current-source-next233 self-test passed`

Non-overlap:

- Avoids rejected jsonvt225/jsonvt226 broad rowid/cost changes by layering only on accepted next224 yield-guard output.
- Does not repeat accepted JSON table cursor/source wiring, hidden/visible constraints, parser-level JSON table SELECT sources, generated-path xFilter/xColumn/xCurrent profiles, alias order/range profiles, or storage/VFS/B-tree surfaces.

Dependency closure:

No new support component is needed. This reuses native JSON table row generation, generated-path rowid xCurrent yield guards, rowid alias projection, JSON path validation, and current-source fingerprints already present in `lanes/libsqlite/src`.
