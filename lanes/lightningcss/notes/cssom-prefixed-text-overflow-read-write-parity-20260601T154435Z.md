# CSSOM prefixed text-overflow read/write parity

Slice: `lightningcss-cssom-declaration-read-write-parity-20260601T154435Z`

Source truth:

- Upstream `tests/test_cssom.rs` exercises CSSOM declaration `get`, `set`, and `remove` behavior.
- Upstream `src/properties/mod.rs` registers `text-overflow` as `TextOverflow` with Opera vendor prefix support.
- Upstream `src/properties/overflow.rs` defines the `TextOverflow` enum keywords as `clip` and `ellipsis`.

Behavior delta:

- Before this slice, `text-overflow: Ellipsis` canonicalized to `ellipsis`, but `-o-text-overflow: Ellipsis` stayed mixed-case because the prefixed property did not enter the native enum normalization table.
- `DeclarationBlock` now routes `-o-text-overflow` through the same `clip` / `ellipsis` CSSOM normalization path as unprefixed `text-overflow`.
- Focused tests cover parse, getProperty with a mixed-case property name, setProperty priority movement, removeProperty, and custom-property case preservation.
- The WordPress layout/effects CSSOM smoke now includes a legacy Opera text-overflow declaration and verifies read, write, and removal output.

Status delta:

- Adds one focused PHP TestRunner case to `DeclarationBlockTest.php`.
- Focused `DeclarationBlockTest.php` assertions move `1287 -> 1295`.
- Full LightningCSS lane assertions move `8500 -> 8508`; lane `phpPass` is updated to `8508`.
- Conservative mapped coverage remains `2398 / 3532`; this deepens the already mapped CSSOM DeclarationBlock cluster rather than claiming a new upstream inventory row.

Dependency closure:

- No new support component is required. The slice reuses the native `DeclarationBlock` parser, enum value canonicalizer, priority bucket serialization, and existing example self-test harness.

Non-overlap:

- This patch does not touch source maps, bundle/import graph, target-prefixing, CSS Modules, custom at-rules, media queries, selectors, or parser recovery. It is limited to the upstream-backed CSSOM declaration read/write behavior for `-o-text-overflow`.

Verification:

- Baseline before edit: `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` -> `1 test files, 1287 assertions, 0 failures`.
- `php -l lanes/lightningcss/src/DeclarationBlock.php` -> `No syntax errors detected in lanes/lightningcss/src/DeclarationBlock.php`.
- `php -l lanes/lightningcss/tests/DeclarationBlockTest.php` -> `No syntax errors detected in lanes/lightningcss/tests/DeclarationBlockTest.php`.
- `php -l lanes/lightningcss/examples/wordpress-layout-effects-cssom.php` -> `No syntax errors detected in lanes/lightningcss/examples/wordpress-layout-effects-cssom.php`.
- `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` -> `1 test files, 1295 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 8508 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-layout-effects-cssom.php --self-test` -> `OK`.
- `git diff --check -- lanes/lightningcss` -> no output.
