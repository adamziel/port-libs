# real-upstream-corpus-json1-jsonb-dynamic-20260531T034621Z-0

Added `SQLiteRealUpstreamJson101TableValueInvariantDynamicTest.php`, a focused
PHP port of hydrated upstream `/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test`
table-valued JSON behavior.

Covered upstream sections:

- `json101-5.1`: sample JSON documents used by the table-valued checks.
- `json101-5.3`: `json_tree()` `fullkey` equals `path` plus `key`.
- `json101-5.4`: `json_each()` `fullkey` equals `path` plus `key`.
- `json101-5.5` and `json101-5.6`: hidden `json` output preserves the input.
- `json101-5.7` and `json101-5.8`: scalar `value` equals `atom`.
- `json101-5.10` and `json101-5.11`: array/object `value` rows keep JSON
  subtype behavior when inserted into another JSON document, while scalar text
  remains text.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson101TableValueInvariantDynamicTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 23361 assertions, 0 failures
```

PASS-line delta if accepted: `+176` focused TestRunner PASS cases. Mapped
denominator coverage remains `1589 / 1589`.

Non-overlap: this does not repeat JSON table parser `FROM`/`JOIN` source
wiring, hidden/visible constraint pushdown, cursor rewind/eof behavior,
json105 reverse-index mutation, json106 invariant thousand rows, json107 BLOB
compatibility, json108 pretty-only invariants, json109 array-insert behavior,
or JSON aggregate/window coverage. It targets the upstream `json101.test`
table-valued row-column invariants and subtype handoff behavior.

Dependency closure: no new support component is needed; this reuses the
existing native PHP JSON table-valued function, JSONB, mutation, and validity
components.
