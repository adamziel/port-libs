# CSSOM WebKit Mask Box Image Read/Write Parity

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260531T203106Z`

## Upstream Source Truth

- Manifest commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Upstream `src/properties/mod.rs` defines the legacy WebKit CSSOM family:
  `mask-box-image`, `mask-box-image-source`, `mask-box-image-slice`,
  `mask-box-image-width`, `mask-box-image-outset`, and `mask-box-image-repeat`
  with `VendorPrefix::WebKit` and no unprefixed emission.
- Upstream `src/properties/masking.rs` maps `Property::WebKitMaskBoxImage*`
  to the same source/slice/width/outset/repeat component slots used by the
  border-image-style shorthand, without a `mask-border-mode` component.
- Upstream DeclarationBlock behavior in `tests/test_cssom.rs` requires
  shorthand reads from complete longhands, longhand reads from shorthand,
  longhand writes into compatible shorthands, and longhand removal by
  decomposing the shorthand into remaining longhands.

## Implementation Delta

- `DeclarationBlock` now reads `-webkit-mask-box-image` shorthand values into
  prefixed source/slice/width/outset/repeat longhands.
- Complete prefixed longhand sets compose back into the prefixed shorthand
  when all priorities match.
- Prefixed longhand writes update a compatible existing
  `-webkit-mask-box-image` shorthand instead of appending a duplicate
  longhand.
- Prefixed longhand removals split an existing shorthand into the remaining
  prefixed longhands and preserve importance.
- The legacy prefixed family stays isolated from modern unprefixed
  `mask-border-*` CSSOM lookups and writes.

## Red-First Evidence

Before implementation, the new focused test failed on the first prefixed
longhand read:

`php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php`

Result: `1 test files, 730 assertions, 1 failures`; expected
`-webkit-mask-box-image-source` to read `url(frame.svg)`, actual was `NULL`.

## Verification

Final verification:

- `php -l lanes/lightningcss/src/DeclarationBlock.php`
- `php -l lanes/lightningcss/tests/DeclarationBlockTest.php`
- `php -l lanes/lightningcss/examples/wordpress-webkit-mask-box-image-cssom.php`
- `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php`
- `php tools/run-tests.php lanes/lightningcss/tests`
- `php lanes/lightningcss/examples/wordpress-webkit-mask-box-image-cssom.php --self-test`
- `git diff --check -- lanes/lightningcss`

Focused DeclarationBlock coverage moved from `729` to `744` assertions with
`0` failures. Full LightningCSS lane coverage moved from `4150` to `4165`
assertions with `0` failures. Mapped denominator coverage is unchanged because
this deepens the already represented DeclarationBlock CSSOM cluster.

## Non-Overlap

This patch does not repeat the accepted `mask-border` CSSOM, generic `mask`
CSSOM, `-webkit-mask` CSSOM, target-prefixing mask-box-image composition,
bundle/import graph, source-map, CSS Modules, or custom at-rule clusters.

## Dependency Closure

No new support component is needed. The implementation reuses the native PHP
declaration parser, CSS URL normalizer, border-image component parser, and
border-image shorthand composer already present in the lane.

## Follow-Up

Continue CSSOM parity on another unmapped shorthand family, or move to a
non-overlapping LightningCSS source-map, parser recovery, visitor/custom
at-rule, or property/value parity slice.
