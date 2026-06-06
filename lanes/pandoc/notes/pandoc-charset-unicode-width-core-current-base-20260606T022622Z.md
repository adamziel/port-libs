# Pandoc Charset/Unicode Width Core - KOI8-R Cyrillic Source Bytes

Slice: `pandoc-charset-unicode-width-core-current-base-20260606T022622Z`
Base: `764a6c14f1ba73661d0d83d3e39c8d1e9ab39f7f`

## Change

- Added a bounded native KOI8-R decoder table for bytes 0x80-0xff, covering
  the Cyrillic upper/lowercase alphabet, Yo/yo, copyright sign, and the legacy
  line-drawing characters that commonly appear in KOI8-R text fixtures.
- Added KOI8-R label aliases (`koi8r`, `cskoi8r`, and `koi8`) while preserving
  canonical source metadata as `koi8-r`.
- Added focused Markdown-to-AST and WordPress block coverage for a KOI8-R
  source document whose decoded heading, paragraph, line drawing characters,
  and display width need to survive the existing Unicode handoff path.
- Extended the WordPress charset handoff smoke with a `KOI8-R source` audit
  row carrying decoded text, canonical source encoding, and narrow/wide
  display-width evidence.

## Source Truth

The bounded source truth is the KOI8-R single-byte mapping shape: ASCII bytes
pass through unchanged and bytes 0x80-0xff map through the KOI8-R legacy
Cyrillic and box-drawing table instead of being treated as malformed UTF-8.
This slice does not ingest generated charset indexes or add online converter
behavior.

No current-base pandoc rework note was present. No Pandoc, Cabal solver/build/
test command, Haskell runner, Word, LibreOffice, `zip`/`unzip`, TeX/PDF
engine, browser renderer, online sanitizer, online service, or live provider
test was executed.

## Red-First Evidence

After adding the KOI8-R focused case and before implementation:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 497 assertions, 1 failures`
  - Failure: `UnicodeText::decodeBytes(..., 'koi8-r')` fell back to
    `utf-8-repaired` instead of canonical `koi8-r`.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 506 assertions, 0 failures`
  - Delta: +1 focused PASS case / +10 assertions from the previous accepted
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

No new support component is needed. This slice reuses the existing native PHP
`UnicodeText`, `MarkdownReader`, `MarkdownWriter`, and `WordPressBlockWriter`
support path. KOI8-U/KOI8-RU variants, ISO-8859-5, full generated charset
indexes, declared HTML/XML charset sniffing, terminal-profile-specific width
variants, broader Unicode property-table refreshes, and full upstream Pandoc
Haskell runner parity remain separate bounded follow-up work.

## Non-Overlap

This does not overlap accepted UTF-8 repair, UTF-16/UTF-32 BOM handling,
Windows-1252/1250/1251, ISO-8859-1/2/15, MacRoman, Shift_JIS/Windows-31J,
EUC-JP, ISO-2022-JP, Big5, bounded GBK/GB2312/GB18030, EUC-KR, HZ-GB-2312,
Unicode normalization, emoji presentation and tag/ZWJ clusters, supplementary
East Asian wide ranges, ambiguous-width policy, Unicode separator wrapping,
default-ignorable controls, prepended format-control zero-width accounting,
Indic virama clusters, Myanmar/Khmer conjuncts, Markdown/HTML reader behavior,
table geometry, DOCX/ODF/EPUB/PDF, syntax-highlighting, CSL/BibTeX, YAML,
doctemplate, ZIP/OPC, or upstream-runner dependency audit slices.
