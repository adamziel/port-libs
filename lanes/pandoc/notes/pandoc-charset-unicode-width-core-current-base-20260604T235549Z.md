# Pandoc Charset Unicode Width Core Current Base

Slice: `pandoc-charset-unicode-width-core-current-base-20260604T235549Z`

Base accepted HEAD: `eff956eb3c3d98bcf310450b72573c553ea980f3`

## Behavior Added

- Normalized decoded CRLF and legacy CR line endings to LF in
  `UnicodeText::decodeBytes()` after UTF-8, UTF-16, Windows-1252, or
  ISO-8859-1 decoding.
- Added `UnicodeText::normalizeLineEndings()` so Pandoc byte-source readers can
  share the same bounded line-ending repair primitive.
- Preserved source-line-ending audit metadata on `MarkdownReader::readBytes()`
  documents as `sourceLineEndings` only when normalization occurred, without
  changing the existing `sourceEncoding` metadata shape.
- Replaced HTML-entity codepoint emission in the charset decoder with direct
  UTF-8 codepoint encoding so decoded control characters such as CR are real
  characters before normalization.
- Extended the WordPress charset handoff smoke to prove CRLF/CR reviewer exports
  are normalized and exposed in the audit table without leaking raw carriage
  returns into WordPress block text.

## Source Truth

This slice owns the support-library row named by the supervisor:
`pandoc-charset-unicode-width-core-*`, covering byte decoding, Unicode repair,
and display-width behavior needed by Pandoc readers/writers. The previous
charset slice explicitly left carriage-return byte-source filtering as a
follow-up. This patch ports that bounded behavior into the native PHP
byte-source path without attempting full Pandoc runner parity or broader line
wrapping/layout behavior.

## Verification

Pre-slice focused `UnicodeTextTest.php` passed with `1 test files, 39
assertions, 0 failures`. The red-first test failed before implementation:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result before implementation: failed because decoded text still contained
    raw carriage returns.

Post-implementation verification:

- `php -l lanes/pandoc/src/UnicodeText.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/src/MarkdownReader.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 47 assertions, 0 failures`
  - Delta: `+8` focused assertions and `+1` focused PHP PASS case.
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `16 test files, 4142 assertions, 0 failures`
- `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice extends the existing native PHP
`UnicodeText` helper and reuses `MarkdownReader`, `MarkdownWriter`,
`WordPressBlockWriter`, and AST paths. It does not invoke Pandoc, Cabal, Haskell
test binaries, citeproc, BibTeX, Biber, Word, LibreOffice, `zip`, `unzip`,
external template engines, TeX/PDF engines, browser renderers, roff, Typst,
MathJax, KaTeX, or online services.

## Non-Overlap

This patch does not repeat accepted XML/HTML5 DOM support, YAML metadata,
ZIP/OPC, relationship graphs, gzip/tar/LZ4 streams, doctemplate partials, CSL or
BibTeX processing, DOCX/ODT parsing, EPUB3 package handoff, legacy DOC/CFB
extraction, table geometry span normalization, Math/TeX conversion, PDF engine
handoff planning, display-width breakpoint splitting, or upstream-runner
dependency audit work. It only extends the charset/Unicode-width support
primitive with decoded CRLF/CR line-ending normalization and direct codepoint
encoding needed for that behavior.

## Follow-Up

Keep full Unicode normalization forms, East Asian ambiguous-width policy,
terminal-profile-specific emoji variation width, deeper HTML/XML parser charset
negotiation, and full line-wrapping/layout behavior as separate bounded slices.
