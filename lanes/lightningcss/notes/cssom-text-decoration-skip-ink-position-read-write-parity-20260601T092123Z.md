# CSSOM Text Decoration Skip-Ink And Emphasis Position Read/Write Parity

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260601T092123Z`

Source truth:

- Pinned upstream `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Upstream exposes `text-decoration-skip-ink`, `-webkit-text-decoration-skip-ink`, `text-emphasis-position`, and `-webkit-text-emphasis-position` in `src/properties/mod.rs` / `src/properties/text.rs`.
- Upstream minifier behavior in `src/lib.rs` lowercases `text-decoration-skip-ink: all` and serializes `text-emphasis-position: over right` as `over`, while retaining `over left`.

Red-first evidence before implementation:

```text
text-decoration-skip-ink: ALL => 'ALL'
-webkit-text-decoration-skip-ink: NONE => 'NONE'
text-emphasis-position: over right => 'over right'
text-emphasis-position: UNDER LEFT => 'UNDER LEFT'
setProperty(..., text-emphasis-position, OVER RIGHT) => text-emphasis-position: OVER RIGHT; text-decoration-skip-ink: ALL
```

Implementation:

- `DeclarationBlock` now canonicalizes CSSOM declaration values for `text-decoration-skip-ink` and prefixed `-webkit-text-decoration-skip-ink` through the upstream keyword set `auto | none | all`.
- `DeclarationBlock` now canonicalizes `text-emphasis-position` and prefixed `-webkit-text-emphasis-position` by accepting upstream vertical/horizontal token order, lowercasing tokens, and omitting default `right`.
- Custom properties remain case-preserving and are not routed through these property-specific normalizers.
- Added a WordPress link underline / typography CSSOM smoke for get/set/remove behavior without Node or WASM.

Verification:

```text
php -l lanes/lightningcss/src/DeclarationBlock.php
No syntax errors detected in lanes/lightningcss/src/DeclarationBlock.php

php -l lanes/lightningcss/tests/DeclarationBlockTest.php
No syntax errors detected in lanes/lightningcss/tests/DeclarationBlockTest.php

php -l lanes/lightningcss/examples/wordpress-text-decoration-skip-ink-cssom.php
No syntax errors detected in lanes/lightningcss/examples/wordpress-text-decoration-skip-ink-cssom.php

php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php
1 test files, 1154 assertions, 0 failures

php lanes/lightningcss/examples/wordpress-text-decoration-skip-ink-cssom.php --self-test
OK

php tools/run-tests.php lanes/lightningcss/tests
13 test files, 7129 assertions, 0 failures
```

Dependency closure:

- No new support component is needed. The slice reuses the existing native PHP declaration parser, CSSOM read/write serializer, and top-level token splitter.

Non-overlap:

- This does not duplicate the accepted target-prefix `text-decoration-skip-ink` boundary slice or existing text-decoration/text-emphasis shorthand CSSOM work. It only closes direct CSSOM declaration read/write canonicalization for skip-ink and emphasis-position properties.
