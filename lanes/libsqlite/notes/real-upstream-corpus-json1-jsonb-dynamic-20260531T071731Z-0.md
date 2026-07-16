# real-upstream-corpus-json1-jsonb-dynamic-20260531T071731Z-0

## Scope

- Added `SQLiteRealUpstreamJson102Json104DynamicMutationPatchTest.php`.
- Source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test`
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/json104.test`
- Upstream sections:
  - `json102-320..400`: `json_insert`, `jsonb_insert`, `json_replace`, `jsonb_replace`, `json_set`, `jsonb_set`, scalar string values, JSON subtype values, and JSONB constructor values.
  - `json104-300..320`: RFC-7396 `json_patch` object merge, null delete, array replacement, scalar replacement, duplicate-key last-value behavior class.

## Evidence

- Focused command:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson102Json104DynamicMutationPatchTest.php`
- Result:
  - `1 test files, 5768 assertions, 0 failures`
  - `1442` TestRunner PASS lines.
- Expected dashboard movement if accepted:
  - `phpPass` `2628093 -> 2629535`
  - mapped denominator unchanged at `1589 / 1589`.

## Non-Overlap

This slice does not repeat accepted JSON table cursor/source/hidden/visible constraint work, JSON101 quote/nested edit behavior, JSON102 multi-path extraction, JSON104 quoted-key update, JSON106/108 invariants, JSONB remove-only paths, or JSON aggregate/window work. It focuses on dynamic mutation and merge-patch behavior from real hydrated upstream files.

## Dependency Closure

No new support component is needed. The batch reuses existing native PHP JSON primitives: `SQLiteJsonMutation`, `SQLiteJsonPatch`, `SQLiteJsonConstructor`, `SQLiteJsonB`, `SQLiteJsonCanonical`, and `SQLiteSelectExpression`.
