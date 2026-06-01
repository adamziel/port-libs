# CSS Modules View-Transition Local/Global Compose Parity

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260601T004449Z`

## Source Truth

- Upstream pinned commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream areas:
  - `src/lib.rs::test_css_modules` view-transition selector-function helper cases.
  - `src/css_modules.rs::CssModule::handle_composes`, preserving local `composes` metadata on normal simple local classes.
- Local pinned NAPI oracle spot-checks reject CSS Modules mode pseudos inside view-transition selector-function arguments:
  - `:root::view-transition-group(:global(public-card))` => invalid state.
  - `:root::view-transition-new(:local(card))` => invalid state.
  - `:root:active-view-transition-type(:global(public-card), card)` => unexpected colon token.
  - Wrapping the full selector in `:global(...)` does not make `:local(...)` / `:global(...)` valid inside the view-transition function argument.

## Implementation

- `CssModulesTransformer` now validates view-transition selector-function arguments in both local and global selector modes before rewriting or preserving the argument.
- Normal local-mode arguments such as `::view-transition-group(card)` continue to scope and export the custom ident.
- Normal global-mode selectors such as `:global(:root::view-transition-old(public-card))` remain public.
- Local `composes` exports remain attached to their owning class in the same stylesheet.
- Added `examples/wordpress-css-modules-view-transition-guards.php` for a build-free block CSS Module that composes a reset class, scopes `view-transition-name`, scopes local view-transition selector idents, preserves a public global view-transition selector, and rejects invalid `:global(...)` inside the function argument.

## Evidence

- Red-first PHP spot-check before the fix accepted `:root::view-transition-group(:global(public-card))` and emitted `:root::view-transition-group(:EgL3uq_global(EgL3uq_public-card))`, which does not match upstream.
- `php -l lanes/lightningcss/src/CssModulesTransformer.php && php -l lanes/lightningcss/tests/CssModulesTransformerTest.php && php -l lanes/lightningcss/examples/wordpress-css-modules-view-transition-guards.php` => no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` => `1 test files, 346 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 5143 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-view-transition-guards.php --self-test` => `OK`.
- `git diff --check -- lanes/lightningcss` => passed.
- Root harness status: not run - isolated micro-slice.

## Status Delta

- Focused CSS Modules evidence moves from `338` to `346` assertions.
- Full LightningCSS PHP evidence moves from `5135` to `5143 pass / 0 fail`.
- Conservative mapped coverage remains `2238 / 3532`; this deepens the already represented CSS Modules local/global/composes cluster rather than claiming a new denominator row.

## Dependency Closure

No new support component is needed. This reuses the lane-local CSS Modules selector scanner, view-transition selector-function serializer, export metadata model, dependency compose class-list helper, and WordPress example harness. No Node, Rust, WASM, parser generator, browser service, or external CSS engine is required at runtime.

## Non-Overlap

This does not repeat accepted CSS Modules local/global selector-list validation, escaped pseudo names, state/highlight idents, host-context behavior, invalid composes fallback, unused-symbol pruning, animation/grid/container/scope/font-palette/position-try dashed-ident handling, bundle dependency diagnostics, media-query, source-map, target-prefix, CSSOM, or custom at-rule visitor slices. The bounded behavior is the view-transition selector-function argument boundary for CSS Modules local/global pseudos while preserving ordinary local composes metadata.
