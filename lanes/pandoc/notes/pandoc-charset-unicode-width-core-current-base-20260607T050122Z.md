# Pandoc Charset Unicode Width Core - ISO-8859-16 Latin-10 Source Bytes

Slice: `pandoc-charset-unicode-width-core-current-base-20260607T050122Z`
Base accepted HEAD: `9b043bdd6c45d131a015a5b5e5edb83ea842ab3a`

## Change

- Added bounded native ISO-8859-16 / Latin-10 byte decoding in `UnicodeText`.
- Added aliases for `latin10`, `latin-10`, `l10`, `iso-ir-226`, `csisolatin10`, and `iso-8859-16:2001`.
- Preserved decoded source-encoding metadata through `MarkdownReader` and `WordPressBlockWriter`.
- Extended the WordPress charset handoff example with a Latin-10 audit row.

## Source Truth

- Mapping source: Unicode Consortium ISO-8859-16 mapping table, `https://www.unicode.org/Public/MAPPINGS/ISO8859/8859-16.TXT`.
- ASCII and shared Latin-1 byte slots continue through the existing single-byte decoder path.
- Non-Latin-1 ISO-8859-16 byte slots are represented by a bounded PHP map rather than shelling out to an external converter.

## Red-First Evidence

- Baseline focused test before the patch: `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php` passed with `1 test files, 661 assertions, 0 failures`.
- Unsupported probe before implementation: `iso-ir-226` decoded as `utf-8-repaired` with `5` repairs for Romanian and Euro byte slots.

## Verification

- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php` passed with `1 test files, 672 assertions, 0 failures`.
- Assertion delta: `+1` PHP PASS case / `+11` focused assertions.
- WordPress smoke: `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test` passed with `charset unicode handoff self-test ok`.
- PHP lint passed for `lanes/pandoc/src/UnicodeText.php`, `lanes/pandoc/tests/UnicodeTextTest.php`, and `lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`.
- JSON validation passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- Whitespace check passed: `git diff --check -- lanes/pandoc` produced no output.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP `UnicodeText` byte decoding, `MarkdownReader` source metadata, `WordPressBlockWriter` output, and the focused lane PHP harness. Full generated charset tables, declared HTML/XML charset sniffing, bidi layout shaping, terminal-profile-specific width variants, and upstream Pandoc Haskell runner parity remain out of scope.

## Non-Overlap

This slice does not repeat accepted BOM/UTF repair, Windows-1250/1251/1253, ISO-8859-1/2/3/4/5/6/7/8/9/10/13/14/15, TIS-620, MacRoman, KOI8-R, Shift_JIS/EUC-JP/ISO-2022-JP, Big5/GBK/GB18030/EUC-KR/HZ, normalization, emoji width, Indic virama, Myanmar virama, or Khmer coeng coverage.

## Follow-Up

Potential next charset gaps remain KOI8-U, Windows-1254/1257, declared HTML/XML charset sniffing, and bidi-review metadata. Do not execute Pandoc, Cabal, Haskell runners, external charset converters, browser renderers, online services, live provider tests, or live-service provider tests for those support-library slices.
