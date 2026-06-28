# Pandoc Plain Writer Wrap Boundary Diagnostics

Implemented one bounded native PHP plain-writer diagnostics slice for actual
auto-wrap opportunity boundaries.

## Behavior

- `PlainWriter::writeWithDiagnostics()` now reports aggregate counts for emitted
  auto-wrap breaks that used a break opportunity, separated from forced token
  splits.
- The new counters classify actual emitted wrap boundaries by source separator:
  ordinary spaces, Unicode spaces, tabs, zero-width spaces, soft hyphens, and
  visible break-after separators.
- Existing output text, generated wrap counts, forced wrap counts, and possible
  break-opportunity counters are unchanged; the new metrics only explain which
  opportunities were actually consumed by wrapping.

This slice does not invoke Pandoc, Cabal/Haskell runners, browser renderers,
office suites, TeX/PDF engines, JSON filters, external validators, online
services, live provider tests, or live-service provider tests.

## Verification

- `php -l lanes/pandoc/src/PlainWriter.php`
- `php -l lanes/pandoc/tests/PlainWriterTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PlainWriterTest.php`
  - Result: 1 test file, 248 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Attempted after the focused pass. Current branch/base remains broad
    baseline-red outside this slice: 295 test files, 116,985 assertions,
    9,781 failures. The visible failure set includes unrelated
    `YamlMetadataReviewTest.php` metadata-path assertions.

## Accounting

- Added one focused PHP behavior case for plain-writer wrapping diagnostics.
- `phpPass` moves from `457` to `458`; `phpFail` remains `0`.
