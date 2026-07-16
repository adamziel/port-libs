# Target Prefixing Browser Boundary Parity - Text Emphasis Position

## Scope

- Lane: `lightningcss`
- Micro-slice: `lightningcss-target-prefixing-browser-boundary-parity-20260601T065326Z`
- Base accepted HEAD: `cc9294ac19877407e3f202dbdfd54b6a9a8fb67d`
- Upstream source truth: pinned `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`

## Upstream Evidence

- `src/properties/text.rs` emits `Feature::TextEmphasisPosition` prefixes, then clears the prefix when the parsed horizontal side is not `Right`.
- `src/prefixes.rs` maps `Feature::TextEmphasisPosition` to WebKit for Android 4.4-4.4.3, Chrome 25-98, Edge 79-98, Opera 15-85, Safari 6.1-7, and Samsung 4-17.
- Native addon spot check at the pinned cache:
  - `right over` and `over right` serialize as `over` and get `-webkit-` for Chrome 98.
  - `left over` and `over left` serialize as `over left` and do not get `-webkit-`, even when the target otherwise needs WebKit text-emphasis prefixes.
  - Chrome 99 and Safari 8 drop the `text-emphasis-position` prefix for right-side positions.

## Implementation

- `CssMinifier::minifyTextEmphasisPosition()` now canonicalizes valid `vertical horizontal` and `horizontal vertical` orders instead of only trimming a trailing `right`.
- `TransitionPrefixer::normalizeTextEmphasisPosition()` now uses the same canonicalization before WebKit prefix gating.
- `TransitionPrefixer::textEmphasisPositionNeedsWebkitPrefix()` now suppresses the WebKit prefix for both `over left` and source-order `left over` / `left under` inputs.
- `wordpress-text-emphasis-prefixer.php` now self-tests Chrome 98/99 target boundaries for WordPress annotation styles.

## Verification

- `php -l lanes/lightningcss/src/CssMinifier.php lanes/lightningcss/src/TransitionPrefixer.php lanes/lightningcss/tests/CssMinifierTest.php lanes/lightningcss/tests/TransitionPrefixerTest.php lanes/lightningcss/examples/wordpress-text-emphasis-prefixer.php`
  - Result: all five files reported no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - Result: `2 test files, 2883 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests`
  - Result: `13 test files, 6563 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-text-emphasis-prefixer.php --self-test`
  - Result: exits 0 and prints the Chrome 98 and Chrome 99 expected CSS.
- `git diff --check -- lanes/lightningcss`
  - Result: no whitespace errors.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP CSS minifier, target prefixer, and WordPress example harness.

## Non-Overlap

This slice does not repeat accepted placeholder pseudo-element, selector pseudo, mask, print-color-adjust, text-decoration, touch-action, text-orientation, box-decoration-break, box-shadow, source-map, CSS Modules, media-query, custom at-rule, or bundle/import graph clusters. It only fixes text-emphasis-position value-order canonicalization and browser-boundary WebKit prefix gating.
