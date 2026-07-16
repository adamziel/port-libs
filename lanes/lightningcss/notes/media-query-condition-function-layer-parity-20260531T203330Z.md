# LightningCSS Media Query Condition Function Layer Parity

Micro-slice: `lightningcss-media-query-range-layer-parity-20260531T203330Z`

Accepted base: `29362e0d6ada0a9ddb4cefdc699cee6add41d488`

Upstream source truth:

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted pristine reads:
  - `/home/claude/port-libs/.upstream-cache/lightningcss/src/media_query.rs`
  - `parse_query_condition` and `parse_parens_or_function`
- Upstream media conditions parse boolean operands as parenthesized media conditions. Unsupported arbitrary condition functions are not accepted as operands after `not`, `and`, or `or` in media queries.

Native PHP delta:

- `MediaQueryParser` now validates top-level `and`/`or` operands inside parenthesized media conditions before minifying nested parentheses.
- `not` inside a media condition now requires a single parenthesized condition operand.
- The guard rejects nested unsupported function operands such as `(not unknown(foo))`, `((color) or unknown(foo))`, and `((color) and not unknown(foo))`, including inside `@layer` block CSS.
- `wordpress-media-layer-minifier.php` now self-tests those layered invalid media conditions without Node/WASM.

Verification:

- `php -l lanes/lightningcss/src/MediaQueryParser.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/MediaQueryParserTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-media-layer-minifier.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php` -> `1 test files, 278 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php lanes/lightningcss/tests/TransitionPrefixerTest.php lanes/lightningcss/tests/CssBundlerTest.php lanes/lightningcss/tests/CssMinifierTest.php` -> `4 test files, 2637 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-media-layer-minifier.php --self-test` -> exits 0 and emits the expected minified layered media CSS.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 4158 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` -> pass.

Status delta:

- `phpPass`: `4150 -> 4158`.
- `phpFail`: `0`.
- Conservative mapped coverage remains `2078 / 3532`; this deepens the already mapped `src/media_query.rs` media-query validation cluster rather than adding a new manifest row.

Non-overlap:

- Does not repeat accepted media range fallback, typed range, unknown feature range, equality range, dangling logic, double negation, parenthesized range negation, resolution prefix, bundler media conjunction, custom-media import-tail scanner, CSSOM, SourceMap, CSS Modules, target-prefixing, or property-value slices.
- This slice is only the nested unsupported condition-function operand rejection cluster for media query range/layer parity.

Dependency closure:

- No new support component is needed. The slice reuses the native `MediaQueryParser`, `CssMinifier`, adjacent bundled/minifier/prefixer tests, and the existing WordPress media-layer example path.
- No Node, Rust, WASM, browser engine, external CSS parser, or new shared support library is required.

Root harness status: not run - isolated micro-slice.

Next task:

- Continue non-overlapping LightningCSS parity around media parser recovery/serialization, bundle/import graph behavior, CSSOM read/write gaps, CSS Modules, target-prefixing boundaries, property/value parity, and source-map behavior.
