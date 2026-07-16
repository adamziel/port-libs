# CSSOM Display Layout Read/Write Parity - 2026-06-01

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260601T055715Z`

Upstream source truth:

- Pinned upstream commit: `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/properties/mod.rs` defines `display`, `visibility`, `position`, `box-sizing`, `text-overflow`, `vertical-align`, `transform-style`, `transform-box`, `backface-visibility`, `perspective`, `mix-blend-mode`, and `z-index` as parsed properties.
- `src/properties/display.rs` serializes `display: inline flow-root` as `inline-block`, `block flow` as `block`, `inline flow` as `inline`, `inline grid` as `inline-grid`, and preserves the `flow-root list-item` form.
- Local upstream binary oracle at the pinned cache emitted matching minified CSS for the display/layout examples.

Implemented behavior:

- `DeclarationBlock` now canonicalizes CSSOM read/write values for the upstream display pair grammar and direct layout/transform keyword declarations.
- CSS-wide keywords and custom properties keep the existing behavior; custom properties such as `--wp--custom--display` remain case preserving.
- Added a WordPress-style smoke for theme/block display state manipulation without requiring Node/WASM at runtime.

Dependency closure:

- No new support component is needed. The slice reuses the existing native PHP `DeclarationBlock` parser/serializer.

Verification:

- `php -l lanes/lightningcss/src/DeclarationBlock.php` - passed.
- `php -l lanes/lightningcss/tests/DeclarationBlockTest.php` - passed.
- `php -l lanes/lightningcss/examples/wordpress-display-layout-cssom.php` - passed.
- `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` - passed, `1 test files, 1035 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-display-layout-cssom.php --self-test` - passed.
- `php tools/run-tests.php lanes/lightningcss/tests` - passed, `13 test files, 6339 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` - passed.
