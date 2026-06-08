# Pandoc Charset/Unicode Width Core - IBM850 DOS Western European Source Bytes

Slice: `pandoc-charset-unicode-width-core-current-base-20260608T192011Z`
Base: `e97bdf9331ef05dac3f6237d837a28df8dd53eb5`

## Change

- Added bounded native IBM850/CP850 single-byte decoding in `UnicodeText`, including common aliases such as `cp850`, `ibm850`, `dos850`, `oem850`, and `cspc850multilingual`.
- Preserved ASCII bytes unchanged and mapped the CP850 high-byte table for DOS Western European accented Latin characters, fractions, box drawing, soft hyphen, double low line, and NBSP.
- Added a focused Markdown-to-WordPress test that distinguishes CP850 from IBM437 on bytes that have different meanings between the two DOS code pages.
- Extended the WordPress charset handoff example with an IBM850 audit row and display-width metadata.

## Source Truth

This slice ports the bounded support-library contract for DOS Western European CP850/IBM850 byte decoding needed before Pandoc readers/writers can preserve legacy Markdown source text. It does not shell out to Pandoc, Cabal, Haskell runners, external charset converters, browser renderers, online services, live provider tests, or live-service provider tests.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php` -> `1 test files, 922 assertions, 0 failures`.
- Red-first: the new CP850 test failed before implementation with `1 test files, 923 assertions, 1 failures`; CP850 decoded as `utf-8-repaired` instead of `ibm850`.
- Final: `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php` -> `1 test files, 934 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test` -> `charset unicode handoff self-test ok`.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new native PHP support component is needed. The slice reuses `UnicodeText`, `MarkdownReader`, `WordPressBlockWriter`, and the existing charset WordPress handoff example.

## Non-Overlap

This slice avoids the accepted Shift_JIS/Windows-31J, GBK, HZ-GB-2312, ISO-8859-3, ISO-8859-7, ISO-8859-8, Windows-1256, IBM437, IBM866, Unicode format-control, Indic virama, Myanmar/Khmer conjunct, and UTF-16 malformed guard surfaces. A useful follow-up would be another bounded DOS or legacy charset family such as CP852, CP860, CP863, or CP865.
