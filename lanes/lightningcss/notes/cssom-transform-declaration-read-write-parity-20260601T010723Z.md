# CSSOM Transform Declaration Read/Write Parity

Slice: `lightningcss-cssom-declaration-read-write-parity-20260601T010723Z`

Base accepted HEAD: `e274bccd68de6d0be207ea53c6e2f938b9cd38dd`

Upstream source truth: pinned `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.

- `tests/test_cssom.rs` exercises `DeclarationBlock::get`, `set`, and `remove` through parsed `Property` values and `to_css_string(PrinterOptions::default())`.
- `src/properties/mod.rs` maps `transform`, `-webkit-transform`, `-moz-transform`, `-ms-transform`, `-o-transform`, `translate`, `rotate`, and `scale` into transform-family property variants.
- `src/properties/transform.rs` provides the transform-list and individual `translate` / `rotate` / `scale` parser-printer behavior.

Implementation:

- `DeclarationBlock` now serializes transform-family declaration values through the native PHP transform value normalizer during CSSOM parse/get/set/remove.
- `CssMinifier` now applies the same transform function-list normalization to `-ms-transform` and `-o-transform` as to the existing unprefixed/WebKit/Mozilla properties.
- `CustomAtRuleTransformer` keeps a raw-DeclarationBlock mode so style visitors still receive upstream-like transform AST function names such as `translateX`, while normal CSSOM callers receive serialized values.
- Added `wordpress-transform-cssom.php` as a WordPress block-style smoke covering cover transforms, individual transform properties, and legacy `-ms-transform` updates.

Status delta:

- Focused `DeclarationBlockTest.php` increased from 876 to 886 assertions.
- Full LightningCSS lane increased from 5247 to 5257 assertions.
- Manifest mapped coverage remains conservative at `2248 / 3532` because this deepens the already represented DeclarationBlock CSSOM cluster.

Verification:

- `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` -> `1 test files, 886 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` -> `1 test files, 192 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 5257 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-transform-cssom.php --self-test` -> `OK`
- Syntax and diff checks are recorded in the final handoff.

Dependency closure:

No new support component is required. This reuses the existing native PHP transform value serializer and keeps custom-at-rule visitor AST behavior isolated from CSSOM serialization.

Non-overlap:

This does not overlap the stale custom-media rework note or the accepted CSSOM alpha, shadow, border-spacing, background, logical-size, or target-prefixing slices. It is limited to transform-family declaration value serialization parity.
