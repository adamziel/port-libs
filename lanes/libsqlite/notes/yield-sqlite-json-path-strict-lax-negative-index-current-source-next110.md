# SQLite JSON path strict/lax negative index current-source next110

Status: focused PHP behavior growth for SQLite JSON path grammar parity.

This slice adds `SQLiteJsonPathStrictLaxNegativeIndexCurrentSourceNextPlan`.
It compares current/next copied `wp_options` JSON rows and records path
diagnostics for the SQLite JSON path grammar boundary:

- `[#-N]` reverse array indexes are valid SQLite paths and remain locatable for
  JSON text and JSONB option values.
- SQL/JSON-style `strict $...` and `lax $...` prefixes are rejected as
  malformed SQLite paths.
- `[-N]` negative array indexes are rejected; SQLite requires `[#-N]`.
- current/next source diagnostics preserve current-reader policy, next-reader
  abort policy for malformed path input, rowid-level result signatures, missing
  path rows, and malformed JSON source rows.

WordPress smoke:

- `lanes/libsqlite/examples/wordpress-json-path-strict-lax-negative-index-current-source-next110.php`
  reports accepted `[#-N]` option-setting paths, rejected strict/lax/`[-N]`
  path forms, and current/next last-plugin slugs for copied `wp_options` rows.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonPathStrictLaxNegativeIndexCurrentSourceNext110Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 85 assertions, 0 failures
```

PASS-line delta: `+54` focused PASS lines. `lane-status.json` `phpPass` moves
from `42491` to `42545`. Mapped upstream coverage is unchanged because this
reuses already mapped JSON path extraction and JSONB path behavior rather than
adding a fresh manifest-backed row.

Non-overlap: avoids accepted batch106 JSONB malformed path/operator diagnostics
by not changing JSONB malformed-source operator lazy evaluation. This slice is
the SQLite path grammar boundary for strict/lax prefixes and `[-N]` negative
indexes, plus current/next source diagnostics over copied WordPress option
rows.

Dependency closure: no new support component is needed. The slice reuses
lane-local `SQLiteJsonInspection`, `SQLiteJsonPath`, `SQLiteJsonB`, and JSON
canonicalization helpers.
