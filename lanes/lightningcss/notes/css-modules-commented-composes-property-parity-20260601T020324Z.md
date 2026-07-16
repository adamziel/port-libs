# CSS Modules Commented Composes Property Parity

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260601T020324Z`

Source truth:

- Upstream commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Relevant upstream code: `src/properties/css_modules.rs::Composes::parse` and `src/css_modules.rs::CssModule::handle_composes`.
- Local NAPI oracle at the pinned commit confirmed that `comp/*x*/oses:` and `c\6f/*x*/mposes:` are invalid declaration names and must not create CSS Modules compose metadata, while `composes/*x*/:` and `c\6f mposes/*x*/:` remain valid property names and preserve local, global, and dependency composes.

Implementation:

- `CssModulesTransformer` now preserves comment token boundaries in declaration heads instead of joining property-name fragments across comments.
- Declaration names are validated as a single CSS identifier before the CSS Modules `composes` parser runs, so invalid commented fragments are omitted without producing phantom compose metadata.
- Comments after a complete escaped or unescaped `composes` property name still keep the declaration valid.
- Added a WordPress-facing self-test example for build-free block CSS Modules transforms with invalid commented fragments and valid local/global/dependency composes.

Evidence:

- Red-first PHP spot check before the fix showed invalid commented `composes` fragments created compose metadata that upstream does not create.
- `php -l lanes/lightningcss/src/CssModulesTransformer.php` passed.
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php` passed.
- `php -l lanes/lightningcss/examples/wordpress-css-modules-commented-composes-property.php` passed.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` passed: `1 test files, 366 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-commented-composes-property.php --self-test` passed: `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` passed: `13 test files, 5458 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` passed.
- Root harness not run: isolated micro-slice.

Status delta:

- Focused CSS Modules test: `359 -> 366` assertions, `+7`.
- Full LightningCSS lane: `5451 -> 5458` assertions.
- Conservative mapped coverage remains `2297 / 3532`; this deepens the existing CSS Modules local/global/composes cluster.

Dependency closure:

- No new support component is needed.
- Reuses the existing CSS Modules transformer, comment scanner, CSS identifier decoder, composes parser, test harness, and example self-test harness.

Non-overlap:

- This is not a repeat of the accepted escaped `composes` property/value comment separator slice, selector local/global pseudo handling, invalid composes value parsing, bundle dependency composes graph, or unused-symbol pruning.
- The stale May 25 custom-media `@import` rework note was inspected and is unrelated to this CSS Modules behavior.

Next:

- Continue CSS Modules parity on non-overlapping selector/declaration/export metadata edges, or move to source-map, bundle/import graph, media-query, property-value, target-prefixing, CSSOM, and custom at-rule slices with full-lane PHP gates.
