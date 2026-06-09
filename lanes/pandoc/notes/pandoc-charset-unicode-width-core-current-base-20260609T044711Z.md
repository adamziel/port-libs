# Pandoc Charset/Unicode Width Current-Base Slice

Micro-slice: `pandoc-charset-unicode-width-core-current-base-20260609T044711Z`
Base accepted HEAD: `4bd0353e68feb117d03d0d43e4710ee88b193cbf`

## Behavior

Added bounded native ISO-2022-JP JIS X 0212 plane-2 decoding in
`UnicodeText`. The decoder now recognizes the four-byte `ESC $ ( D` escape
state and decodes 7-bit JIS0212 lead/trail pairs through the existing bounded
JIS0212 table used by EUC-JP SS3.

Focused source truth for the mapped pairs is the local Tcl encoding table:
`/usr/share/tcl9.0/encoding/jis0212.enc`.

Covered byte pairs:

- `29 21` to `Æ`
- `29 2D` to `Œ`
- `27 44` to `Є`
- `27 74` to `є`
- `26 71` to `ά`
- `26 77` to `ό`

Malformed JIS0212 lead/trail bytes and valid-but-unmapped JIS0212 pairs repair
to U+FFFD while preserving sourceEncoding repair counts.

## Evidence

Baseline before edits:

```text
php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php
1 test files, 1417 assertions, 0 failures
```

Red-first after adding the focused test before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php
1 test files, 1419 assertions, 1 failures
```

Expected failure: the new ISO-2022-JP JIS0212 source decoded with repairs
because the decoder did not recognize `ESC $ ( D`.

Final focused test:

```text
php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php
1 test files, 1432 assertions, 0 failures
```

WordPress example smoke:

```text
php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test
charset unicode handoff self-test ok
```

Lane-local manifest/status movement:

- `lane-status.json` `phpPass`: `2327` to `2328`
- `UPSTREAM_TEST_MANIFEST.json` mapped: `2723` to `2724`
- `mappedCharsetUnicodeWidthCoreCases`: `9` to `10`
- `charsetUnicodeWidthCoreAssertions`: `65` to `80`

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `UnicodeText`
decoding, the existing bounded JIS0212 pair table, `MarkdownReader`
sourceEncoding provenance, `WordPressBlockWriter`, the focused PHP test runner,
and the WordPress charset handoff example.

No Pandoc, Cabal solver/build/test command, Haskell runner, TeX/PDF engine,
Word, LibreOffice, zip/unzip, browser renderer, external charset converter,
online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This is limited to ISO-2022-JP JIS0212 `ESC $ ( D` state handling. It does not
repeat accepted EUC-JP JIS0212 SS3 byte decoding, Shift_JIS, EUC-JP JIS0208,
base ISO-2022-JP, Mac Japanese, Big5, GBK/GB12345/GB18030, EUC-KR,
ISO-2022-KR, HZ-GB-2312, Windows/ISO/Mac/DOS code pages, Unicode display-width
emoji/grapheme/separator/control slices, or non-charset Pandoc support lanes.

## Next

Choose a non-overlapping charset/Unicode gap such as additional JIS0212 pairs
from real fixtures, EUC-TW or Big5-HKSCS extension coverage, or another
display-width edge not already covered by current charset slices.
