# LightningCSS focus selector-list target boundary parity

Micro-slice: `lightningcss-target-prefixing-browser-boundary-parity-20260601T071147Z`

## Source truth

- Upstream pinned commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/compat.rs` `Feature::FocusWithin`, `Feature::FocusVisible`, and `Feature::IsSelector` browser boundaries drive when selector-list fallback is needed and when forgiving `:is(...)` wrapping is available.
- `src/lib.rs` `test_selectors` includes the unsupported selector-list cases used here:
  - Safari 13 splits `:hover, :focus-visible`.
  - Safari 14 wraps same-specificity lists with `:is(...)`.
  - Safari 9 splits `:focus-within, :focus-visible`.
  - Safari 14 splits specificity-changing or pseudo-element lists.

## Patch

- Added target gates for `:focus-within`, `:focus-visible`, and `:is()` support.
- Added selector-list isolation before per-selector prefix expansion:
  - unsupported focus pseudo selectors are split for targets that cannot safely use forgiving selector lists;
  - same-specificity selector lists are wrapped in `:is(...)` when target-supported;
  - selector lists with pseudo-elements or unequal specificity are split to preserve upstream behavior.
- Reused existing `SelectorSpecificity` and selector splitting helpers; no new support component is needed.
- Extended the WordPress selector target-prefixer example with a block button `:focus-visible` selector-list smoke.

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `1 test files, 1060 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 6663 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-selector-target-prefixer.php --self-test`
  - `selector target prefixer example self-test passed`

## Non-overlap

This slice avoids accepted target-prefixing clusters for keyframes, placeholder/placeholder-shown, selector `:is`/`:lang`/`:dir`, autofill, fullscreen, file-selector-button, logical spacing/inset/size, display flex, transforms, filters, mask, image-set, text-decoration, cursor, scroll-snap, media ranges, and supports-declaration prefix boundaries.

## Follow-up

The remaining upstream edge worth isolating is combined unsupported selector-list splitting with logical property fallback emission, where declaration fallback and selector-list isolation both need to compose in one rule rewrite.
