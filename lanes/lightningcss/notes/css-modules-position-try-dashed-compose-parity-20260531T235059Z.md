# CSS Modules Position-Try Dashed Compose Parity

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260531T235059Z`

Base accepted HEAD: `b2a0ea9050b31220cefa69c10914986b6a41bc76`

## Source Truth

- Upstream pinned commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream reads:
  - `src/rules/position_try.rs`, where `@position-try` names are `DashedIdent`.
  - `src/css_modules.rs`, where CSS Modules `dashed_idents` scopes dashed identifiers and preserves `composes` metadata.
  - `src/lib.rs::test_position_try`, confirming the `@position-try` rule body and nested `@supports` cases.
- Native NAPI spot-check at the pinned cache confirmed `@position-try --flyout` becomes `@position-try --EgL3uq_flyout`, `position-try-fallbacks: --flyout` becomes `--EgL3uq_flyout`, `var(--flyout)` marks the dashed export referenced, and `@supports (anchor-name: --menu-anchor)` remains public.

## Behavior

- `CssModulesTransformer` now scopes `@position-try` dashed rule names when `dashedIdents` is enabled.
- `position-try-fallbacks` bare dashed identifiers are scoped/exported without marking them referenced, matching upstream fallback-name behavior.
- Existing `var()` / `env()` dashed references remain handled by the accepted reference path and still mark the export as referenced.
- Local `composes` metadata on the owning class is preserved while position fallback names are exported separately.
- `wordpress-css-modules-position-try.php` models anchored block popover CSS for WordPress/Playground delivery without Node or WASM.

## Evidence

- Red-first local probe before the patch: `@position-try --flyout` and `position-try-fallbacks: --flyout` stayed unscoped and exported only `card` / `base`.
- `php -l lanes/lightningcss/src/CssModulesTransformer.php` => no syntax errors.
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php` => no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-css-modules-position-try.php` => no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` => `1 test files, 335 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 4976 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-position-try.php --self-test` => `OK`.
- `php lanes/lightningcss/examples/wordpress-css-modules-transformer.php --self-test` => `OK`.
- Root harness status: not run - isolated micro-slice.

## Status Delta

- Focused CSS Modules evidence moves from `324` to `335` assertions.
- Full LightningCSS PHP evidence moves from `4965` to `4976` pass / `0` fail.
- Conservative mapped coverage remains `2212 / 3532`; this deepens the existing CSS Modules dashed-ident/composes cluster rather than claiming a new denominator row.

## Dependency Closure

No new support component is needed. This reuses the lane-local CSS Modules transformer, dashed-ident export model, declaration scanner, nesting/minification pass, and existing PHP test/example harness.

## Non-Overlap

This does not repeat accepted CSS Modules local/global selector-list validation, escaped `:local` / `:global` pseudo names, pseudo replacement classes, state/highlight idents, host-context handling, invalid composes fallback, unused-symbol pruning, animation/grid/container/scope/font-palette dashed-ident handling, bundle dependency diagnostics, media-query, source-map, target-prefix, CSSOM, or custom at-rule visitor slices. The stale 2026-05-25 `CustomMediaTransformer` rework note was inspected and is unrelated to this CSS Modules behavior.

## Next

Continue CSS Modules parity on non-overlapping selector/value integration, dependency graph flattening, or unused-symbol boundaries not covered by accepted local/global/composes, dashed-ident, position-try, and bundle-import slices.
