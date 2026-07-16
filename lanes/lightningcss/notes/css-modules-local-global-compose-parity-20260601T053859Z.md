# CSS Modules Local/Global Compose Diagnostics Parity - 2026-06-01T05:38Z

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260601T053859Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Local pinned NAPI artifact checks reported:
  - `:global() { color: red }` and `:local() { color: red }` reject with `Invalid empty selector`.
  - `:global(.a, .b) { color: red }` and `:local(.a, .b) { color: red }` reject with `Unexpected token Comma`.
  - Bare `:global`, bare `:local`, and attached `.foo:global` / `.foo:local` reject with `Ambiguous CSS module class not supported`.

## Implementation

- `CssModulesTransformer` now emits the upstream diagnostics for empty functional local/global selectors, selector-list commas inside those functions, and ambiguous bare local/global pseudos.
- Focused tests assert the exact upstream messages before composed export metadata can be produced from later local rules.
- Added `wordpress-css-modules-local-global-diagnostics.php` to smoke a block CSS Module with valid local/global composition plus invalid migration shapes that should fail with source-truth diagnostics.

## Verification

- `php -l lanes/lightningcss/src/CssModulesTransformer.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-css-modules-local-global-diagnostics.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` -> `1 test files, 414 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-local-global-diagnostics.php --self-test` -> `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 6242 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` -> passed.
- Root harness status: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `6237 -> 6242`.
- Conservative mapped coverage remains `2359 / 3532`; this deepens the existing CSS Modules local/global/composes selector diagnostics cluster rather than claiming a new upstream denominator row.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP CSS Modules selector scanner, composes export metadata path, minifier/nesting output path, and existing PHP example harness. No Node, Rust, WASM, browser service, parser generator, or external CSS engine is required at runtime.

## Non-Overlap

This does not repeat accepted escaped local/global pseudos, comment-split selector guards, invalid escaped selector-newline guards, pure license no-check handling, pseudo-element boundaries, host-context behavior, forgiving selector filtering, nested `composes` rejection, bundled CSS Modules option propagation, source-map, bundle/import graph, media-query, property-value, CSSOM, custom-at-rule, or target-prefixing slices. It is limited to exact upstream diagnostics for invalid local/global selector syntax while preserving valid local/global composes output.
