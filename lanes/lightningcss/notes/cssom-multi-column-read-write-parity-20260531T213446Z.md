# CSSOM Multi-column Read/write Parity

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260531T213446Z`

Base accepted HEAD: `c7ca7ac45660966d9eecf84ad3eaffea66691f11`

## Upstream source truth

- Pinned upstream commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `tests/test_cssom.rs` defines the generic `DeclarationBlock::get`, `set`, and `remove` CSSOM contract used by the existing PHP `DeclarationBlock` parity tests.
- `src/prefixes.rs` includes `Feature::Columns`, `ColumnWidth`, `ColumnCount`, `ColumnRule`, `ColumnRuleWidth`, `ColumnRuleStyle`, and `ColumnRuleColor` in the same prefix feature group, so this slice treats unprefixed, `-webkit-`, and `-moz-` multi-column declarations as one read/write/remove family.
- Existing `wordpress-column-prefixer.php` remains the multi-column target-prefixing path; this slice is CSSOM declaration manipulation, not target prefix emission.

## Implementation

- Added CSSOM read/write/remove support for `columns`, `column-width`, and `column-count`.
- Added CSSOM read/write/remove support for `column-rule`, `column-rule-width`, `column-rule-style`, and `column-rule-color`.
- Applied the same behavior to `-webkit-` and `-moz-` prefixed multi-column declarations.
- Reused the existing `DeclarationBlock` priority buckets and shorthand splitting behavior.

## Verification

- Baseline before implementation: `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` -> `1 test files, 757 assertions, 0 failures`.
- Focused after implementation: `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` -> `1 test files, 791 assertions, 0 failures`.
- Full lane: `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 4487 assertions, 0 failures`.
- Example smoke: `php lanes/lightningcss/examples/wordpress-column-cssom.php --self-test` -> exit 0.
- PHP lint passed for `DeclarationBlock.php`, `DeclarationBlockTest.php`, and `wordpress-column-cssom.php`.
- `git diff --check -- lanes/lightningcss` -> clean.
- `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json` decode with `JSON_THROW_ON_ERROR`.

## Status delta

- `phpPass` moves from `4453` to `4487` based on full lane evidence.
- Conservative mapped coverage remains `2145 / 3532`; the slice deepens the already represented `DeclarationBlock` CSSOM cluster.

## Non-overlap

- Does not repeat accepted multi-column target prefixing, `wordpress-column-prefixer.php`, or `TransitionPrefixer` prefix-boundary behavior.
- Does not repeat existing CSSOM gap, overflow, container, font, list-style, animation, grid, border-image, mask, outline, text-decoration, caret, logical-axis, or WebKit mask-box-image slices.

## Dependency closure

No new support component is required. The slice reuses the native PHP `DeclarationBlock` parser, property buckets, shorthand expansion, and serialization helpers.

## Next

Continue with a non-overlapping CSSOM property family or switch to a higher-priority LightningCSS gap in bundle/import graph, source maps, CSS Modules, parser recovery, visitor/custom at-rules, target prefixing, media queries, selectors, or property/value parity.
