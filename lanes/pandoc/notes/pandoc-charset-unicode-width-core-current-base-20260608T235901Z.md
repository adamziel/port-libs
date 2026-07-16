# Pandoc Charset Unicode Width Current Base - Mac Icelandic

Slice: `pandoc-charset-unicode-width-core-current-base-20260608T235901Z`
Base: `98e36d1bfbcd2aff359b39b4120999431e5e0fde`

## Source Truth

- Used the local Tcl encoding inventory at `/usr/share/tcl9.0/encoding/macIceland.enc`.
- The table is MacRoman-compatible for punctuation/euro/private-use bytes and overrides the Icelandic slots:
  - `0xA0 => U+00DD` (`Ý`)
  - `0xDC => U+00D0` (`Ð`)
  - `0xDD => U+00F0` (`ð`)
  - `0xDE => U+00DE` (`Þ`)
  - `0xDF => U+00FE` (`þ`)
  - `0xE0 => U+00FD` (`ý`)
- The focused test also verifies shared MacRoman behavior for `0xD5` right quote, `0xDB` euro, and `0xF0` private-use handoff.

## Implementation Delta

- `UnicodeText` now normalizes `maciceland`, `mac-iceland`, `xmaciceland`, `x-mac-iceland`, `macicelandic`, `mac-icelandic`, `xmacicelandic`, and `x-mac-icelandic` to `mac-iceland`.
- Mac Icelandic decoding reuses the MacRoman table plus the six local Tcl overrides above.
- `MarkdownReader` source encoding metadata and `WordPressBlockWriter` output now preserve Mac Icelandic decoded text and display width.
- The WordPress charset handoff example includes a Mac Iceland audit row with `mac-iceland:51/62`.

## Verification

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - `1 test files, 1152 assertions, 0 failures`
- Red-first after adding the Mac Icelandic test only:
  - `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - `1 test files, 1153 assertions, 1 failures`
  - Failure: `x-mac-icelandic` normalized as `utf-8-repaired`.
- Final focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - `1 test files, 1166 assertions, 0 failures`
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - `charset unicode handoff self-test ok`
- Syntax/JSON checks:
  - `php -l lanes/pandoc/src/UnicodeText.php`
  - `php -l lanes/pandoc/tests/UnicodeTextTest.php`
  - `php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`
  - `php -r` JSON decode of `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- Diff hygiene:
  - `git diff --check -- lanes/pandoc`
  - `grep -n '[[:blank:]]$' lanes/pandoc/notes/pandoc-charset-unicode-width-core-current-base-20260608T235901Z.md` returned no matches.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP charset decoder, Markdown byte-source handoff, WordPress block writer path, and local Tcl static encoding table as bounded source truth. No Pandoc, Cabal, Haskell runner, Word, LibreOffice, external converter, browser renderer, online service, or live provider test was executed.

## Next Non-Overlap

A next charset slice could cover another local Tcl Mac variant such as `macCroatian`, `macRomania`, `macCentEuro`, or `macThai`, avoiding accepted ISO-8859, Windows, IBM DOS, KOI8, MacRoman, MacTurkish, MacCyrillic, MacGreek, and this Mac Icelandic mapping.
