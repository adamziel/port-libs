# LightningCSS Bundle Import Graph Parity - Quoted url() Source Tokens

## Upstream Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- LightningCSS imports call cssparser's URL/string reader for `@import` sources (`src/parser.rs` uses `expect_url_or_string()`).
- cssparser `expect_url_or_string()` accepts `Token::Function("url")` only by parsing a nested quoted string token. Its whitespace skipper consumes comments, its own tests accept `url( 'abc' \t)`, and its nested parser rejects `url('abc' more stuff)` / `url(abc more stuff)`.

## Native Delta

- `CssBundler` now parses quoted `url(...)` import sources as a single CSS string token, permits whitespace/comment trivia around the string, and rejects any extra token before resolver/read graph traversal.
- Unquoted `url(...)` imports continue to use the existing unquoted URL validation and CSS escape decoding path.
- The WordPress bundle smoke now covers a generated block stylesheet import with a quoted URL plus trailing build comment, and a malformed quoted URL import that is rejected before reading dependencies.

## Verification

- `php -l lanes/lightningcss/src/CssBundler.php` - pass
- `php -l lanes/lightningcss/tests/CssBundlerTest.php` - pass
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` - pass
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` - `1 test files, 327 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` - pass
- `php tools/run-tests.php lanes/lightningcss/tests` - `13 test files, 4201 assertions, 0 failures`
- `git diff --check -- lanes/lightningcss` - pass

## Status Delta

- Full LightningCSS PHP lane moved from `4181` to `4201` assertions.
- Conservative mapped coverage remains `2078 / 3532`; this deepens the already represented bundle/import graph parser cluster instead of adding a new denominator row.

## Dependency Closure

No new support component is needed. The slice reuses the lane-local bounded CSS string-token decoder, comment/whitespace scanner, resolver/read callbacks, and import graph bundler.

## Non-Overlap

This does not repeat accepted import layer-name validation, supports-condition grouping, SourceProvider reads, escaped URL resolution, external import ordering, or CSS Modules bundle graph slices. It only tightens `@import url("...")` source token boundaries before graph resolution.
