# CSSOM animation-range read/write parity

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260531T180416Z`

Upstream source truth:

- `parcel-bundler/lightningcss` pinned manifest commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/declaration.rs` generic `DeclarationBlock::{get,set,remove}` behavior reads shorthand-derived longhands, rewrites a shorthand when a compatible longhand is set, and splits a shorthand into remaining longhands when one longhand is removed.
- `src/properties/animation.rs` defines `AnimationRange`, `AnimationAttachmentRange`, default start/end offsets, and serialization that omits `normal` end ranges and same-name end ranges at `100%`.

Implemented behavior:

- `DeclarationBlock` now treats `animation-range` as a CSSOM shorthand for `animation-range-start` and `animation-range-end`.
- Reads expand `animation-range` into start/end longhands, including multi-layer comma lists and `!important` bucket behavior.
- Longhand writes update compatible existing `animation-range` declarations instead of appending direct longhands.
- Longhand removal splits `animation-range` into the remaining range longhand, and shorthand removal drops both range longhands.
- Added `examples/wordpress-animation-range-cssom.php` to cover scroll-linked WordPress cover reveal CSSOM manipulation without Node/WASM.

Red-first evidence:

```text
php -r 'require "tools/bootstrap.php"; $b = new PortLibs\LightningCSS\DeclarationBlock(); var_export([$b->getProperty("animation-range: entry 10% exit 90%", "animation-range-start"), $b->setProperty("animation-range: entry", "animation-range-end", "exit 90%"), $b->removeProperty("animation-range: entry 10% exit 90%", "animation-range-end")]); echo "\n";'
array (
  0 => NULL,
  1 => 'animation-range: entry; animation-range-end: exit 90%',
  2 => 'animation-range: entry 10% exit 90%',
)
```

Verification:

```text
php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php
1 test files, 533 assertions, 0 failures

php lanes/lightningcss/examples/wordpress-animation-range-cssom.php --self-test
OK

php tools/run-tests.php lanes/lightningcss/tests
13 test files, 2843 assertions, 0 failures
```

Status delta:

- Full LightningCSS lane assertions moved from `2825` to `2843` pass / `0` fail.
- Mapped denominator coverage is unchanged; this deepens the existing CSSOM declaration parity and already mapped animation-range property cluster rather than adding a new upstream inventory row.

Non-overlap:

- Does not touch source-map VLQ parsing, aspect-ratio minification, HWB color-mix, file-backed CSS Modules bundling, mask/mask-border CSSOM, border-image/radius, outline, transition, font, container, text-decoration, text-emphasis, caret, flex, gap, overflow, or list-style CSSOM clusters.
- A current rework note exists for custom-media import-tail conflict handling; it is unrelated to this CSSOM declaration micro-slice and was not modified.

Dependency closure:

- No new support component is needed. The slice reuses the existing PHP `DeclarationBlock` parser, top-level splitting helpers, lane test harness, and local example smoke path.

Next task:

- Continue CSSOM parity with a non-overlapping shorthand/longhand family not already covered by the accepted CSSOM clusters, or move to a higher-priority LightningCSS import graph/source-map/CSS Modules/custom at-rule/property-value gap if directed by the supervisor.
