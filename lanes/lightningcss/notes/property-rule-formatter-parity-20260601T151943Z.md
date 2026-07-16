# @property Formatter Parity

Slice: `lightningcss-property-values-color-font-grid-parity-20260601T151943Z`

Base accepted HEAD: `1ae10d3b407a43d8a283421317a85a7a1d500366`

Upstream source truth: pristine `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`, `src/lib.rs::test_property`.

## Behavior

The earlier property-value slice mapped the 15 `minify_test` and 6 `error_test` helper calls in `test_property`, leaving 5 non-minifying pretty-printer `test` helper calls unmapped. This slice maps those remaining printer cases in native PHP:

- top-level `@property --property-name` blocks print quoted `syntax` descriptors;
- empty `initial-value` descriptors print as `initial-value: ;`;
- typed grammar alternatives such as `'<length>|none'` print as `"<length> | none"`;
- nested `@property` blocks inside `@media (width < 800px)` and `@layer foo` preserve the wrapper and omit the final semicolon when no `initial-value` descriptor is present.

The WordPress smoke covers block-theme design-token `@property` registrations inside a cascade layer without Node.

## Verification

- `php -l lanes/lightningcss/src/CssFormatter.php` - passed.
- `php -l lanes/lightningcss/tests/CssFormatterTest.php` - passed.
- `php -l lanes/lightningcss/examples/wordpress-property-registration-formatter.php` - passed.
- `php tools/run-tests.php lanes/lightningcss/tests/CssFormatterTest.php` - passed, `1 test files, 14 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-property-registration-formatter.php --self-test` - passed, `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` - passed, `13 test files, 8405 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` - passed.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice extends the existing native `CssFormatter` parsing/printing path for `@property`, `@media`, and `@layer` rule wrappers.

## Non-Overlap

This does not repeat the accepted `@property` minifier/validation behavior, color/font/grid property-value minification, CSS Modules, bundle/import graph, source-map, media-query, CSSOM, or target-prefix clusters. It only closes the five previously unmapped `src/lib.rs::test_property` pretty-printer cases.
