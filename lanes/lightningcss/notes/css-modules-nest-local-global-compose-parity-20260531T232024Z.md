# LightningCSS CSS Modules @nest Local/Global Compose Parity 2026-05-31T23:20Z

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260531T232024Z`

## Source Truth

- Upstream pinned commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream areas:
  - `src/selector.rs`, where CSS Modules `:local()` and `:global()` parse into selector components before printing.
  - `src/css_modules.rs::CssModule::handle_composes`, preserving local compose metadata only on simple local class selectors.
  - `src/lib.rs::test_css_modules`, especially the nested-rule + `composes` minify path.
- Native pinned NAPI oracle spot-check confirmed `@nest :global(.theme) &` lowers to `.theme .EgL3uq_card`, while local/global selectors inside `@nest :local(...)` and `@nest &:where(:global(...), .local)` are scoped before nesting is lowered.

## Implementation

- `CssModulesTransformer::rewriteAtRulePrelude()` now recognizes `@nest` preludes.
- `@nest` selector lists are run through the existing CSS Modules selector rewriter before `NestingTransformer` lowers them.
- Local/global mode, selector-valued pseudo functions, export collection, and `composes` metadata reuse the existing native PHP paths.
- The new WordPress example covers block CSS that nests a public `.wp-block-group` selector around a local module class while preserving the module `reset` compose class list.

## Evidence

- Red-first PHP spot-check before the fix emitted `:global(.theme) .EgL3uq_card` for `@nest :global(.theme) &`, while the pinned upstream NAPI oracle emitted `.theme .EgL3uq_card`.
- `php -l lanes/lightningcss/src/CssModulesTransformer.php && php -l lanes/lightningcss/tests/CssModulesTransformerTest.php && php -l lanes/lightningcss/examples/wordpress-css-modules-nest-local-global.php` => no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` => `1 test files, 324 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 4825 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-nest-local-global.php --self-test` => `OK`.
- `git diff --check -- lanes/lightningcss` => passed.
- Root harness status: not run - isolated micro-slice.

## Status Delta

- Focused CSS Modules evidence moves from `320` to `324` assertions.
- Full LightningCSS PHP evidence moves from `4821` to `4825 pass / 0 fail`.
- Conservative mapped coverage remains `2198 / 3532`; this deepens the already represented CSS Modules local/global/composes and nesting cluster rather than claiming a new denominator row.

## Dependency Closure

No new support component is needed. This reuses the lane-local CSS Modules selector scanner, `NestingTransformer`, export metadata model, and WordPress example harness. No Node, Rust, WASM, parser generator, browser service, or external CSS engine is required at runtime.

## Non-Overlap

This does not repeat accepted state/highlight custom-ident scoping, escaped `:local` / `:global` pseudo names, raw custom pseudo-function preservation, host-context raw behavior, pseudo-element boundaries, functional `:local()` composes rejection, transitive class-list flattening, invalid pattern diagnostics, unused-symbol pruning, bundle dependency diagnostics, CSSOM, media-query, source-map, property-value, custom-at-rule, or target-prefixing clusters. It is limited to `@nest` prelude rewriting before CSS Modules local/global/composes output.
