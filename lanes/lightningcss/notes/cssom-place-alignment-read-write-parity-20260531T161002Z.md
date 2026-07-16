# CSSOM Place Alignment Read/Write Parity

Slice: `lightningcss-cssom-declaration-read-write-parity-20260531T161002Z`

Source truth:

- Upstream pinned manifest commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream files inspected from the local cache:
  - `src/declaration.rs`: `DeclarationBlock::get`, `set`, and `remove` expand longhands from shorthands, update same-priority shorthand values in place, and split shorthands when a longhand is removed.
  - `src/properties/align.rs`: `PlaceContent`, `PlaceSelf`, and `PlaceItems` parse align/justify pairs, fill omitted justify values, normalize first-baseline output to `baseline`, default omitted `place-content` baseline justification to `start`, and support `legacy left/right/center` serialization for `justify-items`.

Implemented behavior:

- `DeclarationBlock::getProperty()` now reads `place-content`, `place-self`, and `place-items` shorthands as their align/justify longhands, and composes those shorthands from complete same-priority longhand pairs.
- `setProperty()` now updates same-priority place shorthands when setting `align-content`, `justify-content`, `align-self`, `justify-self`, `align-items`, or `justify-items`; mismatched priority keeps upstream-style bucket separation.
- `removeProperty()` now removes place shorthands together with their longhands, and splits a place shorthand into the remaining longhand when one included longhand is removed.
- Added `wordpress-place-alignment-cssom.php` to show block layout alignment edits without Node.

Evidence:

- Before implementation, local probing returned `null` for `getProperty('place-content: center space-between', 'align-content')`, `null` for composing `place-content` from align/justify longhands, appended `align-content` instead of mutating the shorthand, and left `place-content` unchanged when removing `align-content`.
- Focused after-run: `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` -> `1 test files, 331 assertions, 0 failures`.
- Full lane after-run: `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 2122 assertions, 0 failures`.
- Example after-run: `php lanes/lightningcss/examples/wordpress-place-alignment-cssom.php --self-test` -> `OK`.

Coverage delta:

- Full LightningCSS PHP evidence moves from `2092` to `2122` pass / `0` fail.
- Conservative mapped coverage remains `1349 / 3532` because this deepens the existing DeclarationBlock CSSOM cluster rather than claiming a new upstream denominator row.

Non-overlap:

- This slice avoids accepted CSSOM text-decoration, outline, background, border, border-image, mask-border, transition, animation, gap, overflow, scroll-snap, grid, and inset clusters.
- The stale current-base rework note for `CustomMediaTransformer.php` is unrelated to this DeclarationBlock CSSOM alignment work.

Dependency closure:

- No new support component is needed. The implementation reuses the lane-local declaration parser/serializer helpers and pinned upstream source evidence.
