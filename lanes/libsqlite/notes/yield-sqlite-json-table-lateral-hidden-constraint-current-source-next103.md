# JSON Table Lateral Hidden Constraint Current Source Next103

Slice: `json-table-lateral-hidden-constraint-current-source-next103`

## Behavior

Adds `SQLiteJsonTablePlan::lateralHiddenConstraintCurrentSourceNext103()`, a keyed current/next planner for lateral `json_each()` / `json_tree()` hidden `json`/`root` constraints. The planner pairs current and next host rows by a stable host key column instead of by array ordinal, so a reordered Application options source does not look like a changed JSON source tape.

Covered behavior:

- Stable host-row reorder is recorded in `hostOrderTransition` but does not require a JSON table replan.
- Changed keyed host rows still report `source-json-changed`, `source-root-changed`, and hidden residual rowset changes.
- Added and removed keyed host rows retain explicit transition reasons.
- Duplicate hidden `root` residuals continue to flow through the accepted next88 planner.
- Left-join null extension and JSONB current/next input-kind transitions remain visible per keyed host.
- Missing/duplicate host keys and invalid planner inputs are rejected.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableLateralHiddenConstraintCurrentSourceNext103Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 62 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-json-table-lateral-hidden-constraint-current-source-next103.php --self-test
```

Syntax:

```text
php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php
php -l lanes/libsqlite/tests/SQLiteJsonTableLateralHiddenConstraintCurrentSourceNext103Test.php
php -l lanes/libsqlite/examples/application-json-table-lateral-hidden-constraint-current-source-next103.php
```

## Non-Overlap

This does not repeat accepted parser-level JSON table SELECT/FROM wiring, JSON table cursor behavior, hidden `json`/`root` extraction, visible-column pushdown, rowid alias constraints, or prior lateral hidden planner ordinal pairing. The new behavior is keyed current-source tracking for lateral hidden constraints when the host source order changes.

## Dependency Closure

No new support component is needed. The slice reuses the native JSON parser, JSON table planner, hidden residual constraint handling, JSONB input detection, and TestRunner harness already present in `lanes/libsqlite`.
