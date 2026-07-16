# real-upstream-corpus-json1-jsonb-dynamic-20260531T015818Z-0

Accepted base: `5355cb7ecea35e8be7c9099c3c6dbf4e5ec09d23`.

Added `SQLiteRealUpstreamJson106InvariantDynamicThousandTest.php`, a focused
real upstream JSON1/JSONB corpus batch derived from
`/home/claude/port-libs/.upstream-cache/libsqlite/test/json106.test`.

Covered upstream scenarios:

- `json106-1`: JSON text and JSON5 validity plus JSONB validity/canonical parity.
- `json106-2`: `json_tree` scalar atoms match path extraction for text and JSONB.
- `json106-5`: `json_remove` deletes object scalar leaves.
- `json106-6`: `json_insert` restores removed scalar leaves.
- `json106-7`: `json_patch` visible scalar leaves and JSONB patch parity.
- `json106-8`: `json_pretty` canonical round trip for text and JSONB inputs.

Focused verification:

`php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson106InvariantDynamicThousandTest.php`

Result: `1 test files, 68003 assertions, 0 failures`, with `1001` focused PASS
lines.

Expected selected movement: `1566206 -> 1567207 pass / 0 fail`. Mapped
coverage remains `1589 / 1589`.

Non-overlap: this slice does not repeat accepted JSON101 constructor/null,
JSON102 mutation/operator, JSON104 patch, JSON107 legacy BLOB, JSON109 array
insert, JSON table cursor/source/constraint, or JSON visible/hidden constraint
handoffs. It ports the real `json106.test` invariant family over 1,000 distinct
deterministic JSON documents.

Dependency closure: no new support component is needed; existing native JSON,
JSON5, JSONB, JSON tree, mutation, patch, pretty, and extraction primitives are
reused.
