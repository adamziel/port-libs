# Target Prefixing Browser Boundary Parity - Newer Selector Pseudos

Micro-slice: `lightningcss-target-prefixing-browser-boundary-parity-20260601T184235Z`

Base accepted HEAD: `4cbd19204f0f849ce2c2efa0ea77036ddc64c707`

## Source Truth

Pinned upstream LightningCSS checkout:
`/home/claude/port-libs/.upstream-cache/lightningcss`

Upstream commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`

Files inspected:

- `src/compat.rs`: `Feature::TargetText`, `Feature::SearchText`, `Feature::DetailsContent`, `Feature::Picker`, `Feature::PickerIcon`, `Feature::Checkmark`, `Feature::GrammarError`, `Feature::SpellingError`, and `Feature::StatePseudoClass` browser thresholds.
- `src/selector.rs`: selector serialization for `::target-text`, `::search-text`, `::details-content`, `::picker(...)`, `::picker-icon`, `::checkmark`, `::grammar-error`, `::spelling-error`, and `:state(...)`.

Upstream browser boundaries ported into the PHP target-prefixing path:

- `::target-text`: Chrome/Edge/Android 89, Firefox 131, Opera 63, Safari/iOS 18.1, Samsung 15.
- `::search-text`: Chrome/Edge/Android 144 and Opera 95; other named targets remain unsupported.
- `::details-content`: Chrome/Edge/Android 131, Firefox 143, Opera 87, Safari/iOS 18.4, Samsung 29.
- `::picker(...)`: Chrome/Edge/Android 135, Opera 89, Samsung 29; Firefox/Safari/iOS remain unsupported.
- `::picker-icon` and `::checkmark`: Chrome/Edge/Android 133, Opera 88, Samsung 29; Firefox/Safari/iOS remain unsupported.
- `::grammar-error` and `::spelling-error`: Chrome/Edge/Android 121, Opera 81, Safari/iOS 17.4, Samsung 25; Firefox remains unsupported.
- `:state(...)`: Chrome/Edge/Android 125, Firefox 126, Opera 83, Safari/iOS 17.4, Samsung 27.

## Behavior Added

`TransitionPrefixer` now treats those selectors like other upstream unsupported selector-list pseudos:

- If an unsupported selector-list pseudo appears in a comma list and the list contains pseudo-elements, the rule is split so an older browser does not drop the entire group.
- If the unsupported selector-list pseudo is class-like and the target supports forgiving `:is(...)`, equal-specificity selector lists are wrapped in `:is(...)`.
- If all requested targets meet the upstream boundary, the comma list is preserved.

This slice intentionally does not duplicate the already accepted scroll-navigation `:target-current`, `:target-before`, and `:target-after` selector work.

## Verification

Red probe before implementation:

- `a::target-text, a { color: green; }` for Chrome 88 stayed grouped instead of splitting.
- `mark::search-text, mark { color: yellow; }` for Chrome 143 stayed grouped instead of splitting.
- `wa-checkbox:state(disabled), button:state(checked) { color: red; }` for Chrome 124 stayed grouped instead of using the forgiving `:is(...)` fallback.

Commands run after implementation:

- `php -l lanes/lightningcss/src/TransitionPrefixer.php`
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php`
- `php -l lanes/lightningcss/examples/wordpress-selector-target-prefixer.php`
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - Result: `1 test files, 1427 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-selector-target-prefixer.php --self-test`
  - Result: `selector target prefixer example self-test passed`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - Result: `13 test files, 8953 assertions, 0 failures`

- `git diff --check -- lanes/lightningcss`
  - Result: passed

## Status Delta

- `phpPass` evidence moves from `8934` to `8953` assertions, a `+19` focused assertion delta from the new `TransitionPrefixerTest.php` selector pseudo boundary block.
- Conservative mapped coverage remains `2399 / 3532`; this slice deepens the represented target-prefixing selector compatibility cluster rather than claiming a new denominator row.
- `wordpress-selector-target-prefixer.php` now self-tests WordPress block search highlight pseudos, navigation `:state(...)`, and select picker pseudo selector-list fallback behavior.

## Dependency Closure

No new support component is needed. The implementation reuses the existing PHP target option normalization, `targetsNeedFeatureFallback()` browser threshold helper, selector-list unsupported pseudo fallback path, specificity comparison, and `:is(...)` support detection.

## Next Task

Continue with non-overlapping LightningCSS target-prefixing or selector parity gaps, especially parser/recovery and selector edge cases that can be backed by upstream `compat.rs`, `selector.rs`, or concrete upstream helper tests.
