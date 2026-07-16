# Pandoc Charset/Unicode Width Core - CP862 DOS Hebrew Source Bytes

Slice: `pandoc-charset-unicode-width-core-current-base-20260608T220641Z`
Base accepted HEAD: `5ca5ed5c01549ddcb5727c8343ae1666cecfe98d`

## Behavior

- Added bounded native IBM862/CP862/DOS862 label recognition to `UnicodeText`.
- Decoded CP862 bytes `0x80` through `0x9A` as Hebrew letters and retained the
  existing CP437 table for shared DOS symbols, box drawing, Latin accents, and
  math bytes.
- Preserved CP862 source encoding metadata through `MarkdownReader` and added a
  WordPress charset handoff audit row carrying decoded Hebrew text plus
  narrow/wide display-width evidence.

## Source Truth

This slice uses the local Tcl static table at
`/usr/share/tcl9.0/encoding/cp862.enc` as the byte-map reference for the bounded
CP862 decoder. No external charset converter was executed.

No Pandoc executable, Cabal solver/build/test command, Haskell runner, browser
renderer, online service, live provider test, or live-service provider test was
run for progress.

## Evidence

- No `port-pandoc-*.needs-lane-rework.md` note existed for this lane before the
  slice.
- Baseline before this slice:
  `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  passed with `1 test files, 1080 assertions, 0 failures`.
- Red-first after adding the CP862 case before implementation:
  `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  failed with `1 test files, 1081 assertions, 1 failures` because `cp862`
  still fell back to `utf-8-repaired`.
- Final focused verification:
  `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  passed with `1 test files, 1094 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  passed with `charset unicode handoff self-test ok`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP `UnicodeText`
decoding and display-width helpers, `MarkdownReader`, `WordPressBlockWriter`,
the focused Unicode tests, and the existing WordPress charset handoff example.

## Non-Overlap

This slice does not repeat accepted ISO-8859-8 or Windows-1255 Hebrew,
IBM437/850/852/857/860/861/863/865/866/869, ISO-8859-3/7/9, Windows-1256/1258,
Shift_JIS/Windows-31J, GBK/GB18030, HZ-GB-2312, Indic/Myanmar/Khmer display
clusters, or Unicode separator wrapping. It is limited to the CP862/OEM Hebrew
source-byte decoder plus WordPress charset audit coverage.

## Next

Choose a non-overlapping charset or Unicode layout gap such as Mac Turkish byte
decoding, IBM775 Baltic/OEM source imports, declared HTML/XML charset handoff
integration, or additional Unicode line-break metadata.
