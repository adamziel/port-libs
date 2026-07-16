# LightningCSS Target Prefixing: Place Alignment Browser Boundaries

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/compat.rs` marks `Feature::PlaceSelf | Feature::PlaceItems` incompatible below Chrome 59, Edge 79, Firefox 45, Opera 43, Safari/iOS Safari 11, Samsung 7, Android 59, and for all IE.
- `src/compat.rs` marks `Feature::PlaceContent` incompatible at the same browser boundaries except Safari/iOS Safari, where the cutoff is 9.
- `src/properties/align.rs` expands incompatible `place-*` shorthands into the corresponding align/justify longhands before standard longhand serialization.

## Red-First Gap

Before this slice, native PHP only expanded `place-content`, `place-self`, and `place-items` when old flex target prefixes were required. Upstream also expands these shorthands for non-flex-prefix boundaries such as Chrome 58, Firefox 44, Safari 10 for `place-self`/`place-items`, Edge 78, Android 58, Samsung 6, and IE 11.

Focused probes before the change kept the original shorthand for those targets, while the pinned upstream native binding emitted longhands such as:

- Chrome 58 `place-self: center flex-end` -> `align-self:center;justify-self:flex-end`
- Safari 10 `place-items: center flex-end` -> `align-items:center;justify-items:flex-end`
- IE 11 `place-content: center space-between` -> `align-content:center;justify-content:space-between`

## Implementation

- Added separate target-boundary flags for `place-content`, `place-self`, and `place-items` in `TransitionPrefixer`.
- Reused the existing place-shorthand expansion helpers so old flex-prefix targets still emit the same `-webkit-`, `-moz-`, and `-ms-` fallbacks where upstream expects them.
- Added `examples/wordpress-place-alignment-prefixer.php` for build-free WordPress query/grid block styles targeting Android 58, Safari 10, and modern Chrome.

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `1 test files, 1258 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-place-alignment-prefixer.php --self-test`
  - `OK`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 7594 assertions, 0 failures`

## Non-Overlap

This slice does not repeat accepted display-grid `-ms-` value prefixing, old flex longhand prefix output, CSSOM place-alignment read/write behavior, print-color-adjust boundaries, image-rendering, mask, selector, media-query, source-map, bundle/import graph, CSS Modules, or custom at-rule work. It specifically fills the missing non-flex-prefix browser compatibility expansion for `place-*` shorthands.

## Dependency Closure

No new support component is needed. The existing PHP `TransitionPrefixer` target-normalization and declaration-rewrite helpers are reused.
