# Target Prefixing Browser Boundary Parity - 2026-06-01 08:08Z

## Slice

Implemented the bounded LightningCSS target-prefixing edge where selector-list
browser-boundary isolation must compose with logical property fallback emission.
The patch keeps work under `lanes/lightningcss/**`.

## Source Truth

- Upstream commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/lib.rs` focus-visible prefix tests show `:hover, :focus-visible` split
  for Safari 13, wrapped in `:is(...)` for Safari 14, and split before logical
  `margin-inline-start` fallback rules.
- `src/compat.rs` `Feature::FocusVisible` uses Safari/iOS cutoff `984064`,
  which is Safari 15.4, not 15.1.

## Behavior Delta

- `TransitionPrefixer` now applies selector target variants before returning
  logical border, spacing, inset, and text-align fallback rules, so unsupported
  selector lists are split or wrapped before fallback declarations serialize.
- Safari/iOS `:focus-visible` selector-list fallback now remains active through
  15.3 and stops at 15.4, matching upstream compatibility data.
- `wordpress-selector-target-prefixer.php` now self-tests a WordPress navigation
  focus-visible selector list with logical `padding-inline` fallback.

## Verification

- `php -l lanes/lightningcss/src/TransitionPrefixer.php`
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php`
- `php -l lanes/lightningcss/examples/wordpress-selector-target-prefixer.php`
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `1 test files, 1114 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 6880 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-selector-target-prefixer.php --self-test`
  - `selector target prefixer example self-test passed`

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP
`TransitionPrefixer` selector parsing, specificity checks, and logical fallback
serializers. Full upstream Rust/Node/WASM runners were not run for this isolated
micro-slice.

## Non-overlap

This does not repeat the previous focus selector-list slice by itself. It fixes
the remaining composition point where logical fallback rules returned before the
selector-list isolation pass could apply.
