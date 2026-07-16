# Custom At-Rules Parser Visitor Parity 2026-05-31T23:22Z

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream cases:
  - `node/test/visitor.test.mjs` `dark theme class`, where a returned style-rule selector AST serializes as `html[theme=dark]`.
  - `src/lib.rs::test_selectors` five attribute selector minifier helpers:
    `[foo="baz"]`, `[foo="foo bar"]`, `[foo="foo bar baz"]`, `[foo=""]`, and `.test:not([foo="bar"])`.

## Implementation

- `CssMinifier` now compacts normal attribute selector string values to upstream's shorter identifier form when it is shorter and valid, including nested selector functions.
- Namespace-qualified attribute selector values keep the accepted quoted behavior from `src/lib.rs::test_namespace`.
- The custom at-rule visitor dark-theme returned selector path now matches upstream output: `html[theme=dark]` rather than `html[theme="dark"]`.
- Added a WordPress block-theme smoke for a `Rule.media` visitor that clones dark-mode rules into `html[theme=dark]` selectors without Node/WASM.

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php lanes/lightningcss/tests/CssMinifierTest.php`
  - `2 test files, 1772 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 4826 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-custom-at-rule-attribute-selector-visitor.php --self-test`
  - `OK`
- PHP lint:
  - `php -l lanes/lightningcss/src/CssMinifier.php`
  - `php -l lanes/lightningcss/tests/CssMinifierTest.php`
  - `php -l lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  - `php -l lanes/lightningcss/examples/wordpress-custom-at-rule-attribute-selector-visitor.php`
- Full upstream Rust/Node/WASM runners: not run for this isolated micro-slice.

## Counting

- Full LightningCSS lane assertions: `4821 -> 4826`.
- Conservative mapped coverage: `2198 / 3532 -> 2203 / 3532`.
- Counted checks: the five focused `src/lib.rs::test_selectors` attribute selector minifier helpers.
- The `dark theme class` visitor path was already represented in mapped custom at-rule media visitor coverage; this slice corrects its exact returned selector serialization.

## Non-Overlap

This patch does not repeat accepted custom at-rule parser basics, style/media/supports visitors, variable/env/function visitor chains, CSS Modules attribute selector serialization, namespace-qualified attribute selector quoting, target prefixing, source maps, or bundle/import graph work. It is limited to normal selector attribute value compaction and returned visitor selector parity.

## Dependency Closure

No new support component is needed. This reuses the existing native `CustomAtRuleTransformer`, `CssMinifier`, selector scanner, and lane-local PHP tests/examples. No upstream binary, browser service, parser generator, or external CSS engine is introduced.

Root harness status: not run - isolated micro-slice.
