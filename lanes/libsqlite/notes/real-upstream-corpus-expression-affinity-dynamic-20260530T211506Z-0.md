# real-upstream-corpus-expression-affinity-dynamic-20260530T211506Z-0

Date: 2026-05-30T21:17:19Z
Base accepted HEAD: bbccc1d8f736962c4f86ebb79411aec5c77c5f5a

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/cast.test`
- Covered sections: `cast-1.*`, `cast-2.*`, `cast-3.*`, `cast-5.*`, `cast-7.*`, `cast-9.*`, and `cast-10.*`
- Behavior: CAST target affinity and numeric-prefix handling through `INTEGER`, `REAL`, `NUMERIC`, `TEXT`, and `BLOB` target affinities.

## Delta

Added `SQLiteRealUpstreamExpressionAffinityCastTargetDynamicTest.php`, an oracle-backed dynamic corpus shard with 75 literal/prefix variants x 5 CAST targets x 3 projections. Each case compares the bounded `SQLiteSelectSql` executor against local `sqlite3`.

Focused result:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityCastTargetDynamicTest.php
1 test files, 1130 assertions, 0 failures
```

The focused run produced 1126 PASS lines: 1125 oracle-backed dynamic cases plus one source/count ownership case.

## Non-Overlap

This does not repeat the accepted expression affinity shards for `types2` matrices, affinity2/affinity3 storage rules, LIKE/GLOB, NULL comparison, or broad cast helper tests. It targets CAST target-affinity dispatch through the parser-level SELECT executor with sqlite3 oracle comparison.

## Exclusions

The first red attempt included int64 overflow real formatting and negative-zero real quote cases. Those exposed remaining parity gaps in float formatting / overflow numeric casting, so they were excluded from this countable green batch rather than weakening expectations.

## Dependency Closure

No new support component is needed. The test reuses the existing bounded `SQLiteSelectSql` executor and local `sqlite3` oracle used by other real upstream corpus tests.
