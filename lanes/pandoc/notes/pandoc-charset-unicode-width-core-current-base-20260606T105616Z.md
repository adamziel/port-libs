# Pandoc Charset/Unicode Width Core - ISO-8859-8 Hebrew Source Bytes

Slice: `pandoc-charset-unicode-width-core-current-base-20260606T105616Z`
Base: `acaa655f41a326695b1b8edaa14a30da83e3ddae`

## Change

- Added bounded ISO-8859-8/Hebrew label recognition to `UnicodeText`, including
  `iso-ir-138`, `hebrew`, `csisolatinhebrew`, `iso-8859-8-i`, logical, and
  visual aliases while preserving canonical metadata as `iso-8859-8`.
- Added native byte decoding for the ISO/IEC 8859-8 punctuation, Hebrew-letter,
  double-low-line, LRM, and RLM rows needed by legacy Hebrew Markdown imports.
- Kept undefined ISO-8859-8 high-byte slots explicit as U+FFFD repairs before
  Markdown or WordPress handoff.
- Extended the WordPress charset handoff smoke with an ISO-8859-8 Hebrew audit
  row carrying canonical source encoding and display-width evidence.

## Source Truth

The bounded source truth is the Unicode Consortium mapping table for
ISO/IEC 8859-8:1999 to Unicode:
`https://www.unicode.org/Public/MAPPINGS/ISO8859/8859-8.TXT`.
This slice ports only the native PHP byte-decoding/display-width support needed
for Hebrew review packets and does not ingest full generated charset indexes or
implement a bidi layout engine.

No current-base Pandoc rework note was present. No Pandoc, Cabal solver/build/
test command, Haskell runner, external charset converter, browser renderer,
online sanitizer, online service, live provider test, or live-service provider
test was executed.

## Red-First Evidence

After adding the ISO-8859-8 focused case and before implementation:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 542 assertions, 1 failures`
  - Failure: `UnicodeText::decodeBytes(..., 'iso-ir-138')` returned canonical
    `utf-8-repaired` instead of `iso-8859-8`.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 554 assertions, 0 failures`
  - Delta: `+1` focused PASS case / `+13` assertions from the previous accepted
    UnicodeText focused baseline.
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

No new support component is needed. This slice reuses native PHP `UnicodeText`,
`MarkdownReader`, `MarkdownWriter`, `WordPressBlockWriter`, the existing
WordPress charset handoff example, and the lane-local focused PHP harness.
ISO-8859-3/4/6/10/13/14/16, KOI8-U variants, full generated charset indexes,
declared HTML/XML charset sniffing, terminal-profile-specific width variants,
locale-specific line breaking, and full upstream Pandoc Haskell runner parity
remain separate bounded follow-up work.

## Non-Overlap

This does not overlap accepted UTF-8 repair, UTF-16/UTF-32 BOM handling,
Windows-1252/1250/1251, ISO-8859-1/2/5/7/15, MacRoman, KOI8-R,
Shift_JIS/Windows-31J, EUC-JP, ISO-2022-JP, Big5, GBK/GB18030, EUC-KR,
HZ-GB-2312, Unicode normalization, emoji presentation and tag/ZWJ clusters,
supplementary/rare East Asian wide ranges, BMP/geometric emoji symbols,
ambiguous-width policy, Unicode soft-break wrapping, Unicode separator
wrapping, default-ignorable controls, prepended format-control zero-width
accounting, Indic virama clusters, Myanmar/Khmer conjuncts, Markdown/HTML
reader behavior, XML/HTML5 DOM, table geometry, DOCX/ODF/EPUB/PDF,
syntax-highlighting, CSL/BibTeX, YAML, doctemplate, ZIP/OPC, or
upstream-runner dependency audit slices.
