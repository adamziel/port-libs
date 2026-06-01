# LightningCSS Bundle Resolution Import Graph Parity

Micro-slice: `lightningcss-bundle-resolution-import-graph-parity-20260601T233933Z`

Base accepted HEAD: `228d94b36f9b650f582d4caa8692141e92595d69`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Upstream `src/bundler.rs` collects CSS Modules dependencies from direct top-level style declarations in declaration order: `Property::Composes`, then dashed-ident references found while visiting custom/unparsed declaration values.
- Direct native addon probe from the pinned upstream cache confirmed output order follows source declaration order:
  - `var(--brand from "./tokens.css")` before `composes: card from "./card.css"` emits the token module before the card module.
  - `composes` before the `var(... from)` reference emits the card module before the token module.

## Implementation

- `CssBundler::loadFile()` now schedules CSS Modules dependency imports with `cssModuleDependencySpecifiersInSourceOrder()`.
- The new scheduler only reorders specifiers already discovered by the existing CSS Modules transform/export pass.
- It scans the original source, not transformed output, for direct top-level `composes` declarations and direct style-declaration `var()` / `env()` references, then appends any missed known specifiers in the previous fallback order.

## Verification

- `php -l lanes/lightningcss/src/CssBundler.php` passed.
- `php -l lanes/lightningcss/tests/CssBundlerTest.php` passed.
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` passed.
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` passed: 1 file, 862 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` passed and emitted `css-modules-mixed-dependency-order: declaration-order`.
- `php tools/run-tests.php lanes/lightningcss/tests` passed: 14 files, 9142 assertions, 0 failures.

## Status Delta

- `phpPass`: 9138 -> 9142 (+4).
- `mapped`: unchanged at 2439 / 3532; this deepens an existing bundle/import graph and CSS Modules dependency mapping rather than adding a new manifest unit.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP `CssBundler`, `CssModulesTransformer`, source scanner helpers, and WordPress bundle/import graph example path.

## Non-Overlap And Follow-Up

This does not overlap the accepted runner-evidence slice, source-map offset slices, media-query range slices, or CSS Modules `:has(:scope)` selector-rewrite slice. A useful follow-up would cover additional parser-level CSS Modules dependency-source interactions, especially cases where dependency ordering interacts with import conditions or nested parser diagnostics.
