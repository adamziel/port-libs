# CSS Modules Local/Global Compose Priority Parity

- Slice: `lightningcss-css-modules-local-global-compose-parity-20260531T164837Z`.
- Source truth: pinned `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream area: `src/declaration.rs` handles CSS Modules `Property::Composes` in normal and important declaration buckets; `src/properties/css_modules.rs` parses the `composes` value after declaration priority has been separated.
- Native upstream spot-check with the pinned local Node artifact confirmed that `composes: b !important`, `composes: b ! important`, `composes: b from global !IMPORTANT`, and `composes: b from "./b.css"!important` remove the declaration from emitted CSS while preserving local/global/dependency export metadata. A guard spot-check confirmed `composes: foo\!important` remains a class-name reference, not declaration priority.

## Implementation

- `CssModulesTransformer` now strips a trailing unescaped CSS declaration priority marker before parsing `composes` values.
- The parser keeps existing strict `composes` grammar for malformed local/global/dependency values; only the final declaration priority is ignored, matching upstream declaration parsing.
- The priority scan skips strings and CSS escapes, so escaped identifiers such as `foo\!important` remain valid composed class names.
- `wordpress-css-modules-transformer.php` now exercises global and dependency `composes` declarations with `! important` / `!important` priority markers in a build-free block module path.

## Evidence

- Pre-fix PHP spot-check rejected all three priority-marked compose forms with `Invalid CSS Modules composes declaration`.
- `php -l lanes/lightningcss/src/CssModulesTransformer.php && php -l lanes/lightningcss/tests/CssModulesTransformerTest.php && php -l lanes/lightningcss/examples/wordpress-css-modules-transformer.php` passed.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` passed: `1 test files, 121 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-transformer.php --self-test` passed: `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` passed: `13 test files, 2325 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` passed.
- Root harness status: not run - isolated micro-slice.

## Status Delta

- Full LightningCSS PHP evidence moves from `2313` to `2325 pass / 0 fail`.
- Conservative mapped coverage remains `1446 / 3532`; this deepens the already represented CSS Modules local/global/composes cluster rather than adding a new denominator row.

## Dependency Closure

No new support component is needed. This reuses the existing lane-local CSS Modules declaration scanner, priority-aware statement handling, composes tokenizer, selector/export metadata model, and minifier/nesting pipeline. No Node, Rust, WASM, browser service, or external CSS parser is required at runtime.

## Non-Overlap

This does not repeat accepted CSS Modules selector-list validation, nested global/local mode precedence, escaped local identifiers, escaped dependency specifiers, functional `:local()` composes rejection, pure-mode selector boundaries, view-transition scoping, content-hash patterning, or bundler dependency export flattening. It only covers declaration-priority parity for local/global/dependency `composes` declarations.
