# real-upstream-corpus-json1-jsonb-dynamic-20260531T042757Z-0

Added `SQLiteRealUpstreamJson104QuotedKeyUpdateDynamicTest.php`, a focused PHP
port of hydrated upstream `/home/claude/port-libs/.upstream-cache/libsqlite/test/json104.test`
quoted-key update/extract behavior.

Covered upstream sections:

- `json104-401`: table-style object source row with keys `a` and `b`.
- `json104-402`: `json_insert(x, '$.c', 3)` appends an unquoted object key.
- `json104-403`: `json_extract(x, '$.b')` and `json_extract(x, '$."b"')`
  address the same object member.
- `json104-404`: `json_set(x, '$."b"', 555)` updates the quoted member and is
  visible through both quoted and unquoted paths.
- `json104-405`: `json_set(x, '$."d"', 4)` adds a quoted object member.

The PHP port expands those exact behaviors across 1,005 dynamic objects and
checks text JSON plus JSONB parity for insert, quoted-path extraction, quoted
`json_set`, strict JSONB validity, and nested-key non-interference.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson104QuotedKeyUpdateDynamicTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 24123 assertions, 0 failures
```

Expected PASS-line delta if accepted: `+1006` focused TestRunner PASS cases.
Mapped denominator coverage remains `1589 / 1589`.

Non-overlap: this does not repeat JSON104 RFC7396 merge-patch rows, JSON table
source/cursor/hidden/visible constraint work, JSON109 array-insert behavior,
JSON106 invariants, JSON501/502 JSON5/path behavior, or JSONB removal
inspection. It targets the upstream `json104.test` quoted object-key
mutation/extraction row-update family.

Dependency closure: no new support component is needed; this reuses existing
native PHP JSON mutation, extraction, JSONB, canonicalization, and validity
components.
