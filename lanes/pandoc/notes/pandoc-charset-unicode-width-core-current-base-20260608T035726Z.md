# Pandoc Charset/Unicode Width Current Base

Slice: `pandoc-charset-unicode-width-core-current-base-20260608T035726Z`
Base accepted HEAD: `e0a13ef9a780753d5899fbbc435cefb0324e5b29`

## Behavior

Added bounded UCS-2 label support for Pandoc byte-source handoff:

- `ucs-2` now defaults through the existing UTF-16LE path, matching the lane's current UTF-16 default-without-BOM behavior.
- `ucs-2le` maps to the existing UTF-16LE decoder.
- `ucs-2be` maps to the existing UTF-16BE decoder.

This deliberately reuses `UnicodeText::decodeUtf16()` so surrogate, truncation, repair-count, sourceEncoding metadata, Markdown AST handoff, WordPress block output, and display-width accounting stay on the existing native path.

## Non-Overlap

No active `port-pandoc-*.needs-lane-rework.md` note existed for this lane before editing. This slice avoids the recently accepted charset clusters for Windows-1256 Arabic, ISO-8859-9 Turkish, ISO-8859-8 Hebrew, ISO-8859-3 Latin-3, HZ-GB-2312, Shift_JIS, GB18030, and Unicode display-width cluster handling. It does not run Pandoc, Cabal/Haskell runners, external charset converters, browser renderers, online services, live provider tests, or live-service provider tests.

The local upstream cache path referenced by the lane manifest was not present in this isolated worktree environment, so the implementation is grounded in the existing lane manifest, current `UnicodeText` UTF-16 behavior, and focused native PHP tests.

## Verification

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php` -> `1 test files, 783 assertions, 0 failures`.
- Red-first: same focused command after adding the UCS-2 test failed as expected because `ucs-2le` normalized to `utf-8-repaired`.
- Final: `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php` -> `1 test files, 797 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test` -> `charset unicode handoff self-test ok`.
- Root harness: not run - isolated micro-slice.

Focused delta: +1 PHP PASS case, +14 focused assertions. `lane-status.json` records `phpPass` 1540, and `UPSTREAM_TEST_MANIFEST.json` maps one additional native case (`1959` mapped).

## Dependency Closure

No new support component is needed. The slice reuses native `UnicodeText` byte decoding, `MarkdownReader` source metadata, `WordPressBlockWriter` output, and the existing charset handoff example. Remaining follow-up candidates should stay native and bounded, such as x-mac-cyrillic labels, additional declared-charset sniffing boundaries, or terminal-specific width policy handoff.
