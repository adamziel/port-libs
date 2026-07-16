# LightningCSS CSS Modules: Invalid Composes Selector Diagnostics

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Source files checked: upstream `src/lib.rs::test_css_modules`, `src/css_modules.rs`, and `src/properties/css_modules.rs`.
- The pinned native binding was probed directly from `/home/claude/port-libs/.upstream-cache/lightningcss/lightningcss.linux-x64-gnu.node` because the local upstream checkout does not have the `detect-libc` package needed by `node/index.js`.

## Behavior

Upstream rejects `composes` unless every selector in the selector list is a raw simple local class selector. The native PHP transformer already rejected these forms, but it returned a lane-local diagnostic instead of the upstream text.

This slice aligns the thrown message for invalid selector shape:

- `:local(.test) { composes: foo }`
- `:local(.test), .fallback { composes: foo }`
- `.test, :global(.fallback) { composes: foo }`
- `.test:global(.fallback) { composes: foo }`
- `.ancestor .test`, `.test:hover`, `.test.foo`, `#test`, and `:global(.test)`

The nested-rule path is deliberately preserved as upstream's separate diagnostic for `@media { :local(.test) { composes: foo } }`.

## Implementation

- Updated `CssModulesTransformer::assertValidComposesSelector()` to emit the upstream invalid-selector diagnostic.
- Strengthened `CssModulesTransformerTest` to assert exact diagnostics for local/global selector forms and the nested-rule distinction.
- Updated the WordPress double-colon pseudo example smoke to expect the upstream invalid-selector diagnostic.

## Verification

- `php -l lanes/lightningcss/src/CssModulesTransformer.php`
  - `No syntax errors detected in lanes/lightningcss/src/CssModulesTransformer.php`
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php`
  - `No syntax errors detected in lanes/lightningcss/tests/CssModulesTransformerTest.php`
- `php -l lanes/lightningcss/examples/wordpress-css-modules-double-colon-pseudos.php`
  - `No syntax errors detected in lanes/lightningcss/examples/wordpress-css-modules-double-colon-pseudos.php`
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php`
  - `1 test files, 559 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-css-modules-double-colon-pseudos.php --self-test`
  - `OK`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 7669 assertions, 0 failures`

Focused assertion delta: `CssModulesTransformerTest.php` adds two exact diagnostic assertions over the previous local/global composes checks. Conservative mapped coverage remains `2374 / 3532` because this deepens the existing CSS Modules behavior cluster rather than adding a new upstream manifest unit.

## Non-Overlap

This does not repeat accepted CSS Modules escaped custom ident exports, terminal pseudo-element selector boundaries, composes-before-nested ordering, import/source-index composes behavior, source maps, bundle/import graph diagnostics, CSSOM read/write, media queries, target-prefixing, custom at-rules, or property/value parity.

## Dependency Closure

No new support component is needed. The existing native PHP CSS Modules transformer, selector scanner, and WordPress example harness are reused; no Node, Rust, WASM, or external runtime dependency is introduced.

Root harness was not run; this is an isolated LightningCSS micro-slice.
