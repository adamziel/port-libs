# Target Prefixing Browser Boundary Parity - Mask Safari/iOS 15.2

Slice: `lightningcss-target-prefixing-browser-boundary-parity-20260601T045403Z`

Base accepted HEAD: `e817cf28276645ddc830afdbe15659359b9f073a`

## Upstream Source Truth

Pinned manifest commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.

Targeted upstream read:

```sh
git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/prefixes.rs | sed -n '1436,1483p'
```

`Feature::MaskClip | MaskComposite | MaskImage | MaskOrigin | MaskRepeat | MaskBorder* | Mask | MaskPosition | MaskSize` returns `VendorPrefix::WebKit` for Safari encoded versions `262144..983552` and iOS Safari encoded versions `197120..983552`. `983552` is version `15.2`, so Safari/iOS `15.1` and `15.2` still need WebKit mask aliases and `15.3` does not.

Red-first focused probe before the implementation:

```sh
php -r 'require "tools/bootstrap.php"; $p = new PortLibs\LightningCSS\TransitionPrefixer(); foreach (["15.0", "15.1", "15.2", "15.3"] as $v) { echo "safari $v: ", $p->prefixForTargets(".foo { mask-image: linear-gradient(red, green); }", ["safari" => $v]), PHP_EOL; } foreach (["15.0", "15.1", "15.2", "15.3"] as $v) { echo "ios $v: ", $p->prefixForTargets(".foo { mask-image: linear-gradient(red, green); }", ["ios_saf" => $v]), PHP_EOL; }'
```

Before this patch, Safari/iOS `15.1` and `15.2` emitted only `.foo{mask-image:linear-gradient(red,green)}` and missed upstream's required `-webkit-mask-image` fallback.

## Implementation

- `TransitionPrefixer::targetOptions()` now carries `maskNeedsWebkit` through Safari and iOS Safari `15.2`.
- `TransitionPrefixerTest.php` adds Safari `15.2`/`15.3` and iOS Safari `15.2`/`15.3` assertions for mask-image prefix insertion and stale-prefix cleanup.
- `wordpress-mask-target-boundaries.php` now covers Chrome `119`/`120` and Safari/iOS `15.2`/`15.3` block cover mask output.

## Verification

```sh
php -l lanes/lightningcss/src/TransitionPrefixer.php
php -l lanes/lightningcss/tests/TransitionPrefixerTest.php
php -l lanes/lightningcss/examples/wordpress-mask-target-boundaries.php
php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php
php lanes/lightningcss/examples/wordpress-mask-target-boundaries.php
php tools/run-tests.php lanes/lightningcss/tests
git diff --check -- lanes/lightningcss
```

Results:

- PHP lint passed for all changed PHP files.
- Focused `TransitionPrefixerTest.php`: `1 test files, 984 assertions, 0 failures`.
- WordPress mask target-boundary example smoke passed.
- Full LightningCSS lane: `13 test files, 6063 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` passed.

## Coverage And Handoff Notes

Focused `TransitionPrefixerTest.php` moved from `980` to `984` assertions. Full lane `phpPass` moves from `6059` to `6063`. Conservative mapped coverage remains `2345 / 3532` because this deepens the already represented mask target-prefix browser-boundary cluster rather than adding a new upstream denominator row.

Dependency closure: no new support component is needed. This reuses the existing native PHP `TransitionPrefixer` target table, target-version encoder, declaration scanner, mask prefix machinery, and WordPress mask example.

Non-overlap: this does not replay the stale May 25 `CustomMediaTransformer` rework note; that note targets an older import-tail conflict on another base. This slice only corrects the mask WebKit Safari/iOS 15.2 upper browser boundary and avoids the recently accepted touch-action/text-orientation, appearance, fullscreen, cursor, print-color-adjust, placeholder, animation timeline, transform, selector, image-set, text-decoration, media-query, source-map, CSS Modules, bundle/import graph, CSSOM, and custom at-rule clusters.
