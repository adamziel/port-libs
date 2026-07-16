# Media Query Conjunction And Layer Parity

Slice: `lightningcss-media-query-range-layer-parity-20260531T223229Z`

Source truth:

- Upstream pinned commit: `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Focused upstream behavior: `src/media_query.rs::test_and`, specifically `MediaQuery::and` conjunction for range conditions, `or` grouping, `all`, `not all`, `only screen`, incompatible media types, and incompatible negated media types.
- Import-graph coupling: `src/bundler.rs` combines parent and child import media queries while preserving cascade layer wrapping.

Native delta:

- Added `MediaQueryParser::andQuery()` so upstream-style media-query conjunction is available outside bundler internals.
- Refactored `CssBundler` import media conjunction to delegate to `MediaQueryParser::andQuery()`, preserving the existing bundled import diagnostic for unsupported boolean logic.
- Added focused `MediaQueryParserTest.php` assertions for the upstream `test_and` matrix.
- Extended `wordpress-media-range-layer-import-graph.php` with a layered import graph where parent `all` and child `only screen` combine to `only screen`.

Verification:

- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php`
  - Result: `1 test files, 324 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php`
  - Result: `1 test files, 358 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-media-range-layer-import-graph.php`
  - Result: exits 0 and prints the original range/layer bundle plus the new `only screen` layered bundle.
- `php tools/run-tests.php lanes/lightningcss/tests`
  - Result: `13 test files, 4684 assertions, 0 failures`.
- `php -l lanes/lightningcss/src/MediaQueryParser.php`
  - Result: no syntax errors.
- `php -l lanes/lightningcss/src/CssBundler.php`
  - Result: no syntax errors.
- `php -l lanes/lightningcss/tests/MediaQueryParserTest.php`
  - Result: no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-media-range-layer-import-graph.php`
  - Result: no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/lightningcss/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`
  - Result: `json ok`.
- `git diff --check -- lanes/lightningcss`
  - Result: clean.

Status delta:

- Native PHP pass evidence moves `4663 -> 4684` assertions.
- Conservative mapped denominator coverage moves `2171 / 3532 -> 2172 / 3532`.

Non-overlap:

- This slice does not repeat accepted media range fallback serialization, invalid media range validation, negated interval parsing, resolution prefixing, escaped media identifiers, redundant nested negation, custom-media import-tail scanning, CSS Modules, SourceMap, CSSOM, target-prefixing, property-value, or custom at-rule visitor clusters.
- The existing stale rework note for `port-lightningcss-current-rebase-20260525T053931Z-02383337` names older custom-media import-tail conflict context. Current accepted status already contains later custom-media/import-tail behavior, so this slice stays on the assigned upstream media-query conjunction cluster.

Dependency closure:

- No new support component is needed. The implementation reuses the native PHP media parser, bundler, and minifier surfaces already in the LightningCSS lane.

Next task:

- Continue current-base media-query parity with remaining unmapped parser/boolean/recovery edges only when backed by pinned upstream behavior and fresh focused PHP assertions.
