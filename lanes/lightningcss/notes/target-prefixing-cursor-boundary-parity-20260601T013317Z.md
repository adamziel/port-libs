# Target Prefixing Cursor Boundary Parity

Micro-slice: `lightningcss-target-prefixing-browser-boundary-parity-20260601T013317Z`
Base accepted HEAD: `e0cca2a185669ab1c0c1e83b7ad9894e29901028`

## Source Truth

Pinned upstream commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.

Targeted source read:

```bash
git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/prefixes.rs | nl -ba | sed -n '1312,1355p'
```

The mapped upstream cluster is `src/prefixes.rs` `Feature::ZoomIn`, `Feature::ZoomOut`, `Feature::Grab`, and `Feature::Grabbing`. Upstream emits WebKit cursor value fallbacks for `zoom-in` / `zoom-out` across Chrome 4-36, Opera 15-23, and Safari 3.1-8, plus Mozilla fallbacks for Firefox 2-23. Upstream emits WebKit cursor value fallbacks for `grab` / `grabbing` across Chrome 4-67, Opera 15-54, and Safari 3.1-10, plus Mozilla fallbacks for Firefox 2-25.

## Implementation

`TransitionPrefixer` now rewrites `cursor` declaration values for those four cursor keywords. It inserts `-webkit-` and `-moz-` cursor value fallbacks before the unprefixed declaration when the selected target boundary needs them, preserves existing needed prefixed values without duplication, removes stale prefixed values only when the equivalent unprefixed cursor value is present for modern targets, and carries URL cursor fallback lists through to the prefixed value.

Red-first probe before implementation:

```bash
php <<'PHP'
<?php
require 'tools/bootstrap.php';
$prefixer = new PortLibs\LightningCSS\TransitionPrefixer();
echo $prefixer->prefixForTargets('.foo { cursor: grab; cursor: grabbing; cursor: zoom-in; cursor: zoom-out; }', [
    'chrome' => 35,
    'firefox' => 23,
    'safari' => 8,
    'opera' => 20,
]), "\n";
PHP
# .foo{cursor:grab;cursor:grabbing;cursor:zoom-in;cursor:zoom-out}
```

## Evidence

Focused verification:

```bash
php -l lanes/lightningcss/src/TransitionPrefixer.php
# No syntax errors detected in lanes/lightningcss/src/TransitionPrefixer.php

php -l lanes/lightningcss/tests/TransitionPrefixerTest.php
# No syntax errors detected in lanes/lightningcss/tests/TransitionPrefixerTest.php

php -l lanes/lightningcss/examples/wordpress-cursor-target-prefixer.php
# No syntax errors detected in lanes/lightningcss/examples/wordpress-cursor-target-prefixer.php

php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php
# 1 test files, 883 assertions, 0 failures

php lanes/lightningcss/examples/wordpress-cursor-target-prefixer.php --self-test
# OK

php tools/run-tests.php lanes/lightningcss/tests
# 13 test files, 5408 assertions, 0 failures
```

`git diff --check -- lanes/lightningcss` passes for this handoff.

Focused assertion growth: `TransitionPrefixerTest.php` adds 22 assertions, moving the full LightningCSS PHP lane from 5386 to 5408 assertions. Conservative mapped coverage moves from `2289 / 3532` to `2293 / 3532` for the four upstream cursor-value target-prefix feature rows.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This slice only covers cursor value prefixing for `zoom-in`, `zoom-out`, `grab`, and `grabbing`. It avoids the accepted UI `user-select` / `appearance`, legacy text/sticky, text-decoration, overflow, break, logical-size, selector, placeholder, mask, filter/backdrop-filter, clip-path, display-flex, flex longhand, and animation target-prefixing clusters. The stale `port-lightningcss-current-rebase-20260525T053931Z-02383337.needs-lane-rework.md` note targets a May 25 custom-media import-tail conflict and is unrelated to this cursor boundary slice.

## Dependency Closure

No new support component is needed. The implementation reuses `TransitionPrefixer` target-version routing, declaration parsing, top-level comma splitting, custom-property guards, and declaration serialization.
