# CSS Modules Local/Global Compose Cycle Parity

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260601T072116Z`

## Upstream source truth

- Pinned upstream: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream areas: `src/css_modules.rs::CssModule::handle_composes` and the native Node bundle path for CSS Modules source-index `composes`.
- Native upstream oracle spot check: an entry module composing `.card` from `./card.css`, where `.card` composes `.entry` back from the entry file plus a local `.utility` and a global `wp-card`, emits export metadata for `entry` with `card_card`, `card_utility`, and `wp-card` only. The cyclic root `entry_entry` is not appended to its own `composes` list.

## Implementation

- `CssBundler::resolvedCssModuleExports()` now seeds the current export as the first compose-resolution stack entry before recursively resolving dependency `composes`.
- This preserves accepted repeated source-index compose behavior, but stops a dependency cycle from appending the root export back into its own metadata.
- The touched WordPress example models a block module whose dependency composes back to the entry module while also composing local and global classes.

## Verification

- Focused test: `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php`
  - `1 test files, 611 assertions, 0 failures`
- Full LightningCSS lane: `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 6701 assertions, 0 failures`
- Example smoke: `php lanes/lightningcss/examples/wordpress-css-modules-bundler-composes.php --self-test`
  - exit 0, including `source-index-cycle: guarded`
- PHP lint: `php -l lanes/lightningcss/src/CssBundler.php`, `php -l lanes/lightningcss/tests/CssBundlerTest.php`, and `php -l lanes/lightningcss/examples/wordpress-css-modules-bundler-composes.php`
  - no syntax errors
- Whitespace: `git diff --check -- lanes/lightningcss`
  - exit 0

## Coverage And Non-Overlap

- Conservative mapped coverage remains `2360 / 3532`; this deepens the already represented CSS Modules source-index/local/global compose cluster rather than claiming a new denominator row.
- This avoids accepted CSS Modules WebVTT cue scoping, repeated source-index duplicate preservation, dependency missing-export omission, conditional compose rejection, selector local/global pseudo handling, source-map, media-query, CSSOM, property-value, custom at-rule, and target-prefixing clusters.

## Dependency Closure

- No new support component is needed. This reuses the bounded native CSS Modules bundler, dependency graph resolver, and existing source-index compose metadata representation.
