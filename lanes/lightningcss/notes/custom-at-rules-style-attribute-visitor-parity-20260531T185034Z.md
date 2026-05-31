# Custom At-Rule Style Attribute Visitor Parity

- Lane: `lightningcss`
- Micro-slice: `lightningcss-custom-at-rules-parser-visitor-parity-20260531T185034Z`
- Base accepted HEAD: `0c0eec061390da3a2185ec8623476b5865dd4a49`
- Upstream source truth: `parcel-bundler/lightningcss` pinned commit `22bdda3d190f1cd321d98026225cfc964af64ad9`

## Upstream Evidence

- `node/test/visitor.test.mjs` has two top-level `transformStyleAttribute` parity cases:
  - `works with style attributes`: a `Length` visitor rewrites `height: calc(100vh - 64px)` to `height:calc(100vh - 4rem)`.
  - `visitor function works with style attributes`: a visitor factory records a file dependency through `addDependency` while transforming a style attribute.
- `src/stylesheet.rs` routes `StyleAttribute::parse` through `DeclarationBlock::parse`, runs declaration minification/visitor behavior in a style-attribute declaration context, and serializes the declaration list without a surrounding selector.

## Red-First Probe

Before the implementation, this accepted base had no dependency-returning style-attribute transform API:

```sh
php -r 'require "tools/bootstrap.php"; $t = new PortLibs\LightningCSS\CustomAtRuleTransformer(); var_export(method_exists($t, "transformStyleAttributeWithDependencies")); echo PHP_EOL;'
```

Result: `false`.

## Implementation

- Added `CustomAtRuleTransformer::transformStyleAttribute()` for inline declaration-list visitor transforms.
- Added `CustomAtRuleTransformer::transformStyleAttributeWithDependencies()` for upstream-style visitor factory dependency collection from style attributes.
- Reused the existing native `DeclarationBlock` parser and custom visitor pipeline, including declaration, value, `Length`, function, URL, variable, dashed-ident, and visitor-factory dependency callbacks.
- Added a WordPress inline-style smoke that converts block inline spacing from px to rem and records a theme spacing dependency without Node/WASM.

## Verification

- `php -l lanes/lightningcss/src/CustomAtRuleTransformer.php`
  - `No syntax errors detected in lanes/lightningcss/src/CustomAtRuleTransformer.php`
- `php -l lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  - `No syntax errors detected in lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
- `php -l lanes/lightningcss/examples/wordpress-custom-at-rule-style-attribute-visitor.php`
  - `No syntax errors detected in lanes/lightningcss/examples/wordpress-custom-at-rule-style-attribute-visitor.php`
- `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  - `1 test files, 87 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 3146 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-custom-at-rule-style-attribute-visitor.php --self-test`
  - `OK`

- `git diff --check -- lanes/lightningcss`
  - passed with no output

## Status Delta

- Conservative mapped coverage: `1696 -> 1698 / 3532`.
- Focused custom at-rule test file: `82 -> 87` assertions.
- Full LightningCSS PHP lane: `3141 -> 3146` assertions.
- Full upstream Rust/Node/WASM runners: not run for this isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP declaration parser and visitor pipeline; no Node, Rust, WASM, parser generator, or new support-library activation gate is required.

## Non-Overlap

This slice avoids the stale custom-media rework note and does not repeat accepted custom at-rule parser basics, style-rule visitors, `SupportsRule` return handling, `DashedIdent`/`CustomIdent`, URL visitors, media visitors, source-map, CSS Modules, bundler/import graph, media-query, target-prefixing, CSSOM, or property-value clusters.
