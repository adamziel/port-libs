# CSSOM Background Longhand Read/Remove Parity

- Slice: `lightningcss-cssom-declaration-read-write-parity-20260531T230236Z`
- Base accepted HEAD: `292ada6b86cc431f7b1537075eacedfb4e905cf4`
- Upstream source: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`

## Source Truth

- `src/declaration.rs`: `DeclarationBlock::get()` extracts longhands from shorthand declarations and `DeclarationBlock::remove()` splits a shorthand into remaining longhands when removing a longhand property.
- `src/properties/background.rs`: `Background::longhands()` returns `background-color`, `background-image`, `background-position-x`, `background-position-y`, `background-repeat`, `background-size`, `background-attachment`, `background-origin`, and `background-clip`; `Background::longhand()` returns default-valued longhands such as `none`, `repeat`, `auto`, `scroll`, `padding-box`, and `border-box` from a shorthand.
- `tests/test_cssom.rs`: the existing CSSOM helper cluster is the denominator anchor for DeclarationBlock get/set/remove parity; this slice deepens that represented cluster rather than claiming a new mapped row.

## Behavior

- `DeclarationBlock::getProperty()` now exposes upstream-style default background longhands from a `background` shorthand: `background-image: none`, `background-position: 0 0`, `background-repeat: repeat`, and `background-size: auto`.
- `DeclarationBlock::removeProperty()` now splits `background` shorthands when removing shorthand-derived background longhands, preserving the remaining values and filling upstream initial values where the shorthand omitted them.
- Existing `background-position` shorthand removal remains scoped to `background-position-x` / `background-position-y` and direct `background-position` declarations.
- Added `wordpress-background-longhand-removal-cssom.php` to model editor tooling that removes theme background color/image/size layers while preserving the other background components without Node/WASM.

## Evidence

- Red-first probe before the implementation:
  - `getProperty("background: red", "background-image")` returned `null`.
  - `removeProperty("background: red url(hero.jpg) 20px 10px no-repeat fixed border-box content-box", "background-color")` left the original `background` shorthand unchanged.
- Focused test: `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php`
  - Result: `1 test files, 832 assertions, 0 failures`.
- Full lane test: `php tools/run-tests.php lanes/lightningcss/tests`
  - Result: `13 test files, 4710 assertions, 0 failures`.
- Lint:
  - `php -l lanes/lightningcss/src/DeclarationBlock.php` -> no syntax errors.
  - `php -l lanes/lightningcss/tests/DeclarationBlockTest.php` -> no syntax errors.
  - `php -l lanes/lightningcss/examples/wordpress-background-longhand-removal-cssom.php` -> no syntax errors.
- Example smoke: `php lanes/lightningcss/examples/wordpress-background-longhand-removal-cssom.php --self-test` -> `OK`.
- Diff check: `git diff --check -- lanes/lightningcss` -> no output.
- JSON sanity check for `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json` -> `OK`.
- Focused assertion delta: `+8` over the accepted full-lane status count (`4702 -> 4710`).

## Non-Overlap

This slice only touches CSSOM `DeclarationBlock` background longhand read/remove behavior. It does not overlap source-map null `sourcesContent`, CSSOM priority buckets, CSS Modules escaped pseudo/local-global compose behavior, bundler/import graph, media-query, target-prefixing, property-value minification, or custom at-rule visitor work. Conservative mapped coverage remains `2174 / 3532`.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP declaration parser/serializer and focused lane tests; upstream Rust/Node/WASM runners were not executed.

## Next

Continue CSSOM parity on non-overlapping DeclarationBlock gaps, or pivot to bundle/import graph, source-map, CSS Modules, visitor/custom at-rule, target-prefixing, media-query, selector, parser recovery, or property/value parity slices with manifest-backed source truth.
