# Media Query Bare Not Operand Validation

Slice: `lightningcss-media-query-range-layer-parity-20260531T234235Z`

Source truth:
- Pinned upstream LightningCSS commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Upstream parser behavior inspected in `.upstream-cache/lightningcss/src/media_query.rs`: `parse_query_condition()` treats bare `not <media-in-parens>` as a complete negation and boolean condition operations parse their operands through parenthesized condition/function paths. That means `not (color) and (hover)`, `(hover) and not (color)`, and `screen and not (color) and (hover)` are rejected unless the negated condition is wrapped as `(not (...))`.
- Upstream regression context is the already represented `src/lib.rs::test_media` media condition serialization/error cluster, including layered media parsing.

Native PHP delta:
- `MediaQueryParser::minifyQuery()` now validates top-level boolean condition operands after logical/function validation and before condition normalization.
- Explicit media-type prefixes are preserved by validating only the condition tail, so `screen and not (color)` remains valid while `screen and not (color) and (hover)` is rejected.
- Layer-wrapped minification uses the same parser path, so invalid `@layer { @media ... }` bare-not chains now fail before CSS is emitted.

Red-first probes before the implementation:
- `MediaQueryParser::minifyList('not (color) and (hover)')` incorrectly returned a minified query instead of throwing.
- `MediaQueryParser::minifyList('screen and not (color) and (hover)')` incorrectly serialized a bare negation operand chain.
- `CssMinifier` accepted `@layer blocks { @media not (width < 240px) and (hover) { ... } }`.

Verification:
- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php` -> `1 test files, 348 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 4930 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-media-layer-minifier.php --self-test` -> exit 0.

Status and mapping:
- `lane-status.json` `phpPass` moves from `4914` to `4930`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator remains `2207 / 3532`; this slice deepens the already mapped media-query range/layer validation cluster rather than claiming a new denominator row.

Non-overlap:
- This does not repeat accepted escaped media identifiers, media conjunction operator spacing, comment-token media layers, x-resolution serialization, vendor DPR ranges, or target-prefixing media feature work. It is only the upstream bare `not (...)` operand boundary in boolean condition chains.

Dependency closure:
- No new support component is needed. The slice reuses the existing native PHP media parser, minifier, and layer parser paths.
