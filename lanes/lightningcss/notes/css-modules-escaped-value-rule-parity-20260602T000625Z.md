# LightningCSS CSS Modules Escaped Value Rule Parity

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260602T000625Z`

Base accepted HEAD: `ce8cd6d1ec6823f7aed57d156dd36b048ab6f47a`

## Source Truth

- Upstream pinned commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream areas:
  - `src/lib.rs::test_css_modules_value_rule`, which rejects deprecated CSS Modules `@value`.
  - CSS tokenization for at-keywords, confirmed with the pinned native Node addon: `@v\61 lue compact: ...` raises `The @value rule is deprecated`.

## Behavior

- `CssModulesTransformer` now decodes the at-keyword before checking for deprecated CSS Modules `@value`.
- Escaped and case-varied forms such as `@v\61 lue` and `@V\61 LUE` reject before local/global `composes` metadata can be returned.
- The WordPress smoke models block CSS with local `composes`, global `composes`, and an escaped deprecated `@value` after the rule.

## Evidence

- Red-first PHP spot-check before the patch emitted `@v\61 lue compact:(max-width:1px);` and returned partial `card` / `base` exports for a stylesheet with `composes`.
- Pinned native Node addon spot-check raised `The @value rule is deprecated` for plain and escaped `@value`.
- `php -l lanes/lightningcss/src/CssModulesTransformer.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-css-modules-escaped-value-rule.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` -> 1 test files, 723 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-css-modules-escaped-value-rule.php --self-test` -> OK.
- `php tools/run-tests.php lanes/lightningcss/tests` -> 14 test files, 9267 assertions, 0 failures.
- `git diff --check -- lanes/lightningcss` -> passed.

Focused assertion delta: `CssModulesTransformerTest.php` adds 5 focused assertions, moving `phpPass` from 9262 to 9267. Conservative mapped coverage remains `2439 / 3532`; this deepens the represented CSS Modules value-rule/error cluster rather than claiming a new denominator row.

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the lane-local CSS identifier decoder, CSS Modules transformer, test runner, and WordPress example harness. No Node, Rust, WASM, browser service, parser generator, or external CSS engine is required at runtime.

## Non-Overlap

This does not repeat the accepted SourceMap VLQ/offset, CSSOM SVG paint/image-rendering, CSS Modules terminal pseudo/composes, `:has(:scope)` local/global, export class-list flattening, selector-list validation, escaped local/global pseudo, bundler import graph, media-query, target-prefixing, custom at-rule, or property/value minifier clusters. The only behavior delta is escaped at-keyword recognition for the deprecated CSS Modules `@value` error before local/global `composes` output.

## Next

Continue with current-base production-bearing LightningCSS parity, especially remaining CSS Modules parser diagnostics or the dependency-gated Rust/Node/WASM runner closure.
