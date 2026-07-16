# LightningCSS Target Prefixing Browser Boundary Parity

Micro-slice: `lightningcss-target-prefixing-browser-boundary-parity-20260531T131403Z`

Accepted base: `04e2559bf286c590dfe8ddc3424be7754eff88e2`

Upstream source truth:

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted pristine reads:
  - `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/lib.rs | sed -n '16000,16240p'`
  - `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/lib.rs | sed -n '17180,17380p'`
  - `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/prefixes.rs | nl -ba | sed -n '960,1015p;1135,1165p;1325,1368p;1416,1435p;1518,1580p;1630,1636p'`
- Mapped 15 upstream `prefix_test` helper cases from `test_hyphens`, `test_tab_size`, `test_text_align_last`, `test_text_size_adjust`, `test_break`, `test_position`, and `test_overflow`.

Native PHP delta:

- `TransitionPrefixer` now emits or removes target-dependent prefixes for `text-size-adjust`, `hyphens`, `tab-size`, `text-align-last`, `text-overflow`, `box-decoration-break`, and `position: sticky`.
- Added exact browser-boundary coverage from upstream `src/prefixes.rs`, including iOS Safari 5 text-size-adjust, Safari 16.5 hyphens, Firefox 90 tab-size, Firefox 48 text-align-last, Opera 12 text-overflow, Chrome 129 box-decoration-break, and Safari 12.1 sticky boundaries.
- Added `wordpress-text-compat-prefixer.php` for editor/mobile text compatibility CSS without Node.

Evidence:

- `php -l lanes/lightningcss/src/TransitionPrefixer.php && php -l lanes/lightningcss/tests/TransitionPrefixerTest.php && php -l lanes/lightningcss/examples/wordpress-text-compat-prefixer.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php` -> `1 test files, 221 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 1368 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-text-compat-prefixer.php` -> exits 0.

Non-overlap:

- Does not repeat accepted `user-select` / `appearance` UI prefix boundaries, `print-color-adjust`, `image-set`, `box-shadow`, `text-emphasis`, `text-decoration`, keyframes, `light-dark`, or media range fallback slices.
- Leaves display/flex and logical position/inset prefix clusters for a separate target-prefixing handoff.

Dependency closure:

- No new support component is needed. This reuses the native `TransitionPrefixer` target-version encoder, declaration scanner, and vendor-prefix group rewriting.
