# real-upstream-corpus-json1-jsonb-dynamic-20260531T234113Z-0

Lane: libsqlite

Base accepted HEAD: 6dcdbdf63680f15710c0b63f093637566ee78a22

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test`
- Sections `json101-14.100` through `json101-14.170`, covering scalar-root `json_each()` and `json_tree()` fullkey output when the input JSON is not an array or object.

Patch summary:

- Added `SQLiteRealUpstreamJson101ScalarRootDynamic20260531Test.php` with 300 generated scalar-root cases.
- Each case checks `json_each` and `json_tree` over text JSON, JSONB BLOB, and `SQLiteJsonSubtypeValue` inputs.
- Assertions cover root row count, `key`, `fullkey`, `path`, hidden `json`/`root`, `type`, `value`, `atom`, `id`, `parent`, `json_type`, `json_extract`, canonicalization, and JSON/JSONB validity.
- Widened `SQLiteJsonExtract` public input types to accept `SQLiteJsonSubtypeValue`, matching the already subtype-aware path locator and the existing `json_type` / table-valued JSON surfaces.

Red-first evidence:

- Initial focused run failed with JSONB validity assertions using strict text validation; the test was corrected to use `SQLiteJsonValidity::FLAG_STRICT_JSONB` for BLOB inputs.
- Second focused run failed because `SQLiteJsonExtract::extract()` rejected `SQLiteJsonSubtypeValue` despite downstream path location supporting it. The source fix widened extractor signatures and the focused file then passed.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteJsonExtract.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson101ScalarRootDynamic20260531Test.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson101ScalarRootDynamic20260531Test.php` passed: `1 test files, 27012 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson101ScalarRootDynamic20260531Test.php lanes/libsqlite/tests/SQLiteRealUpstreamJson102ArrowSubtypePropagationDynamic20260531Test.php lanes/libsqlite/tests/SQLiteRealUpstreamJson102MultiPathDynamic20260531Test.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed: `4 test files, 105027 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` passed.

Non-overlap:

- Existing accepted coverage had direct static scalar-root checks for `json101-14.100` through `json101-14.170`.
- This slice expands those upstream cases dynamically across generated scalar roots and JSONB/subtype input forms, and fixes the extractor subtype boundary exposed by that expansion.
- It does not touch JSON table visible/hidden constraints, JSON table SELECT source wiring, JSON table cursor behavior, JSON aggregate/window behavior, JSONB ordered remove, or JSON mutation tail coverage.

Dependency closure:

- No new support component is needed. The patch reuses existing native PHP JSONB, JSON subtype, JSON table-valued, JSON inspection, canonicalization, validity, and extraction components.
