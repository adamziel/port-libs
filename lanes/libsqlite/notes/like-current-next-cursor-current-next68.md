# LIKE current/next cursor current-next68

Implemented `SQLiteLikeCurrentNextCursor` for bounded LIKE index cursor behavior after the existing LIKE/collation range plan has selected a usable range. The cursor sorts copied index entries by `BINARY`, `NOCASE`, or `RTRIM`, seeks to the range lower bound, exposes current/next boundary diagnostics, and still applies residual `LIKE` matching so escaped wildcard literals, case-sensitive binary ranges, and unsafe Unicode NOCASE prefixes do not leak rows.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteLikeCurrentNextCursorCurrentNext68Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 56 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-like-current-next-cursor-current-next68.php
```

Non-overlap: this avoids accepted Unicode GLOB ranges, LIKE collation prefix-range planning current-next65, malformed UTF-16 guards, SELECT SQL text/subquery/group/order clusters, JSON table source/cursor/constraint clusters, WAL/VFS rollback/savepoint/checkpoint/write/lock/sync clusters, B-tree page move/root-collapse/overflow/freelist clusters, and expression-index collation cursor current-next56. The new behavior is the current/next row boundary and residual LIKE filtering for a range cursor.

Dependency closure: no new support component is needed. The slice reuses native PHP LIKE matching and LIKE collation planning, adding only a lane-local cursor helper.

Next task: wire this cursor into broader parser/planner execution for option-name LIKE predicates when a real index scan path is selected.
