# CSS Modules WebVTT Cue Local/Global/Compose Parity

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260601T064545Z`

## Source Truth

- Upstream: `parcel-bundler/lightningcss` pinned at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Static source: `src/selector.rs` defines `::cue` / `::cue-region` bare pseudo-elements and functional `::cue(...)` / `::cue-region(...)` forms whose arguments parse as selectors and serialize inside the pseudo-element.
- Local NAPI oracle checks at the pinned cache showed:
  - `.card::cue(.foo)` scopes both `.card` and `.foo`.
  - `.card::cue-region(:global(.wp-caption) .title)` keeps `.wp-caption` global and scopes `.title`.
  - `.card::cue(:global(.wp-caption), .title)` rejects the selector-list comma.
  - `.card::cue-region(.foo) .title` rejects selectors after the pseudo-element.

## Implementation

- `CssModulesTransformer` now treats functional `::cue()` and `::cue-region()` as WebVTT pseudo-elements with a selector argument.
- Inner cue selectors reuse the CSS Modules selector rewriter, so local classes are scoped and `:global()` is unwrapped.
- The cue selector argument is constrained to one selector, matching upstream comma rejection.
- Existing pseudo-element boundary checks now recurse into these selector arguments and still reject descendant tails.
- Cue selector arguments are still not treated as pure-mode local anchors unless the outer selector has a local reference, matching upstream purity behavior.
- `composes` remains restricted to simple selectors; selectors containing `::cue(...)` or `::cue-region(...)` still reject composition declarations.

## Verification

- Before patch: `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` -> `1 test files, 434 assertions, 0 failures`.
- Focused after patch: `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` -> `1 test files, 442 assertions, 0 failures`.
- Full lane after patch: `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 6508 assertions, 0 failures`.
- Example smoke: `php lanes/lightningcss/examples/wordpress-css-modules-webvtt-cue.php --self-test` -> `OK`.
- Metadata validation: `php -r 'json_decode(...)'` for `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json` -> both OK.
- Whitespace check: `git diff --check -- lanes/lightningcss` -> no output.
- PHP lint:
  - `php -l lanes/lightningcss/src/CssModulesTransformer.php` -> no syntax errors.
  - `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php` -> no syntax errors.
  - `php -l lanes/lightningcss/examples/wordpress-css-modules-webvtt-cue.php` -> no syntax errors.

## Status Delta

- Focused CSS Modules assertions: `434 -> 442` (`+8`).
- Full LightningCSS lane assertions/pass count: `6500 -> 6508`.
- Conservative mapped coverage remains `2360 / 3532` because this deepens the already represented CSS Modules local/global/composes cluster.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP selector scanner, CSS Modules transformer, PHP test harness, and local example path. Node/Rust/WASM are not required at runtime; the local NAPI binding was used only as a source-truth oracle.

## Non-Overlap

This does not repeat accepted source-map VLQ offsets, CSSOM filter/backdrop-filter read-write parity, CSS Modules double-colon raw pseudo local/global behavior, state/highlight selectors, host-context/slotted/host boundaries, view-transition selectors, escaped/commented composes handling, bundle/import graph work, media-query parsing, property-value minification, custom at-rule visitors, or target prefixing. The patch is scoped to WebVTT cue pseudo-element selector arguments in CSS Modules.
