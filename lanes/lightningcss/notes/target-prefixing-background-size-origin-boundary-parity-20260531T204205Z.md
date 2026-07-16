# LightningCSS Target Prefixing: Background Size/Origin Boundaries

Slice: `lightningcss-target-prefixing-browser-boundary-parity-20260531T204205Z`

Source truth:

```bash
git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/prefixes.rs | nl -ba | sed -n '748,790p'
```

Pinned upstream `src/prefixes.rs` maps `Feature::BackgroundOrigin | Feature::BackgroundSize` to these vendor-prefix windows:

- Android `>= 2.1` and `<= 2.3`: `-webkit-`
- Firefox `<= 3.6`: `-moz-`
- Opera `<= 10`: `-o-`

Red-first probe on this accepted worktree showed no prefixed declarations for `.foo { background-size: cover; background-origin: content-box; }` when targeting Firefox 3.6 or Opera 10.

Implementation:

- `TransitionPrefixer` now carries target flags for the Android, Firefox, and Opera boundary windows above.
- `background-size` and `background-origin` declarations are rewritten through the existing vendor-prefixed declaration-group path, so required prefixes are inserted before the unprefixed declaration and stale `-webkit-`, `-moz-`, or `-o-` declarations are removed for modern targets.
- `wordpress-background-size-origin-prefixer.php` models WordPress cover block background delivery for old Android, Firefox, Opera, and modern target sets without Node/WASM.

Verification:

- `php -l lanes/lightningcss/src/TransitionPrefixer.php`
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php`
- `php -l lanes/lightningcss/examples/wordpress-background-size-origin-prefixer.php`
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php` => `1 test files, 635 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 4191 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-background-size-origin-prefixer.php`
- `git diff --check -- lanes/lightningcss`

Coverage and status:

- Focused `TransitionPrefixerTest.php`: `621 -> 635` assertions.
- Full LightningCSS lane: `4181 -> 4191` assertions.
- Conservative mapped coverage: `2078 -> 2084 / 3532` for six browser/property boundary checks.

Dependency closure:

- No new support component is needed. This reuses native `TransitionPrefixer` target-version routing, declaration parsing/minification, and vendor-prefixed declaration-group rewriting.

Non-overlap:

- This does not repeat accepted background-clip, filter/backdrop-filter, object-fit/object-position, border-image, box-sizing, display/flex, transform, logical inset/border, text-decoration, print-color-adjust, UI, keyframes, image-set, mask, CSSOM/source-map/bundler/CSS Modules, or import layer-name clusters.
