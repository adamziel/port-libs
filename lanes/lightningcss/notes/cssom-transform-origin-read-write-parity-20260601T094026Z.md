# CSSOM transform-origin read/write parity

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260601T094026Z`

Source truth:

- Pinned upstream LightningCSS commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Upstream `src/properties/mod.rs` declares `transform-origin` as `TransformOrigin(Position, VendorPrefix) / WebKit / Moz / Ms / O`.
- This extends the already represented `tests/test_cssom.rs` DeclarationBlock get/set/remove cluster with the transform-origin property family, rather than claiming a new denominator row.

Behavior implemented:

- `DeclarationBlock` now canonicalizes `transform-origin`, `-webkit-transform-origin`, `-moz-transform-origin`, `-ms-transform-origin`, and `-o-transform-origin` values during CSSOM parse/get/set.
- Keyword positions normalize to serialized coordinate pairs: `LEFT top` -> `0 0`, `right bottom` -> `100% 100%`, `bottom` -> `50% 100%`, and single horizontal keywords keep the default vertical center.
- CSS custom properties that resemble transform-origin remain untouched.
- Existing priority-bucket ordering and direct remove behavior are preserved.

Pre-fix evidence:

- `php -r 'require "lanes/lightningcss/src/DeclarationBlock.php"; $b = new PortLibs\LightningCSS\DeclarationBlock(); var_export($b->parse("transform-origin: LEFT top; -webkit-transform-origin: 0px 0px")); echo PHP_EOL; var_export($b->setProperty("transform-origin: LEFT top", "transform-origin", "bottom")); echo PHP_EOL;'`
- Output kept raw values: `LEFT top`, `0px 0px`, and `transform-origin: bottom`.

Verification:

- `php -l lanes/lightningcss/src/DeclarationBlock.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/DeclarationBlockTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-transform-cssom.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` -> `1 test files, 1162 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-transform-cssom.php --self-test` -> `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 7215 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` -> clean.

Dependency closure:

- No new support component is needed. The patch reuses the existing `DeclarationBlock` CSSOM parser, priority-bucket serializer, and top-level whitespace tokenizer.

Non-overlap:

- Avoids the accepted `5bccafbbc481` CSSOM text-decoration-skip-ink and text-emphasis-position slice. This patch only touches transform-origin declaration read/write canonicalization.
