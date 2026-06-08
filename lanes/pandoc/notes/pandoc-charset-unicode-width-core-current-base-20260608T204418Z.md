# Pandoc Charset/Unicode Width Core - IBM863 Source Bytes

Slice: `pandoc-charset-unicode-width-core-current-base-20260608T204418Z`
Base accepted HEAD: `6479f65c1465d77f871d7146aaaa2d022aa27e3f`

## Behavior

- Added bounded native IBM863/CP863 DOS Canadian French byte decoding in `UnicodeText`.
- Recognized `cp863`, `ibm863`, `dos863`, `xcp863`, `oem863`, and `csibm863` aliases.
- Preserved CP863 Canadian French accented letters, punctuation, fractions, and currency symbols while reusing IBM437 for the shared DOS drawing/symbol half of the table.
- Added MarkdownReader/WordPressBlockWriter coverage for source encoding metadata and default/wide display-width evidence.

## Source Truth

The byte table is copied from the local static Tcl encoding table:

- `/usr/share/tcl9.0/encoding/cp863.enc`

No Pandoc, Cabal solver/build/test command, Haskell runner, external charset converter, browser renderer, online service, live provider test, or live-service provider test was executed.

## Evidence

- No `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md` rework notes existed for this lane.
- Baseline focused check before edits:
  - `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 998 assertions, 0 failures`
- Red-first check after adding the CP863 test before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 999 assertions, 1 failures`; `cp863` decoded as `utf-8-repaired`.
- Final focused check after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 1010 assertions, 0 failures`
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`
- PHP lint passed for:
  - `lanes/pandoc/src/UnicodeText.php`
  - `lanes/pandoc/tests/UnicodeTextTest.php`
  - `lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`
- JSON validation passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed.

## Status Delta

- `lane-status.json` `phpPass`: `1824` -> `1825`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2248` -> `2249`.
- Charset/Unicode inventory:
  - `mappedCharsetUnicodeWidthCoreCases`: `9` -> `10`
  - `charsetUnicodeWidthCoreAssertions`: `65` -> `77`

## Dependency Closure

No new support component is needed. This slice reuses native `UnicodeText` single-byte decoding, `MarkdownReader::readBytes()` sourceEncoding metadata, `WordPressBlockWriter`, focused `UnicodeTextTest.php` coverage, and the existing WordPress charset handoff example.

## Non-Overlap

This maps a distinct IBM863/CP863 DOS Canadian French import path. It does not repeat accepted CP437, CP850, CP852, CP860, CP866, Windows-1256, ISO-8859-3/7/8/9, Shift_JIS/Windows-31J, HZ-GB-2312, Mac Greek/Cyrillic, UTF-8 malformed scalar repair, or Unicode display-width cluster slices.

Root harness: not run - isolated micro-slice.
