# Pandoc Charset/Unicode Width Core - ISO-8859-5 Cyrillic Decoding

Slice: `pandoc-charset-unicode-width-core-current-base-20260606T071208Z`
Base: `2c3874187ed49e9686f363014a3a498e09dbcd73`

## Change

- Added bounded ISO-8859-5 label recognition to `UnicodeText::decodeBytes()`
  for `iso-ir-144`, `cyrillic`, and `csisolatincyrillic` aliases.
- Added native single-byte ISO-8859-5 decoding for the contiguous Cyrillic
  uppercase/lowercase ranges, the special uppercase/lowercase rows, U+2116
  number sign, U+00A7 section sign, and U+00AD soft hyphen.
- Preserved canonical `iso-8859-5` source-encoding metadata through
  `MarkdownReader` and the WordPress charset review-table handoff.

## Source Truth

The bounded source-truth contract is the ISO-8859-5 single-byte Cyrillic layout:
ASCII bytes pass through, 0xB0-0xEF map to the contiguous Cyrillic alphabet
ranges, the A1-AF/F1-FF rows carry the non-contiguous Cyrillic letters and
symbols, and 0xAD remains soft hyphen for display-width accounting.

No current-base pandoc rework note was present. The pinned Pandoc upstream
checkout was not available in this isolated worktree or shared upstream cache,
so no upstream runner source was executed. No Pandoc, Cabal solver/build/test
command, Haskell runner, external charset converter, browser renderer, online
service, live provider test, or live-service provider test was executed.

## Red-First Evidence

Baseline before adding the new focused case:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 516 assertions, 0 failures`

After adding the ISO-8859-5 test and before implementation:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 517 assertions, 1 failures`
  - Failure: `UnicodeText::decodeBytes(..., 'iso-ir-144')` returned canonical
    `utf-8-repaired` instead of `iso-8859-5`.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 528 assertions, 0 failures`
  - Delta: `+1` focused PASS case / `+12` assertions.
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`
- `php -l lanes/pandoc/src/UnicodeText.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`
  - Result: no syntax errors.
- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " json ok\n"; }'`
  - Result: both pandoc JSON files decoded successfully.
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`UnicodeText`, `MarkdownReader`, `MarkdownWriter`, `WordPressBlockWriter`, the
charset handoff example, and lane-local manifest/status machinery. Full
HTML/XML declared-charset sniffing, KOI8-U and other single-byte families, full
generated CJK index-table ingestion, terminal-profile-specific emoji width
variants, broader Unicode data refreshes, and full upstream Pandoc Haskell
runner parity remain separate bounded follow-up work.

## Non-Overlap

This does not overlap accepted UTF-8 repair, UTF-16/UTF-32 BOM handling,
Windows-1252/1250/1251, ISO-8859-1/2/15, MacRoman, KOI8-R, Shift_JIS/
Windows-31J, EUC-JP, ISO-2022-JP, Big5, GBK/GB18030, EUC-KR, HZ-GB-2312,
Unicode normalization, emoji presentation and tag/ZWJ clusters,
supplementary/rare East Asian wide ranges, BMP/geometric emoji symbols,
ambiguous-width policy, Unicode soft-break wrapping, Unicode separator
wrapping, default-ignorable controls, prepended format-control zero-width
accounting, Indic virama clusters, Myanmar/Khmer conjuncts, Markdown/HTML
reader behavior, XML/HTML5 DOM, table geometry, DOCX/ODF/EPUB/PDF,
syntax-highlighting, CSL/BibTeX, YAML, doctemplate, ZIP/OPC, or
upstream-runner dependency audit slices.
