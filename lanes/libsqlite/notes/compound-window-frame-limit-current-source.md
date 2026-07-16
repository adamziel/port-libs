# compound-window-frame-limit-current-source

Status: focused PHP behavior growth for parser-level compound SELECT output where
each arm computes current-row window frames and the compound tail applies
`ORDER BY ... LIMIT/OFFSET` after the windowed rows are combined.

Behavior covered:

- `UNION ALL` arms retain their own `ROWS BETWEEN CURRENT ROW AND N FOLLOWING`
  window-frame values before compound result ordering.
- Compound tail `ORDER BY frame_weight DESC, id ASC LIMIT 5 OFFSET 1` is applied
  after both windowed arms are combined.
- Current-source/next-source comparison records changed limited rows, frame
  metadata, limit boundary rows, and replan reasons when new Application option
  rows move across the LIMIT boundary.

Verification:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundWindowFrameLimitCurrentSourceNextTest.php
# Focused test run: 1 selected test files (root lock skipped)
# ...
# 1 test files, 156 assertions, 0 failures
```

Focused PASS-line delta: `+51`, moving lane-local `phpPass` from `54864` to
`54915`. Mapped upstream coverage is unchanged because this is a behavior-backed
PHP current-source composition over already mapped compound SELECT, window
frame, and LIMIT inventory.

Application smoke:

```bash
php lanes/libsqlite/examples/application-compound-window-frame-limit-current-source.php --self-test
# application-compound-window-frame-limit-current-source self-test passed
```

Non-overlap: this does not repeat accepted compound recursive affinity/window
next129 behavior, SELECT SQL GROUP/JOIN/subquery/ORDER-expression/comma-LIMIT
clusters, named-window subquery work, JSON table cursor/source/constraint work,
WAL/VFS/B-tree/storage clusters, or Unicode GLOB/collation slices. The new
surface is the composition of per-arm current-row window frame values with a
post-compound LIMIT boundary and current-source/next-source row movement.

Dependency closure: no new support component is needed. The slice reuses the
lane-local parser-level SELECT executor, compound SELECT combiner, window
function frame evaluator, and result LIMIT/OFFSET machinery.
