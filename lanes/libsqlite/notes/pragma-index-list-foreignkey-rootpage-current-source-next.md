# PRAGMA index_list foreign-key rootpage current-source next148

## Behavior

- Adds `SQLitePragmaIndexListForeignKeyRootpageCurrentSourceNext`, a current/next pager that combines `PRAGMA index_list(table)` catalog rows, index/table rootpage integrity rows, and foreign-key violation rows annotated with child/parent rootpage pointer-map status.
- The current source can report index rootpage and FK rootpage blockers while the next source proves the copied database repair clears them.
- Cursor resume is bound to current and next database bytes, attached schema catalog snapshots, row schemas, index-list SQL, FK SQL, integrity SQL, and offset.

## WordPress path

Copied `wp_options` imports commonly rebuild option-name/autoload indexes while also validating FK-like staging rows before handoff. The example keeps that handoff blocked until the repaired next image has stable `PRAGMA index_list(wp_options)` metadata, no index rootpage errors, and no FK rootpage pointer-map blockers.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexListForeignKeyRootpageCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/wordpress-pragma-index-list-foreignkey-rootpage-current-source-next.php`

## Non-overlap

This does not repeat accepted next143 `index_list` plus rootpage current/next behavior, next125 `index_xinfo` plus FK rootpage behavior, or next138/141 quickcheck/index/FK behavior. It covers the narrower unhandled combination of `PRAGMA index_list` catalog admission with FK rootpage pointer-map gating across current and repaired next sources.

## Dependency closure

No new support component is needed; this reuses existing native PHP schema catalog, table/index page assembly, pointer-map analysis, `PRAGMA index_list`, and foreign-key integrity helpers.
