# Charset Unicode Width Core Current Base - ISO-2022-CN GB2312

Slice: `pandoc-charset-unicode-width-core-current-base-20260609T053523Z`
Base: `43b1a4a1010b27f9642a54fbdd65b896e3bf9eec`

## Behavior

`UnicodeText::decodeBytes()` now recognizes `iso-2022-cn`, `iso2022cn`, and
`csiso2022cn` for a bounded ISO-2022-CN GB2312 path. The decoder handles
`ESC $ ) A` designation, SO/SI shifted GB2312 pairs, ASCII returns, and
deterministic replacement repairs for unsupported or malformed designation,
shift, pair, and final-state cases.

The new focused case feeds decoded source through `MarkdownReader` and
`WordPressBlockWriter`, preserving `sourceEncoding`, WordPress heading/
paragraph output, and the display-width audit used by the charset handoff
example.

## Evidence

- Baseline before edits: `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php` -> `1 test files, 1453 assertions, 0 failures`.
- Red-first after adding the ISO-2022-CN test only: `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php` -> `1 test files, 1454 assertions, 1 failures`; the source still decoded as UTF-8 instead of `iso-2022-cn`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php` -> `1 test files, 1474 assertions, 0 failures`.
- WordPress example smoke: `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test` -> `charset unicode handoff self-test ok`.

## Dependency Closure

No new native support component is needed. This slice reuses the existing
native PHP `UnicodeText` charset dispatcher, the local GBK pair table for the
GB2312 code space, `MarkdownReader` source-encoding provenance, and
`WordPressBlockWriter` block output. No Pandoc, Cabal solver/build/test
command, Haskell runner, Word, LibreOffice, zip/unzip, external converter,
external template engine, TeX/PDF engine, browser renderer, online service,
live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted ISO-2022-KR, ISO-2022-JP, HZ-GB-2312,
GB1988/GBK/GB18030/GB12345, EUC-JP JIS0212, Big5, or display-width-only
coverage. It is a bounded ISO-2022-CN GB2312 SO/SI decode and repair slice.

## Next

Good follow-up candidates are Big5-HKSCS extension coverage, EUC-TW only if a
bounded local source table is available, or East Asian display-width edge cases
that are independent of this ISO-2022-CN path.
