# encoding-collation-cast-nocase-current-source-next129

Status: focused PHP behavior growth for CASTed `wp_options.option_value`
NOCASE `LIKE` range scans at the current-source to next-source boundary.

Behavior:

- Adds `SQLiteCastNocaseCurrentSourceNextPlan` for parser-level
  `CAST(option_value AS ...)` scans that use SQLite-style ASCII-only NOCASE
  prefix bounds while keeping the `LIKE` residual.
- Tracks current/next candidate rowids, residual matches, entered/exited
  matches, changed cast text, changed NOCASE keys, and stale cursor
  invalidation reasons.
- Covers TEXT, INTEGER, REAL, NUMERIC, BLOB, NULL, escaped wildcard, leading
  wildcard, and non-ASCII prefix behavior. Non-ASCII NOCASE remains
  ASCII-only, matching SQLite's built-in NOCASE semantics.

WordPress smoke:

- `lanes/libsqlite/examples/wordpress-cast-nocase-current-source-next129.php`
  models copied `wp_options` import scans where mixed-case plugin option
  payloads are matched through `CAST(option_value AS TEXT) LIKE
  'plugin\_cache%' COLLATE NOCASE`.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCastNocaseCurrentSourceNext129Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 74 assertions, 0 failures
```

Dependency closure: no new support component is needed. This reuses the native
PHP SELECT CAST executor, SQLite LIKE pattern planner, ASCII NOCASE folding,
and current/next source cursor accounting already present in libsqlite.

Non-overlap: avoids accepted Unicode GLOB ranges, UTF-16 malformed guards,
UTF-16 cast/RTRIM/GLOB range current-source next127, cast/collation LIKE
current-source next123, predicate-affinity current-source next109, JSON table,
VFS/WAL, B-tree, PRAGMA, trigger/FK, compound SELECT, and suite-runner
surfaces. The new surface is CASTed `NOCASE LIKE` prefix-range invalidation
over current/next copied WordPress option sources.
