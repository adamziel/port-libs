# Target Prefixing Browser Boundary Parity - Touch Action And Text Orientation

Slice: `lightningcss-target-prefixing-browser-boundary-parity-20260601T041800Z`

Base accepted HEAD: `a514b852099d3beeb2c984bc19ea1aeae13dfd49`

## Upstream Source Truth

Pinned manifest commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.

Targeted upstream reads:

```sh
sed -n '1360,1382p;2140,2160p' /home/claude/port-libs/.upstream-cache/lightningcss/src/prefixes.rs
```

`Feature::TouchAction` returns `VendorPrefix::Ms` only for IE encoded version `655360`, which is IE 10.0.

`Feature::TextOrientation` returns `VendorPrefix::WebKit` only for Safari encoded versions `655616..852224`, which is Safari 10.1 through 13.1. There is no iOS Safari entry for this feature.

Red-first focused probes before the implementation showed:

- IE 10 `touch-action` serialized as `.foo{touch-action:manipulation}`, omitting the upstream-required `-ms-touch-action`.
- Safari 10.1 and 13.1 `text-orientation` serialized as `.foo{text-orientation:upright}`, omitting the upstream-required `-webkit-text-orientation`.
- Stale `-ms-touch-action` and `-webkit-text-orientation` declarations were preserved for modern targets when an unprefixed equivalent existed.

## Implementation

- `TransitionPrefixer::targetOptions()` now exposes the upstream browser boundaries for IE 10 `touch-action` and Safari 10.1-13.1 `text-orientation`.
- `rewriteTextCompatibilityPrefixEntries()` now inserts or removes the `-ms-touch-action` and `-webkit-text-orientation` declaration groups with the existing stale-prefix cleanup path.
- `TransitionPrefixerTest.php` adds exact lower-bound, upper-bound, modern-boundary, no-iOS, Edge, and stale-prefix assertions.
- `wordpress-touch-orientation-prefixer.php` covers WordPress swipe gallery and vertical-caption block styles for IE 10/11 and Safari 10.1/13.2 delivery.

## Verification

```sh
php -l lanes/lightningcss/src/TransitionPrefixer.php
php -l lanes/lightningcss/tests/TransitionPrefixerTest.php
php -l lanes/lightningcss/examples/wordpress-touch-orientation-prefixer.php
php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php
php lanes/lightningcss/examples/wordpress-touch-orientation-prefixer.php
php tools/run-tests.php lanes/lightningcss/tests
git diff --check -- lanes/lightningcss
```

Results:

- PHP lint passed for all changed PHP files.
- Focused `TransitionPrefixerTest.php`: `1 test files, 980 assertions, 0 failures`.
- WordPress touch/orientation example smoke passed.
- Full LightningCSS lane: `13 test files, 5955 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` passed.

## Coverage And Handoff Notes

Focused `TransitionPrefixerTest.php` moved from `970` to `980` assertions. Full lane `phpPass` moves from `5945` to `5955`. Conservative mapped coverage remains `2336 / 3532` because this deepens the already represented target-prefix browser-boundary cluster rather than adding a new upstream helper denominator row.

Dependency closure: no new support component is needed. This reuses the existing native PHP target-version routing and declaration-prefix group writer.

Non-overlap: the stale May 25 `CustomMediaTransformer` rework note targets an older import-tail conflict and is unrelated to this target-prefixing slice. This patch avoids the recently accepted appearance, fullscreen, cursor, print-color-adjust, placeholder, animation timeline, transform, selector, mask, image-set, text-decoration, media-query, source-map, CSS Modules, bundle/import graph, CSSOM, and custom at-rule clusters.
