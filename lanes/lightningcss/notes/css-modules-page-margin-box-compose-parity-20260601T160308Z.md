# LightningCSS CSS Modules Page Margin Box Compose Parity 2026-06-01T16:10Z

## Scope

- Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260601T160308Z`.
- Source truth: pinned `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`, focused on CSS Modules `composes` descriptor handling in `@page` rules and nested page margin boxes.
- Native NAPI oracle used `/home/claude/port-libs/.upstream-cache/lightningcss/lightningcss.linux-x64-gnu.node` for targeted checks only, not as runtime support.

## Behavior

- `@page` rules that mix declarations with margin-box blocks now scope page-level `composes` descriptor names even when the descriptors appear after a margin box.
- Page declarations serialize before nested page margin boxes, matching upstream minified output.
- Valid `composes` inside nested page margin boxes now raises `The \`composes\` property cannot be used within nested rules`.
- Invalid margin-box `composes` fallback declarations such as `composes: from global` remain emitted CSS and do not create exports.

## Red-First Evidence

- Before the patch, `@page { @top-left { composes: print-card from "./print.css"; margin: 1in } }` emitted the raw nested body and did not reject valid nested `composes`.
- Before the patch, `@page :first { composes: print-card from global; margin: 1in; @top-left { color: red } }` left `print-card` unscoped and absent from exports.

## Verification

- `php -l lanes/lightningcss/src/CssModulesTransformer.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-css-modules-page-composes.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` -> `1 test files, 674 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-page-composes.php --self-test` -> `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 8632 assertions, 0 failures`.

## Status Delta

- `phpPass` moves `8621 -> 8632`.
- Conservative mapped coverage remains `2398 / 3532`; this deepens the existing CSS Modules local/global/composes descriptor cluster rather than claiming a new upstream denominator row.
- Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP CSS Modules transformer, page descriptor scanner, declaration minifier, compose tokenizer, PHP tests, and existing WordPress page-composes example harness.

## Non-Overlap

This does not repeat accepted functional `:local()` compose rejection, nested style-rule compose diagnostics, unknown at-rule raw-body preservation, page-level descriptor-only `@page` composes, counter-style/font-palette/view-transition/position-try descriptors, dashed-ident scoping, bundle/import graph, source-map, CSSOM, media-query, custom-at-rule visitor, target-prefix, or property-value slices.
