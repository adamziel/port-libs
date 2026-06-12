# Pandoc JSON/native zero-content block payloads

Slice: `plib-9rsl4`, Pandoc JSON/native AST constructor completeness.

This slice adds focused coverage for source-tagged zero-content block
constructors:

- `HorizontalRule`
- `Null`

Both `PandocJsonReader` and `NativeReader` preserve the original native
constructor payloads, including inert review provenance such as review queues
and source ordinals. `PandocJsonWriter` and `NativeWriter` now have explicit
coverage proving those payloads survive unchanged JSON/native handoff and
regenerate as bare constructors once semantic review-state edits make the
source payload stale.

Accounting:

- `mappedJsonNativeZeroContentBlockPayloadCases`: `1`
- `jsonNativeZeroContentBlockPayloadAssertions`: `20`

Verification on current main `1cc64b1e16`:

- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test files, 1469 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 69662 assertions, 0 failures`

No Pandoc, JSON filters, Cabal/Haskell runners, browser renderers, external
validators, online services, live provider tests, or live-service provider
tests were invoked.
