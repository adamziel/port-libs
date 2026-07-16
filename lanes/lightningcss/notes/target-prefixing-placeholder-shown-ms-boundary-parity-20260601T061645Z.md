Micro-slice: `lightningcss-target-prefixing-browser-boundary-parity-20260601T061645Z`

Source truth:
- Pinned upstream `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/prefixes.rs::Feature::PseudoClassPlaceholderShown` emits the MS prefix for IE targets `>= 10`.
- `src/selector.rs` parses `:-ms-input-placeholder` as the MS `PlaceholderShown` selector and serializes it via `write_prefixed!(..., "placeholder-shown")`, which produces `:-ms-placeholder-shown`.
- Local native-addon oracle spot checks:
  - IE 9: `input:placeholder-shown{color:red}`
  - IE 10/11: `input:-ms-placeholder-shown{color:red}input:placeholder-shown{color:red}`
  - Firefox 50: `input:-moz-placeholder-shown{color:red}input:placeholder-shown{color:red}`

Implementation:
- Changed the target-prefix selector variant for `:placeholder-shown` MS targets from `:-ms-input-placeholder` to upstream's serialized `:-ms-placeholder-shown`.
- Kept the separate `::placeholder` pseudo-element MS output as `::-ms-input-placeholder`.
- Added IE9/IE10/IE11 and WordPress search-input focused assertions in `TransitionPrefixerTest.php`.
- Added `examples/wordpress-placeholder-shown-selector-prefixer.php --self-test`.

Evidence:
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `1 test files, 1043 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-placeholder-shown-selector-prefixer.php --self-test`
  - `placeholder-shown selector prefixer example self-test passed`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 6432 assertions, 0 failures`
- `php -l lanes/lightningcss/src/TransitionPrefixer.php`
  - `No syntax errors detected`
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `No syntax errors detected`
- `php -l lanes/lightningcss/examples/wordpress-placeholder-shown-selector-prefixer.php`
  - `No syntax errors detected`
- `git diff --check -- lanes/lightningcss`
  - passed with no output

Non-overlap:
- This does not repeat the accepted fullscreen, `::placeholder` pseudo-element, selector pseudo boundary, mask, box-shadow, font supports, supports declaration, media-query, CSSOM, bundle/import graph, CSS Modules, source-map, or custom at-rule clusters.
- This slice only corrects the upstream MS `:placeholder-shown` browser-boundary selector spelling and adds the missing IE lower-bound assertion.

Dependency closure:
- No new support component is needed. The existing PHP selector-prefix rewriting path and test harness cover this behavior.
