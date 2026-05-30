# encoding-like-glob-collation-source-current-source-next86

- Behavior: adds `SQLiteEncodingLikeGlobSourceSwitchPlan` for current-source to next-source LIKE/GLOB cursor invalidation over encoded `wp_options.option_name` rows. The plan composes existing UTF-8/UTF-16 source decoding and LIKE/GLOB collation range scans, then reports retained, exited, entered, encoding-changed, and byte-changed rowids when a rebuilt next source replaces the current cursor source.
- Focused evidence:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingLikeGlobSourceSwitchCurrentNext86Test.php`
  - `1 test files, 55 assertions, 0 failures`
  - 55 focused PASS lines.
- Application smoke:
  - `php lanes/libsqlite/examples/application-option-name-source-switch-current-next86.php --self-test`
  - `application-option-name-source-switch-current-next86 self-test passed`
- Non-overlap: avoids accepted Unicode GLOB ranges, UTF-16 malformed guard, malformed text cursor comparisons, LIKE current/next cursor ranges, batch82 UTF-16 encoding/collation source cursors, JSON source/cursor/constraint clusters, B-tree overflow/freelist/page-move/root clusters, WAL/VFS transaction application, and SELECT SQL text/group/order/subquery work. This slice only covers the current encoded source to next encoded source invalidation boundary for LIKE/GLOB cursors.
- Dependency closure: no new support component is needed. The slice reuses lane-local native PHP encoding, LIKE/GLOB, and collation cursor primitives; no ext/sqlite, upstream binary, network, or live service dependency is introduced.
