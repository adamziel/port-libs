# Pandoc JSON/native textual metadata constructor handoff

Bead: `plib-6wjw8`
Date: 2026-06-30 UTC
Area: Pandoc JSON/native AST constructor completeness

## Behavior

Textual `NativeReader` metadata values use native-style typed records such as
`MetaInlines`, `MetaBlocks`, and `MetaList` with `value` payloads. Before this
slice, direct `PandocJsonWriter` handoff only recognized canonical shared meta
records such as `inlines`, `blocks`, `list`, and `map`, so text-native metadata
could be serialized as empty `MetaString` values.

`PandocJsonWriter` and standalone `NativeWriter` now accept both typed record
families for metadata constructor payloads. Textual native metadata maps with
inline, block, list, scalar, and nested map values now preserve `MetaInlines`,
`MetaBlocks`, `MetaList`, `MetaBool`, `MetaString`, and `MetaMap` constructors
through Pandoc JSON/native AST output and text-native rendering.

No Pandoc binary, JSON filters, Cabal/Haskell runners, office suites, TeX/PDF
engines, browser renderers, `zip`/`unzip`, external validators, online
services, live provider tests, or live-service provider tests were invoked.

## Accounting

- Focused PHP pass accounting: `472 -> 473`
- Direct format parity accounting remains in `lane-status.json`; no upstream
  denominator token was added for this handoff-only metadata constructor case.
- Broad lane status remains red outside this slice.

## Verification

- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/NativeReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTest.php`
  - `1 test files, 412 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTest.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `2 test files, 6635 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `298 test files, 117474 assertions, 9646 failures`
  - Existing broad-lane failures are outside this slice, with visible failures
    in `UnicodeTextTest.php` around missing `MarkdownReader::readBytes()`,
    display-width table expectations, and YAML metadata provenance cases.
