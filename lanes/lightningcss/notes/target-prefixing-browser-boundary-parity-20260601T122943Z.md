# Target Prefixing Browser Boundary Parity - 2026-06-01 12:29 UTC

Micro-slice: `lightningcss-target-prefixing-browser-boundary-parity-20260601T122943Z`

## Scope

This patch tightens the LightningCSS target-prefix boundary for `user-select` on
iOS Safari. The pinned upstream source at
`parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`
marks `Feature::UserSelect` as needing a WebKit prefix for `ios_saf` only when
the encoded browser version is at least `197120`, which decodes to `3.2.0`
with the lane's `(major << 16) | (minor << 8) | patch` encoder.

Red-first probe before the fix:

```bash
php -r 'require "tools/bootstrap.php"; $p=new PortLibs\LightningCSS\TransitionPrefixer(); echo $p->prefixForTargets(".foo { user-select: none; }", ["ios_saf" => "3.1"]), PHP_EOL; echo $p->prefixForTargets(".foo { user-select: none; }", ["ios_saf" => "3.2"]), PHP_EOL;'
```

Before this patch both iOS Safari 3.1 and 3.2 emitted
`-webkit-user-select`. After the patch, 3.1 remains unprefixed and 3.2 emits
the WebKit-prefixed fallback.

## Status Delta

- Focused PHP assertions: `1273 -> 1275` in `TransitionPrefixerTest.php`.
- `lane-status.json` `phpPass`: `7771 -> 7773`.
- Mapped upstream denominator remains `2392 / 3532`; this deepens an existing
  target-prefixing browser-boundary parity cluster and does not add a new
  manifest unit.

## Dependency Closure

No new support component is needed. The patch reuses the existing target
normalization/version-boundary helpers, declaration prefix pipeline, and
WordPress example smoke harness.

## Non-Overlap

This avoids the already accepted target-prefixing browser-boundary work for
intrinsic sizing, display grid, appearance, clip-path, selector handling,
source maps, CSS Modules, bundle/import graphs, custom at-rules, media queries,
and property/value clusters. It owns only the upstream `user-select` iOS Safari
3.2 lower-boundary correction.

## Verification

Passed in this worktree:

- `php -l lanes/lightningcss/src/TransitionPrefixer.php`
  - `No syntax errors detected in lanes/lightningcss/src/TransitionPrefixer.php`
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `No syntax errors detected in lanes/lightningcss/tests/TransitionPrefixerTest.php`
- `php -l lanes/lightningcss/examples/wordpress-target-prefix-boundaries.php`
  - `No syntax errors detected in lanes/lightningcss/examples/wordpress-target-prefix-boundaries.php`
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `1 test files, 1275 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-target-prefix-boundaries.php`
  - passed; final two smoke outputs were:
    - `.wp-block-navigation .wp-block-navigation__responsive-container button{user-select:none}`
    - `.wp-block-navigation .wp-block-navigation__responsive-container button{-webkit-user-select:none;user-select:none}`
- `php -r 'json_decode(file_get_contents("lanes/lightningcss/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json OK\n";'`
  - `lane-status json OK`
- `git diff --check -- lanes/lightningcss`
  - passed with no output

Root harness: not run - isolated micro-slice.
