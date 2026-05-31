# LightningCSS Break Property Target Prefix Boundary Parity

Micro-slice: `lightningcss-target-prefixing-browser-boundary-parity-20260531T212241Z`

## Source Truth

- Upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Pinned source:

```bash
git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/prefixes.rs | nl -ba | sed -n '616,647p'
```

`src/prefixes.rs` maps `Feature::BreakBefore | Feature::BreakAfter | Feature::BreakInside` to `VendorPrefix::WebKit` for:

- Android 2.1 through 4.4.3
- Chrome 4 through 49
- iOS Safari 3.2 through 8.1
- Opera 15 through 36
- Safari 3.1 through 8
- Samsung Internet through 4

Red-first probe on this accepted worktree returned unprefixed-only output for `.foo { break-before: page; break-after: column; break-inside: avoid; }` targeting Chrome 49.

## Implementation

- `TransitionPrefixer` now computes a `breakNeedsWebkit` target flag from the upstream browser windows above.
- `break-before`, `break-after`, and `break-inside` reuse the existing vendor-prefixed declaration-group rewriter, so legacy WebKit targets receive prefixed declarations before the modern declaration.
- Modern targets remove stale equivalent `-webkit-break-*` declarations when the unprefixed declaration is present.
- Added `examples/wordpress-break-prefixer.php` to model WordPress query pagination and column layouts that need legacy WebKit break prefixes without Node/WASM.

## Verification

- `php -l lanes/lightningcss/src/TransitionPrefixer.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-break-prefixer.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php` -> `1 test files, 664 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 4407 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-break-prefixer.php` -> exits 0 and prints the expected legacy WebKit and modern outputs.
- `git diff --check -- lanes/lightningcss` -> clean.

## Coverage And Status

- Focused `TransitionPrefixerTest.php`: accepted status had 647 assertions for this file after the previous full-lane total; this slice adds 17 assertions, reaching 664.
- Full LightningCSS lane: `4390 -> 4407` assertions.
- Conservative mapped coverage: `2117 -> 2123 / 3532` for six upstream browser boundary windows in `Feature::BreakBefore | Feature::BreakAfter | Feature::BreakInside`.

## Non-Overlap

This does not repeat accepted `box-decoration-break`, text compatibility, text-decoration, background-size/origin, background-clip, border-image, box-sizing, object-fit/object-position, display/flex, transform, logical inset/border, mask, filter/backdrop-filter, print-color-adjust, UI, keyframes, image-set, media range/resolution, CSSOM/source-map/bundler/CSS Modules, property-value, or custom at-rule clusters. The stale May 25 `CustomMediaTransformer` rework note was inspected and is unrelated to this target-prefixing slice.

## Dependency Closure

No new support component is needed. This reuses the lane-local native target-version encoder, declaration parser/minifier, and generic vendor-prefixed declaration group helper.
