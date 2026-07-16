# CSS Modules Quoted Language Selector Compose Parity

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260601T114351Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Upstream `src/selector.rs` parses `:lang()` arguments as identifiers or strings via `expect_ident_or_string()` and serializes each with identifier escaping.
- Upstream `:dir()` parses a `Direction` token and serializes the canonical lowercase direction.
- Local NAPI oracle spot checks from `.upstream-cache/lightningcss/lightningcss.linux-x64-gnu.node` confirmed:
  - `.card:lang("en-US", "fr")` prints `.EgL3uq_card:lang(en-US,fr)` and preserves local `composes` exports.
  - `.card:l\61 ng("zh\2d Hans", fr)` prints `.EgL3uq_card:lang(zh-Hans,fr)`.
  - `.card:lang("en US")` prints `.EgL3uq_card:lang(en\ US)`.
  - `.card:DIR(r\74 l)` prints `.EgL3uq_card:dir(rtl)`.
  - `:lang(1)`, `:lang("en" "fr")`, `:dir("ltr")`, `:dir(ltr, rtl)`, and `:dir(foo)` reject.

## Implementation

- `CssModulesTransformer` now serializes minified `:lang()` string arguments as escaped identifiers instead of preserving quotes.
- Escaped `:lang()` names and escaped identifier/string arguments are decoded before canonical serialization, matching upstream output.
- Minified `:dir()` now requires a single identifier and only accepts `ltr` or `rtl`, so quoted, multiple, or unknown direction values reject.
- Existing local/global CSS Modules class hashing and `composes` export flattening are unchanged and covered by the focused assertions.
- The WordPress CSS Modules language pseudo example now covers quoted language tags, escaped language identifiers, escaped direction identifiers, and invalid direction/language arguments.

## Verification

- Red-first PHP spot check before the patch preserved quoted language arguments such as `:lang("en-US","fr")`, while pinned upstream emitted `:lang(en-US,fr)`.
- `php -l lanes/lightningcss/src/CssModulesTransformer.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-css-modules-language-pseudo-guards.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` -> `1 test files, 567 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-language-pseudo-guards.php --self-test` -> `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 7625 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` -> passed.
- Root harness status: not run - isolated micro-slice.

## Status Delta

- Focused CSS Modules assertions: `557 -> 567`.
- `lane-status.json` `phpPass`: `7615 -> 7625`.
- Conservative mapped coverage remains `2374 / 3532`; this deepens the already represented CSS Modules local/global/composes language selector cluster rather than claiming a new denominator row.

## Dependency Closure

No new support component is needed. This reuses the native PHP CSS Modules selector scanner, CSS string and identifier token readers, identifier serializer, minifier pipeline, export metadata model, and WordPress example self-test harness. No Node, Rust, WASM, browser service, parser generator, or external CSS engine is required at runtime.

## Non-Overlap

This does not repeat accepted escaped view-transition selector-function names, escaped custom-ident exports, local/global colon guards in language pseudos, host/slotted/cue/state/highlight selector handling, terminal pseudo behavior, invalid/commented `composes`, bundle/import graph, source-map, CSSOM, media-query, custom at-rule, property/value, or target-prefixing slices. The patch is limited to quoted and escaped `:lang()` plus escaped `:dir()` argument parity on the CSS Modules local/global/composes path.

## Next Task

Continue with non-overlapping CSS Modules selector/composes edges, or pivot to current-priority LightningCSS source-map, bundle/import graph, CSSOM, media-query, target-prefix, property/value, or custom at-rule parity.
