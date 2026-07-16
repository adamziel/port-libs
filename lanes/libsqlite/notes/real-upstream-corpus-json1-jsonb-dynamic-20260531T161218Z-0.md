# Real upstream JSON102 arrow subtype propagation

Slice: `real-upstream-corpus-json1-jsonb-dynamic-20260531T161218Z-0`
Base accepted HEAD: `8c7b034bb5fb3d061acb6b56e46103da8721d7a6`

## Source truth

- Hydrated upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test`
- Ported scenarios: `json102-1600`, `json102-1610`, `json102-1620`
- Upstream behavior: JSON `->` results carry JSON subtype, while `->>` results are ordinary SQL scalar/text values.

## Implementation

- `SQLiteSelectExpression::jsonOperatorValue()` now returns `SQLiteJsonSubtypeValue` for found `->` results.
- Existing top-level SQL projection behavior remains text-visible through `SQLiteSelectProjection::projectedValue()`.
- Existing direct JSON operator tests were updated to unwrap subtype when they are asserting user-visible JSON text rather than subtype identity.

## New focused corpus

- Added `SQLiteRealUpstreamJson102ArrowSubtypePropagationDynamic20260531Test.php`.
- Adds `1002` focused TestRunner PASS cases:
  - `1000` dynamic cases over text JSON and JSONB sources.
  - `1` hydrated upstream-source citation case.
  - `1` dependency-closure case.
- Adds `56009` focused behavior assertions.
- Covered object members, full paths, nested values, missing paths, and array indexes.
- Covered downstream subtype-sensitive value arguments through `json_quote()`, `json_array()`, and `json_object()`.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson102ArrowSubtypePropagationDynamic20260531Test.php`
  - `1 test files, 56009 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson101InfinityDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamJson101NullDepthDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamJson101NullPropagationDynamic20260531Test.php lanes/libsqlite/tests/SQLiteRealUpstreamJson102ArrowSubtypePropagationDynamic20260531Test.php lanes/libsqlite/tests/SQLiteRealUpstreamJson102OperatorDynamicMatrixTest.php lanes/libsqlite/tests/SQLiteRealUpstreamJson102OperatorDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamJson102OperatorPathStressTest.php lanes/libsqlite/tests/SQLiteRealUpstreamJson102OperatorRhsDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamJson107BlobOperatorDynamicMatrixTest.php lanes/libsqlite/tests/SQLiteRealUpstreamJson107LegacyBlobDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamJson1JsonbDynamicCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamJson1JsonbDynamicExpansion20260531Test.php lanes/libsqlite/tests/SQLiteRealUpstreamJson1JsonbDynamicLarge20260531Test.php lanes/libsqlite/tests/SQLiteRealUpstreamJson1JsonbDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamJson501502DynamicBulkTest.php lanes/libsqlite/tests/SQLiteRealUpstreamJson502EscapedLabelDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamJson502EscapedPathDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamJsonb01MalformedDynamicCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamJsonbJson109DynamicCorpusTest.php`
  - `19 test files, 265440 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `php -l` changed/new PHP files
  - all passed
- `git diff --check -- lanes/libsqlite`
  - passed

## Non-overlap

This slice does not repeat prior JSON102 value/RHS lookup coverage (`json102-1800` through `json102-1831`), JSON table cursor/source wiring, JSON hidden/visible constraint pushdown, or JSON aggregate/window work. It owns the narrower upstream subtype propagation behavior for `json102-1600`, `json102-1610`, and `json102-1620`.

## Dependency closure

No new support component is required. The patch reuses existing JSON subtype, JSON constructor, JSON quote, JSONB, and SELECT expression/projection infrastructure.

Root harness: not run - isolated micro-slice.
