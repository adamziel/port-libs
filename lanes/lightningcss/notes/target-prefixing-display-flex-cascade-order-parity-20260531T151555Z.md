# LightningCSS Display Flex Cascade-Order Target Prefix Parity

Micro-slice: `lightningcss-target-prefixing-browser-boundary-parity-20260531T151555Z`

Accepted base: `4678f572bda3b3437f0480f42476c787d671be75`

## Upstream Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted pristine reads:
  - `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/lib.rs | sed -n '15650,15815p'`
  - `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/prefixes.rs | sed -n '704,770p;2240,2275p'`
- Mapped the two remaining `src/lib.rs::test_display` `prefix_test` cascade-order helper cases:
  - `display:flex` followed by `display:-webkit-box` emits only the later authored WebKit box fallback for legacy targets.
  - a modern target with `display:-webkit-box; display:flex; display:-moz-box; display:-webkit-flex; display:-ms-flexbox` keeps the prefixed suffix and does not restore the stale standard `display:flex`.

## Native PHP Delta

- `TransitionPrefixer` now computes the last standard and last flex-display declaration per `flex` / `inline-flex` display group before inserting target-required display aliases.
- Prefix aliases are generated only when the final flex-display cascade entry is the standard value.
- If a later authored prefixed display fallback exists, earlier standard/prefixed entries are dropped and the later authored suffix is preserved, matching upstream cascade ordering.
- `wordpress-flex-display-prefixer.php` now includes block/navigation display fallback order self-test coverage.
- Conservative mapped coverage moves from `1258 / 3532` to `1260 / 3532`.

## Verification

- `php -l lanes/lightningcss/src/TransitionPrefixer.php`
  - `No syntax errors detected in lanes/lightningcss/src/TransitionPrefixer.php`
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `No syntax errors detected in lanes/lightningcss/tests/TransitionPrefixerTest.php`
- `php -l lanes/lightningcss/examples/wordpress-flex-display-prefixer.php`
  - `No syntax errors detected in lanes/lightningcss/examples/wordpress-flex-display-prefixer.php`
- `php -r 'json_decode(file_get_contents("lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json"), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, json_last_error_msg() . PHP_EOL); exit(1); } echo "OK\n";'`
  - `OK`
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `1 test files, 302 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 1831 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-flex-display-prefixer.php --self-test`
  - `OK`

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted legacy text/sticky prefixes, UI prefixes, print-color-adjust, image-set, keyframes, light-dark, media range/resolution, flex longhand/box-alignment prefixes, logical inset target fallbacks, CSSOM, grid, source-map, bundle/CSS Modules, or custom at-rule visitor/parser clusters. It closes the two display-flex cascade-order cases explicitly left by the earlier display/flex target-prefixing notes.

## Dependency Closure

No new support component is needed. The implementation reuses the native browser target-version encoder, declaration scanner, display-value alias mapping, existing minifier normalization, and vendor-prefix rewriting machinery.
