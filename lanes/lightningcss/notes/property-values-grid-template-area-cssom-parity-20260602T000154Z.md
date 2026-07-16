# LightningCSS Grid Template Area CSSOM Parity

Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260602T000154Z`

Accepted base: `88709d5e9c46dd0bea4e620239d7ccd93667375e`

Upstream source truth:

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted pristine read: `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/properties/grid.rs | rg -n "GridTemplate|GridTemplateRows|GridTemplateColumns|GridTemplateAreas|impl_shorthand"`.
- Targeted pristine read: `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/declaration.rs | rg -n "fn get|longhand\\(&property_id\\)"`.
- Upstream `GridTemplate` parses quoted area rows plus optional row track sizes and optional explicit columns, and `impl_shorthand!` maps the shorthand into `GridTemplateRows`, `GridTemplateColumns`, and `GridTemplateAreas`. `DeclarationBlock::get()` asks shorthand declarations for the requested longhand before giving up.

Native PHP delta:

- `DeclarationBlock::gridTemplateComponentsFromShorthand()` now expands area-bearing `grid-template` shorthand values instead of returning `null` when quoted grid area rows are present.
- The parser extracts line-name boundaries, default `auto` row tracks for omitted sizes, explicit row track sizes, optional column track lists, and normalized `grid-template-areas` rows.
- Existing CSSOM set/remove paths reuse the same component extraction, so updating or removing `grid-template-rows`, `grid-template-columns`, or `grid-template-areas` from an area-bearing shorthand now follows the already accepted grid-template serializer.
- `wordpress-grid-template-cssom.php` now covers a WordPress-style block layout authored as one area-bearing `grid-template` shorthand and reads it back into rows, columns, and areas.

Evidence:

- Red-first current-base probe returned `NULL` for `grid-template-rows`, `grid-template-columns`, and `grid-template-areas` from `grid-template: [header-top] "a a a" [header-bottom main-top] "b b b" 1fr [main-bottom] / auto 1fr auto`.
- `php -l lanes/lightningcss/src/DeclarationBlock.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/DeclarationBlockTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-grid-template-cssom.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` -> `1 test files, 1387 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-grid-template-cssom.php --self-test` -> `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `14 test files, 9228 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` -> exits 0.

Status delta:

- `phpPass` moves `9222 -> 9228` from six focused DeclarationBlock assertions.
- Conservative mapped coverage remains `2439 / 3532` because this deepens the already represented DeclarationBlock CSSOM grid-template cluster rather than claiming a new denominator row.

Non-overlap:

- Does not repeat accepted grid value minifier/formatter rows, grid-template longhand composition from separate declarations, CSS Modules terminal pseudo/composes parity, target-prefixing, media-query, SourceMap, bundle/import graph, or runner-evidence closure slices.
- This handoff is limited to CSSOM longhand extraction, set, and remove behavior for area-bearing `grid-template` shorthand declarations.

Dependency closure:

- No new support component is needed. This reuses the native DeclarationBlock tokenizer, top-level splitter, grid track-list parser/serializer, and existing grid-template CSSOM set/remove machinery.

Root harness status: not run - isolated micro-slice.
