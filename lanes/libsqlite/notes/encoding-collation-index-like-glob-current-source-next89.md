# encoding-collation-index-like-glob-current-source-next89

Behavior:

- Added `SQLiteEncodingCollationIndexLikeGlobCurrentSourceNextPlan` for current-source to next-source LIKE/GLOB index cursor handoff diagnostics over encoded `wp_options.option_name` rows.
- The plan keeps escaped LIKE prefix/range metadata, GLOB prefix ranges, schema-cookie changes, collation-version changes, UTF-8/UTF-16 source byte changes, and matched-rowset deltas in one cursor reuse decision.
- It reuses existing LIKE/GLOB residual matching and encoded source cursor primitives rather than changing accepted matcher semantics.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingCollationIndexLikeGlobCurrentSourceNext89Test.php`
  - `1 test files, 75 assertions, 0 failures`

Application smoke:

- `php lanes/libsqlite/examples/application-encoding-collation-index-like-glob-current-source-next89.php --self-test`
  - `application-encoding-collation-index-like-glob-current-source-next89 self-test passed`

Non-overlap:

This avoids accepted Unicode GLOB range matching, UTF-16 malformed text guards, UTF-16 LIKE/GLOB affinity cursors, next86 source-switch rowset comparison, next83 affinity/collation ranges, current/next LIKE cursor ranges, JSON table cursor/source/constraint work, SQL SELECT text/group/order/subquery work, and VFS/WAL/B-tree apply clusters. The new surface is the index cursor reuse decision across current and next sources when LIKE ESCAPE or GLOB predicates meet schema-cookie and collation-version changes.

Dependency closure:

No new support component is needed. This reuses the native PHP LIKE/GLOB matcher, collation range planner, and encoded current-source cursor helpers already in `lanes/libsqlite/src`.
