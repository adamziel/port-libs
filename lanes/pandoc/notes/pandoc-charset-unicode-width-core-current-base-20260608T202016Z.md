# Pandoc Charset Unicode Width Core Current Base - IBM860

Micro-slice: `pandoc-charset-unicode-width-core-current-base-20260608T202016Z`
Base accepted HEAD: `e804d88dd32d5db061bbd8258db113c523e8f8c3`

## Behavior

Added bounded IBM860/CP860 DOS Portuguese byte decoding on the native `UnicodeText` path. The slice recognizes `cp860`, `ibm860`, `dos860`, `xcp860`, `oem860`, and `csibm860` aliases, decodes the CP860 Portuguese high-byte accents/punctuation/currency bytes, and reuses the existing IBM437 mapping for the shared box-drawing half of the table.

Source truth was the local static Tcl encoding table at `/usr/share/tcl9.0/encoding/cp860.enc`; no external charset converter or Pandoc/Haskell runner was executed.

## Evidence

- Rework notes: no `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md` files existed for this lane.
- Baseline focused check before edits: `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php` passed with `1 test files, 973 assertions, 0 failures`.
- Red-first focused check after adding the CP860 test only: `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php` failed at `cp860` alias fallback with `1 test files, 974 assertions, 1 failures`.
- Final focused check: `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php` passed with `1 test files, 984 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test` passed with `charset unicode handoff self-test ok`.
- PHP lint: `php -l lanes/pandoc/src/UnicodeText.php && php -l lanes/pandoc/tests/UnicodeTextTest.php && php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php` passed with no syntax errors.
- JSON validation: `php -r 'json_decode(... lane-status.json ...); json_decode(... UPSTREAM_TEST_MANIFEST.json ...);'` passed with `pandoc json ok`.
- Whitespace check: `git diff --check -- lanes/pandoc` passed with no output.

## Status Delta

- `lane-status.json` `phpPass`: `1802` -> `1803`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2225` -> `2226`.
- Charset/Unicode inventory: `mappedCharsetUnicodeWidthCoreCases` `9` -> `10`, `charsetUnicodeWidthCoreAssertions` `65` -> `76`.

## Dependency Closure

No new support component is needed. The slice reuses native `UnicodeText` single-byte decoding, `MarkdownReader::readBytes()`, and `WordPressBlockWriter` output paths. It does not require Pandoc, Cabal, Haskell test binaries, external charset converters, browser renderers, online services, or live-service provider tests.

## Non-Overlap

This avoids the accepted Windows-874 Thai, Windows-1256 Arabic, IBM437, IBM850, IBM852, IBM866, HZ-GB-2312, Shift_JIS/Windows-31J, ISO-8859-7, ISO-8859-8, ISO-8859-3, and Unicode display-width cluster slices. Suggested next non-overlapping charset follow-up: CP863/CP865 or another legacy charset/display-width gap not already present in `UnicodeTextTest.php`.
