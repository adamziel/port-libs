# Pandoc Charset/Unicode Width Core - IBM852 Source Bytes

Slice: `pandoc-charset-unicode-width-core-current-base-20260608T200242Z`
Base accepted HEAD: `e4416a27234df3582c58620f35f477531567f5a3`

## Behavior

- Added bounded native IBM852/CP852 DOS Central European byte decoding in `UnicodeText`.
- Added aliases for CP852-style source names including `cp852`, `ibm852`, `dos852`, `cspc852`, and `csibm852`.
- Preserved Central European accents, line-drawing bytes, soft hyphen, non-breaking space, source encoding metadata, display-width accounting, Markdown reader handoff, and WordPress charset audit-table output.

## Source Truth

The byte table is copied from the local static Tcl encoding table:

- `/usr/share/tcl9.0/encoding/cp852.enc`

No Pandoc, Cabal/Haskell runner, external charset converter, browser renderer, online service, live provider test, or live-service provider test was executed.

## Evidence

- Baseline before the new case: `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php` passed with `1 test files, 942 assertions, 0 failures`.
- Red-first test-only probe failed with `1 test files, 943 assertions, 1 failures`; the decoder returned `utf-8-repaired` instead of canonical `ibm852`.
- Final focused test after implementation passed with `1 test files, 955 assertions, 0 failures`.
- WordPress example smoke passed: `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`.
- Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses `UnicodeText`, `MarkdownReader`, `WordPressBlockWriter`, and the lane-local charset handoff example.

## Non-Overlap

This maps a distinct IBM852/CP852 DOS Central European import path and does not overlap the accepted IBM437, IBM850, IBM866, ISO-8859-2, ISO-8859-3, ISO-8859-7, ISO-8859-8, ISO-8859-9, Windows-1250, Windows-1256, Shift_JIS/Windows-31J, Big5/GBK, HZ-GB-2312, Mac Cyrillic, emoji, Indic, Myanmar, Khmer, or general Unicode display-width slices.

## Follow-Up

Next charset/Unicode work should choose a non-overlapping legacy charset or display-width gap, such as CP860/CP863/CP865 DOS sources, BOM-aware handoff diagnostics, or additional Unicode line-break metadata.
