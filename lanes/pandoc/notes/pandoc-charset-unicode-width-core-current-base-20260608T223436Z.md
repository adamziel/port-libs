# Pandoc Charset/Unicode Width Core - Mac Turkish Source Bytes

Slice: `pandoc-charset-unicode-width-core-current-base-20260608T223436Z`
Base accepted HEAD: `a93e698ac06f7885c2a47509237e09731628d097`

## Behavior

- Added bounded native Mac Turkish label recognition for `mac-turkish`,
  `x-mac-turkish`, and related compact labels.
- Decoded Mac Turkish bytes through the existing MacRoman table plus the
  Turkish-specific byte slots `0xDA` through `0xDF` and `0xF5`, preserving
  `ĞğİıŞş` and the Mac private-use glyph before Markdown parsing.
- Preserved source-encoding metadata through `MarkdownReader` and added a
  WordPress charset handoff audit row with narrow/wide display-width evidence.

## Source Truth

This slice uses the local static Tcl byte table at
`/usr/share/tcl9.0/encoding/macTurkish.enc` as the bounded map reference. No
external charset converter was executed.

No Pandoc executable, Cabal solver/build/test command, Haskell runner, browser
renderer, online service, live provider test, or live-service provider test was
run for progress.

## Evidence

- No `port-pandoc-*.needs-lane-rework.md` note existed for this lane before the
  slice.
- Baseline before this slice:
  `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  passed with `1 test files, 1094 assertions, 0 failures`.
- Red-first after adding the Mac Turkish case before implementation:
  `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  failed with `1 test files, 1095 assertions, 1 failures` because
  `x-mac-turkish` still fell back to `utf-8-repaired`.
- Final focused verification:
  `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  passed with `1 test files, 1108 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  passed with `charset unicode handoff self-test ok`.
- Syntax checks:
  `php -l lanes/pandoc/src/UnicodeText.php`,
  `php -l lanes/pandoc/tests/UnicodeTextTest.php`, and
  `php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`
  reported no syntax errors.
- Lane JSON validation:
  `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  returned `pandoc json ok`.
- `git diff --check -- lanes/pandoc` passed with no whitespace errors.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP `UnicodeText`
decoding and display-width helpers, `MarkdownReader`, `WordPressBlockWriter`,
the focused Unicode tests, and the existing WordPress charset handoff example.

## Non-Overlap

This does not repeat accepted MacRoman, Mac Cyrillic, Mac Greek, ISO-8859-9,
Windows-1254, Windows-1258, TIS-620/Windows-874, IBM857, IBM862,
Shift_JIS/EUC-JP/ISO-2022-JP, Big5/GBK/GB18030/EUC-KR/HZ-GB-2312, Indic/
Myanmar/Khmer/Thai/Lao display clusters, Unicode separator wrapping, or
format-control display-width slices. It is limited to the bounded Mac Turkish
source-byte decoder plus WordPress charset audit coverage.

## Next

Choose a non-overlapping charset or Unicode layout gap such as IBM775 Baltic
OEM source imports, Mac Icelandic/Croatian/Romanian byte decoding, or
additional Unicode line-break metadata.
