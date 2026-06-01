# CSSOM Direct Declaration Read/Write Parity

Slice: `lightningcss-cssom-declaration-read-write-parity-20260601T053943Z`

Source truth:

- Pinned upstream LightningCSS commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Upstream `src/declaration.rs::DeclarationBlock::get`, `set`, and `remove` operate on parsed `Property` values and serialize them via `ToCss`.
- Upstream property definitions in `src/properties/mod.rs`, `src/properties/display.rs`, `src/properties/position.rs`, `src/properties/overflow.rs`, `src/properties/size.rs`, and `src/properties/effects.rs` define parsed direct declaration values for `visibility`, `box-sizing`, `position`, `text-overflow`, `mix-blend-mode`, `z-index`, and `aspect-ratio`.

Implemented behavior:

- `DeclarationBlock` now canonicalizes direct layout/effects CSSOM declarations when parsing, reading, setting, and removing.
- Covered direct keywords: `visibility`, `box-sizing`, `position`, `text-overflow`, and `mix-blend-mode`.
- Covered numeric/value forms: `z-index` strips plus signs and redundant integer zeroes, and `aspect-ratio` serializes optional `auto` plus ratio numbers through a canonical `width / height` form while omitting `/ 1`.
- Custom properties remain raw and case-preserving.

Verification:

- `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php`
  - `1 test files, 1017 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 6251 assertions, 0 failures`
- `php -l lanes/lightningcss/src/DeclarationBlock.php && php -l lanes/lightningcss/tests/DeclarationBlockTest.php && php -l lanes/lightningcss/examples/wordpress-layout-effects-cssom.php`
  - no syntax errors detected
- `php lanes/lightningcss/examples/wordpress-layout-effects-cssom.php --self-test`
  - `OK`
- `git diff --check -- lanes/lightningcss`
  - passed

Dependency closure:

- No new support component is needed. This reuses the existing native PHP declaration parser/serializer and the pinned upstream LightningCSS property definitions as source truth.

Non-overlap:

- This deepens the already represented DeclarationBlock CSSOM cluster. It does not touch bundle/import graph, source-map, CSS Modules, custom at-rules, media-query, target-prefixing, or property-value minifier clusters.
