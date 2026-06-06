# Pandoc Charset/Unicode Width Core - TIS-620 Thai Source Bytes

Slice: `pandoc-charset-unicode-width-core-current-base-20260606T141507Z`
Base: `78406f3d5dccc3d1a3f450862a98b46c50437d15`

## Change

- Added bounded TIS-620 / ISO-8859-11 Thai label recognition to `UnicodeText`,
  including `tis-620`, `tis620`, `tis620-2533`, `iso-ir-166`, `iso-8859-11`,
  and `thai` style labels while preserving canonical source metadata as
  `tis-620`.
- Added native single-byte decoding for the TIS-620 Thai byte rows used by
  legacy Thai Markdown imports.
- Kept undefined Thai high-byte slots explicit as U+FFFD repairs before
  Markdown or WordPress handoff.
- Extended the WordPress charset handoff smoke with a TIS-620 Thai audit row
  carrying canonical source encoding and display-width evidence.

## Source Truth

The bounded source-truth contract is the TIS-620 / ISO-8859-11 Thai single-byte
layout: ASCII bytes pass through unchanged, byte `0xA0` maps to no-break space,
bytes `0xA1`-`0xDA` map to Unicode `U+0E01`-`U+0E3A`, bytes `0xDF`-`0xFB` map
to Unicode `U+0E3F`-`U+0E5B`, and undefined high-byte gaps become U+FFFD
repairs. This slice does not ingest generated charset indexes or use external
charset converters for progress.

No current-base Pandoc rework note was present. No Pandoc, Cabal solver/build/
test command, Haskell runner, external charset converter, browser renderer,
online sanitizer, online service, live provider test, or live-service provider
test was executed.

## Red-First Evidence

Baseline before adding the new focused case:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 567 assertions, 0 failures`

Unsupported-label probe before implementation:

- `php -r 'require "tools/bootstrap.php"; $d=PortLibs\Pandoc\UnicodeText::decodeBytes("# \xE4\xB7\xC2\n\n\xE0\xB9\xD7\xE9\xCD\xCB\xD2 \xE0\xCD\xA1\xCA\xD2\xC3.", "tis-620"); var_export([$d["encoding"], $d["repairs"], $d["text"]]); echo "\n";'`
  - Result: canonical encoding `utf-8-repaired`, `13` repairs.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 580 assertions, 0 failures`
  - Delta: `+1` focused PASS case / `+13` assertions.
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`
- `php -l lanes/pandoc/src/UnicodeText.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`
  - Result: no syntax errors.
- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " json ok\n"; }'`
  - Result: both Pandoc JSON files decoded successfully.
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `UnicodeText`,
`MarkdownReader`, `MarkdownWriter`, `WordPressBlockWriter`, the existing
WordPress charset handoff example, and the lane-local focused PHP harness.
ISO-8859-3/4/10/13/14/16, KOI8-U, Windows-874, generated full charset indexes,
declared HTML/XML charset sniffing, bidi layout shaping, terminal-profile-
specific width variants, and full upstream Pandoc Haskell runner parity remain
separate bounded follow-up work.

## Non-Overlap

This does not overlap accepted UTF-8 repair, UTF-16/UTF-32 BOM handling,
Windows-1252/1250/1251, ISO-8859-1/2/5/6/7/8/15, MacRoman, KOI8-R,
Shift_JIS/Windows-31J, EUC-JP, ISO-2022-JP, Big5, GBK/GB18030, EUC-KR,
HZ-GB-2312, Unicode normalization, emoji presentation and tag/ZWJ clusters,
supplementary/rare East Asian wide ranges, BMP/geometric emoji symbols,
ambiguous-width policy, Unicode soft-break wrapping, Unicode separator
wrapping, default-ignorable controls, prepended format-control zero-width
accounting, Indic virama clusters, Myanmar/Khmer conjuncts, Markdown/HTML
reader behavior, XML/HTML5 DOM, table geometry, DOCX/ODF/EPUB/PDF,
syntax-highlighting, CSL/BibTeX, YAML, doctemplate, ZIP/OPC, or upstream-runner
dependency audit slices.
