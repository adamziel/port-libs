# Pandoc Charset/Unicode Width Current-Base Slice

Micro-slice: `pandoc-charset-unicode-width-core-current-base-20260609T002118Z`
Base accepted HEAD: `72cabc3f4f492b184408152fdc147cadc8cc603f`

## Behavior

Added native Mac Central European (`macCentEuro`) single-byte decoding in `UnicodeText`.
The slice normalizes labels such as `x-mac-cent-euro`, `mac-ce`, and `maccenteuro`,
then decodes the high-byte table before Markdown and WordPress block handoff.

Source truth for the byte table was the local Tcl encoding map:
`/usr/share/tcl9.0/encoding/macCentEuro.enc`.

## Evidence

- Rework notes: no current `port-pandoc-*.needs-lane-rework.md` files existed.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  passed with `1 test files, 1166 assertions, 0 failures`.
- Red-first: `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  failed with `1 test files, 1167 assertions, 1 failures` because
  `x-mac-cent-euro` fell back to `utf-8-repaired`.
- Final: `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  passed with `1 test files, 1180 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  passed with `charset unicode handoff self-test ok`.

## Dependency Closure

No new support component is needed. This reuses native PHP `UnicodeText`,
`MarkdownReader`, `WordPressBlockWriter`, and the existing charset audit example.
No Pandoc, Cabal solver/build/test command, Haskell runner, external charset
converter, browser renderer, online service, live provider test, or
live-service provider test was executed.

## Non-Overlap

This avoids accepted ISO-8859, Windows, IBM/DOS, KOI8, MacRoman, MacTurkish,
MacCyrillic, MacGreek, and MacIceland charset slices. A next non-overlapping
charset slice could cover `macCroatian`, `macRomania`, `macThai`, or a bounded
Unicode display-width edge.
