# LightningCSS Target Prefixing Browser Boundary Parity

Micro-slice: `lightningcss-target-prefixing-browser-boundary-parity-20260601T051231Z`

Accepted base: `b6e9f0ce57867f58750508c9437be4ae03b4d9e1`

Upstream source truth:

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted pristine read:
  - `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/prefixes.rs | sed -n '1,260p'`
- Native addon oracle:
  - Directly required `/home/claude/port-libs/.upstream-cache/lightningcss/lightningcss.linux-x64-gnu.node`.
  - Confirmed `box-shadow` emits `-moz-box-shadow` for Firefox 3.5 and 3.6, drops it for Firefox 3.7 and later, emits WebKit plus Mozilla prefixes together for mixed Safari 5 / Firefox 3.6 targets, and starts iOS Safari WebKit `box-shadow` at 3.2 rather than 3.1.

Native PHP delta:

- `TransitionPrefixer` now maps `Feature::BoxShadow` Mozilla target boundaries for Firefox 3.5-3.6.
- Corrected the iOS Safari lower WebKit `box-shadow` boundary from 3.0 to 3.2 to match upstream `src/prefixes.rs` and native addon output.
- Matching stale `-webkit-box-shadow` / `-moz-box-shadow` declarations are now removed per vendor when the requested targets do not need that vendor prefix, while preserving non-matching legacy declarations.
- Updated the WordPress box-shadow prefix smoke to cover Firefox 3.6, mixed legacy WebKit/Mozilla targets, iOS Safari 3.1/3.2, and modern cleanup.

Evidence:

- `php -l lanes/lightningcss/src/TransitionPrefixer.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-box-shadow-prefixer.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php` -> `1 test files, 1006 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-box-shadow-prefixer.php --self-test` -> exits 0.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 6134 assertions, 0 failures`.

Non-overlap:

- This deepens the already represented `box-shadow` target-prefix cluster only.
- It does not repeat accepted `image-set`, `backdrop-filter`, `print-color-adjust`, `text-emphasis`, `text-decoration`, keyframes, `light-dark`, UI prefix, transition, fullscreen selector, text-orientation, touch-action, supports declaration, or bundle/CSS Modules/custom-at-rule work.

Dependency closure:

- No new support component is needed. This reuses the native `TransitionPrefixer` target-version encoder, declaration parser, advanced-color fallback builder, and existing legacy box-shadow declaration rewriting path.
