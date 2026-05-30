# pragma-rootpage-pointermap-fk-current-source-next

This slice adds `SQLitePragmaRootpagePointerMapForeignKeyCurrentSourceNext`, a
current-source PRAGMA helper that annotates `foreign_key_check` violations with
the child and parent table rootpages plus their auto-vacuum pointer-map state.

The new behavior is narrower than accepted rootpage analysis and FK pagination:
it joins the two surfaces for FK blockers so a copied Application database can
tell whether a missing parent row is the only blocker or whether the child or
parent table rootpage also has a pointer-map/rootpage integrity problem.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaRootpagePointerMapForeignKeyCurrentSourceNextTest.php`
  - `Focused test run: 1 selected test files (root lock skipped)`
  - `1 test files, 68 assertions, 0 failures`
  - `57` PASS lines
- `php lanes/libsqlite/examples/application-pragma-rootpage-pointermap-fk-current-source-next.php`
  - printed blocked copied `wp_options` / `wp_terms` FK diagnostics with one
    rootpage pointer-map conflict.

Dependency closure: no new support component is needed. The helper reuses the
existing native PHP `SQLitePragmaForeignKeyIntegrity`,
`SQLitePragmaRootpageIntegrityAnalysisCurrentSourceNext`,
`SQLiteAttachedSchemaCatalog`, schema-record, and pointer-map primitives.

Non-overlap: avoids accepted PRAGMA recursive FK catalog output, PRAGMA
foreign-key/rootpage pagination, rootpage integrity analysis current-source
next111, pointer-map/FK integrity pagination, PRAGMA index_xinfo/table_info
analysis, B-tree pointer-map mutation/reuse clusters, and current-source WAL,
JSON table, VFS, planner, trigger, and encoding accepted surfaces. The new
surface is FK violation rows enriched with child/parent rootpage pointer-map
state for current-source repair gating.
