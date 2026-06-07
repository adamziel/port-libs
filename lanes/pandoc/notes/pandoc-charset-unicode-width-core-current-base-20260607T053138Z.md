# Pandoc Charset Unicode Width Core - KOI8-U Ukrainian Source Bytes

Slice: `pandoc-charset-unicode-width-core-current-base-20260607T053138Z`
Base accepted HEAD: `9f18ba88ee76386e943df1faf4ad3dc5a3241d77`

## Change

- Added bounded native KOI8-U byte decoding in `UnicodeText` by layering the Ukrainian override byte slots on the existing KOI8-R map.
- Added `koi8-u`, `koi8u`, and `cskoi8u` canonical source-encoding handling.
- Preserved decoded source-encoding metadata through `MarkdownReader` and `WordPressBlockWriter`.
- Extended the WordPress charset handoff example with a KOI8-U audit row.

## Source Truth

- KOI8-U Ukrainian mapping source: RFC 2319, `https://www.rfc-editor.org/rfc/rfc2319`.
- Cross-check reference: WHATWG `index-koi8-u`, `https://encoding.spec.whatwg.org/index-koi8-u.txt`.
- This bounded slice ports the RFC 2319 KOI8-U Ukrainian override byte slots `A4`, `A6`, `A7`, `AD`, `B4`, `B6`, `B7`, and `BD`; all shared Cyrillic, box-drawing, ASCII, and control bytes continue through the existing KOI8-R decoder path.

## Evidence

- Baseline focused test before the patch: `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php` passed with `1 test files, 672 assertions, 0 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php` passed with `1 test files, 684 assertions, 0 failures`.
- Assertion delta: `+1` PHP PASS case / `+12` focused assertions.
- WordPress smoke: `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test` passed with `charset unicode handoff self-test ok`.
- PHP lint passed for `lanes/pandoc/src/UnicodeText.php`, `lanes/pandoc/tests/UnicodeTextTest.php`, and `lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`.
- JSON validation passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- Whitespace check passed: `git diff --check -- lanes/pandoc` produced no output.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP `UnicodeText` byte decoding, `MarkdownReader` source metadata, `WordPressBlockWriter` output, and the focused lane PHP harness. Full generated charset tables, declared HTML/XML charset sniffing, bidi layout shaping, terminal-profile-specific width variants, external charset converters, and upstream Pandoc Haskell runner parity remain out of scope.

## Non-Overlap

This slice does not repeat accepted BOM/UTF repair, Windows-1250/1251/1253, ISO-8859-1/2/3/4/5/6/7/8/9/10/13/14/15/16, TIS-620, MacRoman, KOI8-R, Shift_JIS/EUC-JP/ISO-2022-JP, Big5/GBK/GB18030/EUC-KR/HZ, normalization, emoji width, Indic virama, Myanmar virama, or Khmer coeng coverage.

## Follow-Up

Potential next charset gaps remain Windows-1254/1257, declared HTML/XML charset sniffing, bidi-review metadata, and any separately scoped KOI8-U/WHATWG extension byte differences not covered by the RFC 2319 Ukrainian override slice. Do not execute Pandoc, Cabal, Haskell runners, external charset converters, browser renderers, online services, live provider tests, or live-service provider tests for those support-library slices.
