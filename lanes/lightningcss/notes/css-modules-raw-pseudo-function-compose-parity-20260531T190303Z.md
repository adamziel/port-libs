# LightningCSS CSS Modules Raw Pseudo Function Compose Parity 2026-05-31T19:03Z

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260531T190303Z`

## Source Truth

- Upstream commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream files:
  - `src/selector.rs`, where unsupported pseudo functions parse as `CustomFunction` and serialize their raw `TokenList` arguments.
  - `src/selector.rs::is_pure_css_modules_selector`, which only descends into known selector-valued functions such as `:is()`, `:where()`, `:has()`, `:not()`, `NthOf`, `:host()`, `::slotted()`, and CSS Modules `:local()`.
  - Existing `src/lib.rs::test_css_modules` local/global/composes cluster for export metadata behavior.

## Implementation

- `CssModulesTransformer` now skips CSS Modules scoping and pseudo-class replacement inside raw custom pseudo-function arguments such as `:--theme-state(.legacy, :hover, #anchor)`.
- Known selector-valued functions still allow local/global rewriting, so `:is(.featured, :global(.wp-block-card))` and `:nth-child(... of .card, :global(.wp-block))` keep upstream CSS Modules scoping.
- Pure-mode validation now ignores local-looking tokens inside raw custom pseudo-function token lists unless another selector-valued local reference exists.
- The WordPress CSS Modules example now covers a block-state custom pseudo function while preserving local `composes` metadata.

## Verification

- Red-first spot check before implementation: `.card:--theme-state(.legacy)` incorrectly emitted `.EgL3uq_legacy`, and `:--theme-state(.legacy)` passed `pure` mode.
- `php -l lanes/lightningcss/src/CssModulesTransformer.php` => no syntax errors.
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php` => no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-css-modules-transformer.php` => no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` => `1 test files, 189 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 3334 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-transformer.php --self-test` => `OK`.

## Coverage Delta

- Focused CSS Modules assertions moved from `183` to `189`.
- Full LightningCSS PHP assertions moved from `3328` to `3334`.
- Conservative mapped coverage remains `1800 / 3532`; this deepens the already represented CSS Modules selector/composes cluster rather than adding a new static denominator row.

## Dependency Closure

No new support component is needed. This reuses the native PHP selector scanner, CSS Modules export model, `NestingTransformer`, and `CssMinifier`; no Node, Rust, WASM, parser generator, browser service, or external package is introduced.

## Non-Overlap

This slice does not repeat accepted CSS Modules local/global basics, functional `:local()` composes rejection, pure selector boundaries, pseudo-class replacement, `@scope`, container-name, grid, dashed-ident, content-hash, view-transition, or bundler dependency graph work. The stale 2026-05-25 CustomMedia import-tail rework note was inspected; current lane tests and notes already contain that scanner/import-tail behavior, and this patch does not touch `CustomMediaTransformer`.
