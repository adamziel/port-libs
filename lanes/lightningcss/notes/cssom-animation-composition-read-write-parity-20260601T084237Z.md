# CSSOM Animation Composition Read/Write Parity

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260601T084237Z`

## Source Truth

- Upstream commit: `parcel-bundler/lightningcss@22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/properties/animation.rs` defines `AnimationComposition` as `replace`, `add`, and `accumulate`.
- `src/properties/mod.rs` registers `animation-composition` as a parsed `SmallVec<AnimationComposition>` declaration.
- `src/lib.rs::test_animation` includes the upstream minifier case `animation-composition: add`.

Red probe before the patch:

```text
php -r 'require "tools/bootstrap.php"; $b=new PortLibs\LightningCSS\DeclarationBlock(); var_export([$b->getProperty("animation-composition: ADD, Accumulate", "animation-composition"), $b->setProperty("animation-composition: ADD; color: red", "animation-composition", "Accumulate")]); echo "\n";'
array (
  0 =>
  array (
    'value' => 'ADD, Accumulate',
    'important' => false,
  ),
  1 => 'animation-composition: Accumulate; color: red',
)
```

## Implementation

- `DeclarationBlock` now canonicalizes `animation-composition` comma lists through the CSSOM parse/get/set path.
- Known upstream enum values serialize as lowercase `replace`, `add`, and `accumulate`.
- Unknown or variable-backed list members are preserved, and custom properties such as `--Animation-Composition` remain case-preserving.
- Added `examples/wordpress-animation-composition-cssom.php` to cover WordPress block animation composition CSSOM reads, writes, priority buckets, and removal without Node/WASM.

## Verification

- Baseline focused test before patch: `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` => `1 test files, 1105 assertions, 0 failures`.
- Focused after patch: `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` => `1 test files, 1113 assertions, 0 failures`.
- Example smoke: `php lanes/lightningcss/examples/wordpress-animation-composition-cssom.php --self-test` => `OK`.
- Lint passed:
  - `php -l lanes/lightningcss/src/DeclarationBlock.php`
  - `php -l lanes/lightningcss/tests/DeclarationBlockTest.php`
  - `php -l lanes/lightningcss/examples/wordpress-animation-composition-cssom.php`

## Status Delta

- Focused DeclarationBlock coverage adds 8 assertions.
- Conservative mapped coverage remains `2360 / 3532`; this deepens the existing upstream DeclarationBlock CSSOM cluster rather than adding a new denominator row.
- Full upstream Rust/Node/WASM runners were not executed in this isolated worker slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP declaration parser, comma-list tokenizer, CSSOM priority bucket handling, and direct declaration set/remove paths.

## Non-Overlap

This does not repeat accepted animation shorthand/range CSSOM, transition CSSOM, SVG rendering CSSOM, clip-path CSSOM, mask/mask-border CSSOM, grid, font, container, border, background, source-map, bundle/import graph, CSS Modules, media-query, target-prefixing, property-value minifier, or custom at-rule visitor slices. The patch is limited to standalone `animation-composition` declaration read/write canonicalization.
