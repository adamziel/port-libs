# Pandoc Rich Package Unsupported Format Review Packet

Implemented one bounded native PHP registry slice for rich package
unsupported-format review metadata.

## Behavior

- `RichPackageUnsupportedFormatRegistry::unsupportedFormatSummary()` now reports
  direction buckets, support buckets, unsupported input/output format lists,
  no-native-reader/writer lists, diagnostic code counts, gate counts, source
  alias gaps, and extension-level unsupported package names.
- `RichPackageUnsupportedFormatRegistry::reviewPacket()` exposes the summary
  alongside raw unsupported, source-alias, and extension diagnostics.
- `PandocFormatRegistry` now delegates
  `richPackageUnsupportedFormatSummary()` and
  `richPackageFormatReviewPacket()` to the rich package registry so registry
  handoffs can consume the same review surface.

This is registry metadata only. It does not add a converter, claim full direct
format parity, invoke Pandoc, office suites, TeX/browser engines, Typst,
Jupyter, Node tooling, zip/unzip, external validators, online services, or live
provider tests.

## Verification

- `php -l lanes/pandoc/src/RichPackageUnsupportedFormatRegistry.php`
- `php -l lanes/pandoc/src/PandocFormatRegistry.php`
- `php -l lanes/pandoc/tests/RichPackageUnsupportedFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/RichPackageUnsupportedFormatRegistryTest.php`
  - Result: 1 test file, 160 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/RichPackageUnsupportedFormatRegistryTest.php lanes/pandoc/tests/PandocFormatRegistryTest.php`
  - Result: 2 test files, 398 assertions, 0 failures.

## Accounting

- Added one focused PHP behavior case for rich package unsupported-format
  summary and review-packet buckets.
- `lane-status.json` `phpPass` moves from `490` to `491`.
