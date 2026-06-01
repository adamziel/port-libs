# CSS Modules escaped custom-ident compose parity

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260601T082235Z`

Source truth:
- Pinned upstream: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream files: `src/lib.rs::test_css_modules`, `src/properties/animation.rs`, `src/properties/transition.rs`, `src/selector.rs`, `src/values/ident.rs`, and `src/printer.rs`.
- Upstream behavior: CSS parser identifier tokens decode escapes before `Printer::write_ident()` applies CSS Modules scoping. This applies to animation custom idents, `view-transition-*` declarations, `:active-view-transition-type()`, and `::view-transition-*()` selector arguments. `view-transition-group` keywords include `normal`, `contain`, and `nearest`.

Implementation:
- Made `CssModulesTransformer::splitWhitespaceTopLevel()` escape-aware so hex escapes with terminator whitespace stay inside one token.
- Decoded escaped `view-transition-name`, `view-transition-class`, and `view-transition-group` idents before CSS Modules scoping.
- Replaced regex-only view-transition selector argument rewriting with `readCssIdentifierToken()` parsing, so escaped part names and classes scope as one decoded ident.
- Added a WordPress smoke for escaped animation and view-transition identifiers in a block CSS Module with dependency `composes` metadata.

Verification:
- `php -l lanes/lightningcss/src/CssModulesTransformer.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-css-modules-escaped-custom-ident.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` -> 1 test file, 476 assertions, 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests` -> 13 test files, 6900 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-css-modules-escaped-custom-ident.php --self-test` -> OK.

Status delta:
- Full lane PHP evidence moves from 6894 to 6900 assertions.
- Conservative mapped coverage remains `2360 / 3532`; this deepens the already represented CSS Modules local/global/composes cluster rather than claiming a new denominator row.

Dependency closure:
- No new support component is needed. This reuses the lane-local `CssModulesTransformer`, CSS identifier escape reader, selector scanner, and existing export/class-list helpers.

Non-overlap:
- This does not repeat accepted CSS Modules escaped local/global pseudos, escaped class and composes identifiers, nested `composes` rejection, host/slotted/cue/state/highlight selectors, raw view-transition guard diagnostics, bundle/import graph work, source-map VLQ behavior, media-query parsing, CSSOM, property-value minification, custom at-rule visitors, or target prefixing. The slice is limited to escaped custom-ident token decoding before CSS Modules animation/view-transition scoping while preserving `composes` metadata.
