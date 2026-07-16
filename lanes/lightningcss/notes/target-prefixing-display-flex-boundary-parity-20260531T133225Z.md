# LightningCSS Display Flex Target Prefix Boundary Parity

Micro-slice: `lightningcss-target-prefixing-browser-boundary-parity-20260531T133225Z`

Accepted base: `39b47e3d7563ca406403433b251e48bb5e25f850`

Upstream source truth:

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted pristine reads:
  - `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/lib.rs | sed -n '15650,15815p'`
  - `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/prefixes.rs | sed -n '704,770p'`
  - `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/prefixes.rs | sed -n '2240,2275p'`
- Mapped 5 of the 7 upstream `src/lib.rs::test_display` `prefix_test` helper cases: old-target `display:flex`, old-target `display:inline-flex`, existing old WebKit box before unprefixed flex, modern stale flex-alias removal, and modern stale inline-flex-alias removal.
- Added browser-boundary checks from `src/prefixes.rs` for Chrome 20/21/28/29, Safari 6/7/8/9, Firefox 21/22, and IE 10/11.

Native PHP delta:

- `TransitionPrefixer` now emits target-required display aliases for `display:flex` and `display:inline-flex`: old WebKit box values, Mozilla box values, WebKit flex values, and IE flexbox values.
- Modern targets strip stale display flex aliases when a matching unprefixed declaration is present.
- Added `wordpress-flex-display-prefixer.php` for block columns, navigation containers, and migrated button groups without Node.

Evidence:

- `php -l lanes/lightningcss/src/TransitionPrefixer.php && php -l lanes/lightningcss/tests/TransitionPrefixerTest.php && php -l lanes/lightningcss/examples/wordpress-flex-display-prefixer.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php` -> `1 test files, 246 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 1542 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-flex-display-prefixer.php --self-test` -> `OK`.
- `git diff --check -- lanes/lightningcss` -> no whitespace errors.

Non-overlap:

- Does not repeat accepted `user-select` / `appearance` UI prefix boundaries, legacy text/sticky prefixing, `print-color-adjust`, `image-set`, `box-shadow`, `text-emphasis`, `text-decoration`, keyframes, `light-dark`, media range/resolution fallback, or CSSOM inset/shorthand behavior.
- Leaves flex longhand and box-alignment prefix rewriting, logical position/inset target prefixes, and the two remaining display-flex cascade-order edge cases for later target-prefixing handoffs.

Dependency closure:

- No new support component is needed. This reuses the native `TransitionPrefixer` target-version encoder, declaration scanner, display-value alias mapping, and vendor-prefix group rewriting.
