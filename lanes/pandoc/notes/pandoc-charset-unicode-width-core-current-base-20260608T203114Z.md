# pandoc-charset-unicode-width-core-current-base-20260608T203114Z

## Slice

- Lane: `pandoc`
- Accepted base: `bb37a42dff2002404bb134df44da31542c787c36`
- Upstream source truth: Pandoc readers/writers must preserve Unicode text while writers align and wrap by visible display columns. This slice ports the bounded support-library behavior needed for ZWJ text on the native PHP WordPress/Markdown handoff path, without invoking Pandoc or Haskell runners.

## Behavior

`UnicodeText::graphemes()` no longer lets every plain zero-width joiner collapse the following scalar into the same display cluster. A ZWJ now requests the next cluster only when the current visible base is emoji-capable; the existing Indic virama ZWJ path remains intact. This keeps `A + ZWJ + B` at width 2 and `CJK + ZWJ + CJK` at width 4, while emoji ZWJ sequences and bounded Indic conjuncts still stay single display clusters.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php` passed with `1 test files, 987 assertions, 0 failures`.
- Red-first: the focused plain/CJK ZWJ case failed with `1 test files, 988 assertions, 1 failures`; `A + ZWJ + B` collapsed into one grapheme.
- Final: `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php` passed with `1 test files, 1003 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test` passed.

## Status Delta

- Added one mapped Charset/Unicode display-width case.
- Added one PHP PASS case and 16 focused assertions in `UnicodeTextTest.php`.
- Updated `lane-status.json` `phpPass` from `1816` to `1817`.
- Updated `UPSTREAM_TEST_MANIFEST.json` mapped denominator from `2240` to `2241`, `mappedCharsetUnicodeWidthCoreCases` from `9` to `10`, `charsetUnicodeWidthCoreAssertions` from `65` to `81`, and `mappedUnicodeDisplayBreakpointChecks` from `8` to `9`.

## Dependency Closure

No new support component is needed. The slice reuses native `UnicodeText` grapheme segmentation, display-width accounting, wrapping/splitting helpers, focused PHP tests, and the lane-local WordPress charset handoff example. No Pandoc, Cabal/Haskell runner, external charset converter, browser renderer, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted charset byte-decoding slices such as UCS-2 labels, Windows-1256 Arabic, ISO-8859 Greek/Hebrew/Turkish, HZ-GB-2312, Shift_JIS, GBK/GB18030, Mac Cyrillic/Greek, DOS code pages, or prior emoji/Indic/Myanmar/Khmer display-width cases. It is limited to plain non-emoji ZWJ display splitting while preserving already-covered emoji ZWJ and Indic virama behavior.

## Follow-Up

Choose a non-overlapping native Unicode/charset gap such as terminal-specific width policy handoff, additional declared-charset sniffing boundaries, or another bounded charset label not already covered.
