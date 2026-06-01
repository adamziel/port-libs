# LightningCSS animation supports-prefix boundary parity

Micro-slice: `lightningcss-target-prefixing-browser-boundary-parity-20260601T195835Z`

## Source truth

- Pinned upstream manifest commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Upstream source: `/home/claude/port-libs/.upstream-cache/lightningcss/src/prefixes.rs` lists `Feature::Animation`, `Feature::AnimationName`, `Feature::AnimationDuration`, `Feature::AnimationDelay`, `Feature::AnimationDirection`, `Feature::AnimationFillMode`, `Feature::AnimationIterationCount`, `Feature::AnimationPlayState`, and `Feature::AnimationTimingFunction` in the same prefix group as `Feature::AtKeyframes`.
- Native addon oracle:

```bash
node - <<'NODE'
const native = require('/home/claude/port-libs/.upstream-cache/lightningcss/lightningcss.linux-x64-gnu.node');
const cases = [
  ['chrome42 shorthand', { chrome: 42 << 16 }, '@supports (animation: spin 1s) { .foo { animation: spin 1s; } }'],
  ['chrome42 name', { chrome: 42 << 16 }, '@supports (animation-name: spin) { .foo { animation-name: spin; } }'],
  ['firefox15 name', { firefox: 15 << 16 }, '@supports (animation-name: spin) { .foo { animation-name: spin; } }'],
  ['opera12 duration', { opera: 12 << 16 }, '@supports (animation-duration: 1s) { .foo { animation-duration: 1s; } }'],
  ['modern stale', { chrome: 43 << 16, firefox: 16 << 16, opera: 13 << 16 }, '@supports ((-webkit-animation: spin 1s) or (-moz-animation: spin 1s) or (-o-animation: spin 1s) or (animation: spin 1s)) { .foo { -webkit-animation: spin 1s; -moz-animation: spin 1s; -o-animation: spin 1s; animation: spin 1s; } }']
];
for (const [name, targets, css] of cases) {
  const result = native.transform({ filename: 'input.css', code: Buffer.from(css), minify: true, targets });
  console.log(name + ': ' + result.code.toString());
}
NODE
```

Observed upstream outputs included WebKit expansion for Chrome 42, Moz expansion for Firefox 15, O expansion for Opera 12, and stale prefixed condition pruning for modern Chrome/Firefox/Opera targets.

## Patch

- Added animation shorthand and legacy animation longhands to `TransitionPrefixer::supportsDeclarationPrefixGroups()`.
- Added focused assertions for `@supports (animation: ...)`, `@supports (animation-name: ...)`, `@supports (animation-duration: ...)`, and stale prefixed `@supports` cleanup.
- Extended `examples/wordpress-animation-prefixer.php` with a block animation guarded by `@supports` so the visible WordPress path exercises the prelude and declaration body together.
- Updated `lane-status.json`: `phpPass` moves `9034 -> 9039` from the verified full LightningCSS lane run. Mapped coverage remains `2439 / 3532` because this is additional parity inside the already mapped target-prefixing/browser-boundary area.

## Verification

```bash
php -l lanes/lightningcss/src/TransitionPrefixer.php
php -l lanes/lightningcss/tests/TransitionPrefixerTest.php
php -l lanes/lightningcss/examples/wordpress-animation-prefixer.php
php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php
php lanes/lightningcss/examples/wordpress-animation-prefixer.php --self-test
php tools/run-tests.php lanes/lightningcss/tests
```

Results:

- `TransitionPrefixerTest.php`: `1 test files, 1434 assertions, 0 failures`.
- WordPress animation example self-test: `OK`.
- Full LightningCSS lane tests: `13 test files, 9039 assertions, 0 failures`.

## Non-overlap

This slice avoids accepted direct animation declaration prefixing, keyframes prefixing, animation timeline fallback, selector-pseudo target fallbacks, CSS Regions preservation, and residual color/media target-prefixing work. It only closes the missing `@supports` declaration-prefix browser boundary for animation properties.

## Dependency closure

No new support component is needed. The change reuses the existing PHP supports-condition parser, target version table, and declaration prefix rewriter.
