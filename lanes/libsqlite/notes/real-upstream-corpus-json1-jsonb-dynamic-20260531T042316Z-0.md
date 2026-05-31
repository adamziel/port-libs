# real-upstream-corpus-json1-jsonb-dynamic-20260531T042316Z-0

- Base accepted HEAD: `5823f556f77d50bd49ce909acb22097fc44da229`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test`.
- Ported upstream sections: `json101-10.1..10.95`, `json101-10.86.0..10.86.6`, and `json101-11.0..11.3`.
- Behavior cluster: strict JSON string backslash escape validity and 1000/1001 nesting-depth `json_valid()` boundaries, now also exercised through parser-level `SQLiteSelectSql` constant SELECT dispatch.
- Focused test movement: existing file baseline was 17,912 assertions by static count; focused run now reports `1 test files, 18332 assertions, 0 failures`, for +420 focused behavior assertions.
- Non-overlap: does not touch JSON table hidden/visible constraints, JSON table SELECT sources/cursors, JSONB malformed planner diagnostics, JSON aggregate/window/object behavior, JSON patch/pretty invariant batches, or generated path-cost families.
- Dependency closure: no new support component required; reused existing `SQLiteJsonValidity` and `SQLiteSelectSql` expression dispatch.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson101EscapeValidityDynamicTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 18332 assertions, 0 failures
```
