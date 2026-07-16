# Charset Unicode Width Core Current Base - EUC-JP JIS0212

Slice: `pandoc-charset-unicode-width-core-current-base-20260609T043712Z`
Base accepted HEAD: `07a72489fb26b6c1406952193d9f53ff0495c0b3`

## Behavior

- Added bounded native EUC-JP JIS X 0212 plane-2 decoding for `0x8F lead trail`
  sequences in `UnicodeText::decodeBytes()`.
- Mapped the focused JIS0212 pairs from local source truth
  `/usr/share/tcl9.0/encoding/jis0212.enc`:
  - `8F A9 A1` to `Æ`;
  - `8F A9 AD` to `Œ`;
  - `8F A7 C4` to `Є`;
  - `8F A7 F4` to `є`;
  - `8F A6 F1` to `ά`;
  - `8F A6 F7` to `ό`.
- Malformed plane-2 lead/trail bytes, missing trails, and valid but unmapped
  JIS0212 pairs repair to U+FFFD and preserve sourceEncoding repair counts.
- The WordPress charset handoff example now includes an EUC-JP JIS0212 audit
  row with display-width accounting.

## Evidence

- Baseline before edits:
  - `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 1387 assertions, 0 failures`
- Red-first after adding the focused test before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 1389 assertions, 1 failures`
  - Failure was the expected missing plane-2 branch: the new source decoded
    with `12` repairs instead of `0`.
- Final focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 1404 assertions, 0 failures`
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`
- Syntax checks:
  - `php -l lanes/pandoc/src/UnicodeText.php`
  - `php -l lanes/pandoc/tests/UnicodeTextTest.php`
  - `php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`
  - Result: no syntax errors detected.
- JSON validation:
  - `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " json ok\n"; }'`
  - Result: both Pandoc JSON files decoded successfully.
- Diff hygiene:
  - `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `UnicodeText`
decoding, `MarkdownReader` sourceEncoding provenance, display-width helpers,
`WordPressBlockWriter`, the focused PHP test runner, and the existing WordPress
charset handoff example.

No Pandoc, Cabal solver/build/test command, Haskell runner, TeX/PDF engine,
Word, LibreOffice, zip/unzip, browser renderer, external charset converter,
online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This is limited to bounded EUC-JP JIS0212 plane-2 byte decoding. It does not
repeat accepted Shift_JIS, EUC-JP JIS0208, ISO-2022-JP, Mac Japanese, Big5,
GBK/GB12345/GB18030, EUC-KR, ISO-2022-KR, HZ-GB-2312, Windows/ISO/Mac/DOS
code pages, Unicode separator/control/emoji/Indic/Southeast Asian display-width
slices, XML/HTML5 DOM, ODT, DOCX, EPUB, ZIP/OPC, archive, math, citation,
BibTeX/CSL, table geometry, syntax-highlighting, or upstream-runner dependency
audit slices.

## Next

Pick a non-overlapping charset/Unicode gap such as additional JIS0212 pairs
from real fixtures, ISO-2022-JP JIS0212 state handling, EUC-TW or Big5-HKSCS
extension coverage, or another display-width edge not already covered by
current charset slices.
