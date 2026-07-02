# Pandoc JSON/native textual legacy target constructors

Bead: `plib-pdt99`
Date: 2026-07-01 UTC
Area: Pandoc JSON/native AST constructor completeness

## Behavior

Textual `NativeReader` now accepts legacy attr-less inline target constructors:

- `Link [ ... ] ( "url" , "title" )`
- `Image [ ... ] ( "url" , "title" )`

The parsed shared AST keeps the same URL/title target payload and, for images,
the plain alt text derived from the inline label. `PandocJsonWriter` and
standalone `NativeWriter` continue to emit canonical current Pandoc native
constructor payloads with the default attr tuple first, so legacy text input
round-trips into the supported JSON/native shape.

No Pandoc binary, JSON filters, Cabal/Haskell runners, office suites, TeX/PDF
engines, browser renderers, `zip`/`unzip`, external validators, online
services, live provider tests, or live-service provider tests were invoked.

## Accounting

- Focused PHP pass accounting: `473 -> 474`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2317 -> 2318`
- Added mapped case: `mappedJsonNativeTextualLegacyTargetConstructorCases`
- Direct format parity accounting remains active in `lane-status.json`.

## Verification

- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/tests/NativeReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTest.php`
  - `1 test files, 478 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTest.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `2 test files, 6756 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `300 test files, 117555 assertions, 9649 failures`
  - Existing broad-lane failures are outside this slice, with visible failures
    in `UnicodeTextTest.php` around missing `MarkdownReader::readBytes()`,
    display-width table expectations, and YAML metadata provenance cases.
