# CSS Modules Language Pseudo Local/Global Compose Parity

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260601T062843Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Local pinned NAPI oracle from `.upstream-cache/lightningcss/lightningcss.linux-x64-gnu.node` confirmed:
  - Escaped and uppercase language/direction pseudo names canonicalize, e.g. `.card:D\49 R(ltr)` prints `:dir(ltr)` and `.card:l\61 ng(en, fr)` prints `:lang(en,fr)`.
  - CSS Modules mode pseudos inside `:dir()` / `:lang()` arguments reject with `Unexpected token Colon`, e.g. `:dir(:global(ltr))`, `:lang(:global(en))`, and `:lang(en, :local(fr))`.
  - Raw custom pseudo functions remain raw; this slice is limited to the parsed `:dir()` / `:lang()` selector functions.

## Implementation

- `CssModulesTransformer` now recognizes decoded `:dir()` and `:lang()` selector function names in the CSS Modules selector path.
- Those functions serialize with canonical lowercase names and minified comma-separated arguments.
- The selector function arguments now reject `:local` / `:global` pseudos before export metadata is trusted, matching upstream parser diagnostics.
- Existing local/global/dependency `composes` export handling is unchanged; the focused test proves local compose class lists still flatten.
- Added `wordpress-css-modules-language-pseudo-guards.php` for block CSS that uses escaped selector pseudo names and rejects invalid migrated local/global language pseudo arguments.

## Verification

- Red-first PHP spot check before the patch accepted `.card:dir(:global(ltr))` and emitted `.EgL3uq_card:dir(:global(ltr)){...}`, while the pinned upstream artifact rejected with `Unexpected token Colon`.
- `php -l lanes/lightningcss/src/CssModulesTransformer.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-css-modules-language-pseudo-guards.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` -> `1 test files, 431 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-language-pseudo-guards.php --self-test` -> `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 6437 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` -> passed.
- Root harness status: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `6429 -> 6437`.
- Conservative mapped coverage remains `2359 / 3532`; this deepens the represented CSS Modules local/global/composes selector cluster rather than claiming a new denominator row.

## Dependency Closure

No new support component is needed. This reuses the native PHP CSS Modules selector scanner, selector-function serialization, export metadata model, minifier pipeline, and WordPress example self-test harness. No Node, Rust, WASM, browser service, parser generator, or external CSS engine is required at runtime.

## Non-Overlap

This does not repeat accepted escaped local/global pseudo handling, selector-valued pseudo canonicalization for `:is()` / `:where()` / `:has()` / `:not()` / prefixed `any()`, single `:is()` unwrapping, nth-child formula minification, host-context behavior, view-transition guards, nested `composes` rejection, bundled CSS Modules option propagation, source-map, bundle/import graph, media-query, property-value, CSSOM, target-prefixing, or custom at-rule slices. The patch is limited to parsed `:dir()` / `:lang()` selector functions on the CSS Modules local/global/composes path.

## Next Task

Continue with non-overlapping CSS Modules selector/composes edges, or pivot to current-priority LightningCSS source-map, bundle/import graph, CSSOM, media-query, target-prefix, property/value, or custom at-rule parity.
