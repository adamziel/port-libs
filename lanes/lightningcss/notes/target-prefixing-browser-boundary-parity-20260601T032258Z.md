# Target Prefixing Browser Boundary Parity - Fullscreen Selectors

Slice: `lightningcss-target-prefixing-browser-boundary-parity-20260601T032258Z`
Base accepted HEAD: `639880c48c54d40c3ed0188758af6aee8d8d2712`

## Upstream Source Truth

Pinned manifest commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.

Targeted upstream read:

```sh
sed -n '1003,1033p' /home/claude/port-libs/.upstream-cache/lightningcss/src/prefixes.rs
```

`Feature::PseudoClassFullscreen` keeps WebKit fullscreen selector prefixes for:

- Chrome `15..70`
- Opera `15..63`
- Safari `5.1..16.3`
- Samsung Internet `4..9.2`

The PHP port already covered the Chrome/Opera range and the Firefox/IE variants, but its WebKit browser target boundary stopped at Safari 16.0 and Samsung 9.0. This slice extends only the Safari and Samsung upper bounds.

## Implementation

- `TransitionPrefixer::targetOptions()` now treats Safari 16.1, 16.2, and 16.3 as requiring `:-webkit-full-screen`, and stops at Safari 16.4.
- `TransitionPrefixer::targetOptions()` now treats Samsung Internet 9.1 and 9.2 as requiring `:-webkit-full-screen`, and stops at Samsung 9.3.
- `TransitionPrefixerTest.php` adds the exact Safari 16.3/16.4 and Samsung 9.2/9.3 browser-boundary assertions.
- `wordpress-selector-target-prefixer.php` now includes a block-cover fullscreen selector smoke.

## Verification

```sh
php -l lanes/lightningcss/src/TransitionPrefixer.php
php -l lanes/lightningcss/tests/TransitionPrefixerTest.php
php -l lanes/lightningcss/examples/wordpress-selector-target-prefixer.php
php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php
php lanes/lightningcss/examples/wordpress-selector-target-prefixer.php --self-test
php tools/run-tests.php lanes/lightningcss/tests
git diff --check -- lanes/lightningcss
```

Results:

- PHP lint passed for all changed PHP files.
- Focused `TransitionPrefixerTest.php`: `1 test files, 934 assertions, 0 failures`.
- Example smoke: `selector target prefixer example self-test passed`.
- Full LightningCSS lane: `13 test files, 5760 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss`: passed.

## Coverage And Handoff Notes

Conservative mapped coverage remains `2320 / 3532` because this deepens the already represented selector pseudo target-prefix browser-boundary cluster rather than adding a new upstream helper denominator row. `phpPass` moves from `5756` to `5760`.

Dependency closure: no new support component is needed. This reuses the existing PHP target parser, selector variant emitter, and minifier pipeline.

Non-overlap: this does not touch the stale CustomMedia rework note in `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-lightningcss-current-rebase-20260525T053931Z-02383337.needs-lane-rework.md`; that note names an older custom-media import-tail conflict and is unrelated to this target-prefixing boundary slice. It also avoids the recently accepted bundle/import graph, CSS Modules, custom at-rule, source-map, media-query, CSSOM, print-color-adjust, placeholder, cursor, animation timeline, and property-value clusters.
