# LightningCSS Length Target Fallback Browser Boundaries

Micro-slice: `lightningcss-target-prefixing-browser-boundary-parity-20260531T231006Z`

Base accepted HEAD: `b77f76b33ac877becd8fb58514949f334f0fbc0d`

## Source Truth

Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.

This slice ports the `src/lib.rs::test_length` `prefix_test` loop over 10 length properties:
`margin-right`, `margin`, `padding-right`, `padding`, `width`, `height`, `min-height`, `max-height`, `line-height`, and `border-radius`.

The upstream cases keep `22px` fallbacks for Safari 10 before `max(4%, 22px)`, prune them for Safari 14, keep `22px` fallbacks for Safari 14 before `max(2cqw, 22px)`, and prune them for Safari 16.

Source boundary confirmation came from pristine `git show` reads of `src/lib.rs::test_length` and `src/targets.rs` / `src/compat.rs` feature data at the pinned commit:
`MinFunction | MaxFunction` is supported at Safari 11.1 / iOS Safari 11.3 and `ContainerQueryLengthUnits` at Safari/iOS Safari 16.

## Implementation

`TransitionPrefixer` now removes a previous same-property non-important fallback only when the later value is target-supported:

- `min()` / `max()` fallback pruning requires all selected targets to meet the upstream min/max function boundary.
- `cq*` length-unit fallback pruning requires all selected targets to meet the upstream container-query length-unit boundary.
- `var()`-backed fallback pairs are preserved conservatively.

The PHP coverage maps 40 upstream helper cases: 10 properties x 4 browser-boundary assertions.

## Evidence

Red-first check before implementation: the focused `TransitionPrefixerTest.php` run failed the new cqw Safari 14 fallback assertion, reporting `1 test files, 720 assertions, 1 failures`.

Final verification:

- `php -l lanes/lightningcss/src/TransitionPrefixer.php` passed.
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php` passed.
- `php -l lanes/lightningcss/examples/wordpress-fluid-length-target-fallback.php` passed.
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php` passed: `1 test files, 757 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` passed: `13 test files, 4830 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-fluid-length-target-fallback.php --self-test` passed.

Status delta: `phpPass` moves `4790 -> 4830`; conservative mapped coverage moves `2195 / 3532 -> 2235 / 3532`.

## Non-Overlap

This slice does not repeat accepted selector target-prefixing, media-query conjunction fallback, source-map generated-column overflow, font target fallback, alpha-color fallback, or logical spacing target fallback clusters. It is limited to length property fallback pruning for upstream `max()` and container-query length unit browser boundaries.

## Dependency Closure

No new support component is needed. The slice reuses lane-local `TransitionPrefixer` target-version routing, declaration scanning, and existing value minification output.
