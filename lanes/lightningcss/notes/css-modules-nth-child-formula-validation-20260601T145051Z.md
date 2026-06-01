# CSS Modules nth-child Formula Validation

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260601T145051Z`

## Upstream Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Behavior spot-checked through the pinned NAPI artifact at `/home/claude/port-libs/.upstream-cache/lightningcss/lightningcss.linux-x64-gnu.node`.
- Upstream rejects invalid `:nth-child()` / `:nth-last-child()` An+B formulas before CSS Modules export composition is accepted:
  - `.foo` -> `Unexpected token Delim('.')`
  - `#foo` -> `Unexpected token IDHash("foo")`
  - `[foo]` -> `Unexpected token SquareBracketBlock`
  - `calc(1)` -> `Unexpected token Function("calc")`
  - `2 n` -> `Unexpected token Ident("n")`
  - `+ 2` -> `Unexpected token WhiteSpace(" ")`
- Valid formulas such as `+n of .item` and `-n + 1 of .item` continue to serialize and scope local selectors.

## Implementation

- `CssModulesTransformer::minifyNthChildFormula()` now validates An+B syntax before whitespace folding.
- Invalid formula token diagnostics are raised before CSS Modules `composes` metadata can be returned.
- Existing `:local()` / `:global()` mode-pseudo rejection inside nth-child formulas is preserved.
- The WordPress-relevant nth-child example now includes invalid-formula diagnostics for composed block classes.

## Verification

- Red-first baseline before the implementation: `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` -> `1 test files, 631 assertions, 0 failures`.
- Focused after implementation: `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` -> `1 test files, 641 assertions, 0 failures`.
- Changed example smoke: `php lanes/lightningcss/examples/wordpress-css-modules-nth-child-formula.php --self-test` -> `OK`.
- Full lane: `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 8315 assertions, 0 failures`.
- PHP lint:
  - `php -l lanes/lightningcss/src/CssModulesTransformer.php`
  - `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php`
  - `php -l lanes/lightningcss/examples/wordpress-css-modules-nth-child-formula.php`
- `git diff --check -- lanes/lightningcss` -> passed with no output.

## Coverage And Dependency Closure

- `phpPass` moves `8305 -> 8315`.
- Conservative mapped coverage remains `2393 / 3532`; this deepens an already represented CSS Modules local/global/composes selector parser cluster.
- No new support component is needed. The slice reuses the native PHP CSS Modules transformer and selector scanner.
- Root harness not run: isolated micro-slice.

## Non-overlap

This does not repeat accepted CSS Modules local/global mode-pseudo rejection, escaped selector/comment boundaries, pseudo-element handling, bundle `composes from` import graph behavior, CSSOM declaration read/write, SourceMap, media-query, target-prefixing, or custom at-rule clusters.
