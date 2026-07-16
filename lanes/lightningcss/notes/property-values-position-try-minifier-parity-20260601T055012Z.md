# LightningCSS Position-Try Minifier Parity - 2026-06-01

## Scope

- Lane: `lightningcss`
- Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260601T055012Z`
- Upstream source truth: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`, `src/lib.rs::test_position_try`.

The color/font/grid minifier surface was already heavily covered on this base. This patch locks the adjacent upstream property-value cluster for anchored positioning:

- top-level `@position-try` declarations with `anchor()` values, zero margin, and `auto` width;
- nested `@position-try` inside `@supports (anchor-name: ...)`;
- WordPress anchored popover CSS using `position-anchor` and `position-try-fallbacks` lists.

## Implementation

No new production helper was needed. The existing native PHP minifier path already serializes the pinned upstream rows exactly, so the patch adds focused regression coverage and a WordPress smoke example rather than introducing a redundant code path.

## Evidence

Verification was run from the isolated lane worktree:

- `php -l lanes/lightningcss/tests/CssMinifierTest.php` => no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-position-try-minifier.php` => no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` => `1 test files, 1820 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 6270 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-position-try-minifier.php --self-test` => `OK`.
- `git diff --check -- lanes/lightningcss` => clean.
- Root harness was not run; this is an isolated LightningCSS micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses the existing CSS tokenizer/minifier and declaration-value normalization machinery in `CssMinifier`.

## Non-Overlap

This does not repeat the accepted CSS Modules `@position-try` dashed-ident scoping slice. That earlier work covered module export/name rewriting; this patch covers plain minifier serialization against upstream `test_position_try` plus a WordPress runtime delivery smoke.

## Next

Continue property-value parity on any remaining upstream-backed color, font, grid, or anchor-positioning cases that require native PHP behavior rather than broad status or manifest churn.
