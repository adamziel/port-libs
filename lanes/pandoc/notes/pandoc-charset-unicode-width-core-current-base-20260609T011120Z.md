# Pandoc Charset/Unicode Width Current-Base Slice

Micro-slice: `pandoc-charset-unicode-width-core-current-base-20260609T011120Z`
Base accepted HEAD: `09109401d59cee7a589aaf8125432abbe4aef718`

## Behavior

Added native MacRomania/Mac Romanian single-byte decoding in `UnicodeText`.
The slice normalizes labels such as `macromania`, `mac-romanian`, and
`x-mac-romanian` to canonical `mac-romania`, then applies the Romanian
MacRoman overrides before Markdown and WordPress block handoff.

Source truth for the byte table was the local Tcl encoding map:
`/usr/share/tcl9.0/encoding/macRomania.enc`.

## Evidence

- Rework notes: no current `port-pandoc-*.needs-lane-rework.md` files existed.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  passed with `1 test files, 1180 assertions, 0 failures`.
- Red-first: `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  failed with `1 test files, 1181 assertions, 1 failures` because
  `x-mac-romanian` fell back to `utf-8-repaired`.
- Final: `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  passed with `1 test files, 1194 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  passed with `charset unicode handoff self-test ok`.

## Dependency Closure

No new support component is needed. This reuses the native PHP `UnicodeText`
decoder, `MarkdownReader` byte-source handoff, `WordPressBlockWriter`, and the
existing WordPress charset audit example. No Pandoc, Cabal solver/build/test
command, Haskell runner, external charset converter, browser renderer, online
service, live provider test, or live-service provider test was executed.

## Non-Overlap

This avoids accepted ISO-8859, Windows, IBM/DOS, KOI8, MacRoman, MacTurkish,
MacCyrillic, MacGreek, MacIceland, and MacCentralEuropean charset slices. A
next non-overlapping charset slice could cover bounded `macCroatian` or
`macThai` decoding, or a distinct Unicode display-width edge.
