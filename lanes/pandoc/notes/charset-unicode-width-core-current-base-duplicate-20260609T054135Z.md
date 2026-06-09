# Charset Unicode Width - EUC-TW Plane 1

Slice: `pandoc-charset-unicode-width-core-current-base-duplicate-20260609T054135Z`

Base accepted HEAD: `50ff75128f57e5d1c91c6f6643df81bffbb2e704`

## Behavior

- Added bounded native EUC-TW label normalization for `euc-tw`, `x-euc-tw`, and `cseuctw`.
- Added a small CNS 11643 plane-1 byte mapping table for the source bytes used by the focused WordPress handoff fixture.
- Added decoder repair paths for malformed plane-1 trails, unsupported valid SS2 multi-plane sequences, truncated SS2 sequences, and unmapped plane-1 pairs.
- Added WordPress charset handoff coverage showing EUC-TW decoded text and display-width metadata in the existing audit table.

## Source Truth And Non-Overlap

The plane-1 byte pairs are taken from the local Tcl `cns11643.enc` static encoding table shape already used by the environment for encoding inventories: EUC-TW plane-1 bytes are CNS row/cell bytes with the high bit set. This slice does not overlap the accepted Mac Central European/Romanian/Croatian/Thai/Ukrainian, Mac Symbol/Dingbats, Mac Japanese, ISO-2022-JP JIS0212, CP950, Big5 pointer-sequence, GBK/GB18030, or Unicode line-break/display-width slices.

## Verification

Baseline before this patch:

`php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`

Result: `1 test files, 1474 assertions, 0 failures`

Focused verification after this patch:

`php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`

Result: `1 test files, 1492 assertions, 0 failures`

Example smoke:

`php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`

Result: `charset unicode handoff self-test ok`

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses native `UnicodeText`, `MarkdownReader`, `WordPressBlockWriter`, and existing display-width/repair accounting. Full EUC-TW plane coverage remains a follow-up native charset expansion, not a dependency blocker for this bounded handoff.

## Next

For a non-overlapping follow-up, extend EUC-TW beyond the bounded plane-1 fixture mappings or add Big5-HKSCS extension coverage with fixture-backed WordPress handoff tests.
