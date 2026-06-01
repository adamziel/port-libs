# LightningCSS CSSOM Mask Type Read/Write Parity

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260601T072253Z`

Source truth:

- Pinned upstream commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/properties/mod.rs` registers `mask-type` as a first-class declaration mapped to `MaskType`.
- `src/properties/masking.rs` defines `MaskType` as the `alpha` / `luminance` enum used for standalone SVG mask-type declarations, distinct from the `mask-mode` longhand in the `mask` shorthand.

Native PHP change:

- `DeclarationBlock` now canonicalizes standalone `mask-type` enum declarations through the same CSSOM parse/get/set path used by direct declaration values.
- Valid `mask-type` values read/write as lowercase `alpha` and `luminance`; custom properties that happen to mention mask type preserve authored casing.
- The focused CSSOM test covers parsing, `getProperty()`, `setProperty()` priority ordering, `removeProperty()`, and custom property non-interference.

WordPress path:

- `examples/wordpress-mask-type-cssom.php` models block/theme tooling that adjusts decorative SVG mask behavior while preserving custom design-token declarations without Node/WASM.

Evidence:

- Red probe before patch: `DeclarationBlock::parse("mask-type: LUMINANCE")` returned `mask-type => LUMINANCE`, and `getProperty("mask-type: ALPHA", "mask-type")` returned `ALPHA`.
- `php -l lanes/lightningcss/src/DeclarationBlock.php` -> no syntax errors
- `php -l lanes/lightningcss/tests/DeclarationBlockTest.php` -> no syntax errors
- `php -l lanes/lightningcss/examples/wordpress-mask-type-cssom.php` -> no syntax errors
- `php lanes/lightningcss/examples/wordpress-mask-type-cssom.php --self-test` -> `OK`
- `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` -> `1 test files, 1070 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 6704 assertions, 0 failures`
- `git diff --check -- lanes/lightningcss` -> pass

Dependency closure:

- No new support component is needed. This reuses the existing bounded `DeclarationBlock` declaration parser, enum keyword canonicalizer, priority buckets, and serializer.

Non-overlap:

- This slice does not repeat the just-ready text-spacing CSSOM handoff (`tab-size`, `word-spacing`, `letter-spacing`, `text-indent`) or accepted mask shorthand/longhand CSSOM behavior. It only adds standalone `mask-type` enum parity backed by the pinned upstream masking property source.
