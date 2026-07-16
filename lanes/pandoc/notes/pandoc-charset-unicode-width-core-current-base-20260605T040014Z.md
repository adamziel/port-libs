# Pandoc Charset Unicode Width Core Current Base

Slice: `pandoc-charset-unicode-width-core-current-base-20260605T040014Z`

Base accepted HEAD: `a18cd7e7f0c3b2dde3f61187f659f01e7ea565cc`

## Behavior Added

- Made detected UTF-8, UTF-16LE, and UTF-16BE byte order marks authoritative
  over stale caller-supplied encoding hints in `UnicodeText::decodeBytes()`.
- `MarkdownReader::readBytes()` now records the actual BOM-selected decoder in
  `sourceEncoding` when imported bytes disagree with external charset metadata.
- Extended the WordPress charset handoff smoke with a BOM override audit row so
  review packets expose UTF-16BE content decoded correctly despite stale
  Windows-1252 hints.

## Source Truth

This slice owns the supervisor row `pandoc-charset-unicode-width-core-*`, which
covers byte decoding, Unicode repair, and display-width behavior needed by
Pandoc readers and writers. Earlier accepted charset notes covered BOM-aware
decoding, Windows-1252/ISO-8859-1 decoding, malformed UTF-8 repair, line-ending
normalization, Unicode normalization forms, display-width splitting/wrapping,
emoji presentation and tag clusters, ambiguous-width policy, soft breaks, and
default-ignorable display-width accounting. This patch tightens the byte-source
contract for stale external encoding metadata without changing display-width
behavior.

No current-base Pandoc rework note was present. No hydrated Pandoc checkout was
available in this worktree or the upstream cache, so this remains static
source-truth mapping plus focused native PHP tests rather than Haskell runner
parity.

## Verification

Pre-slice focused baseline:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 129 assertions, 0 failures`

Red-first focused check:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result before implementation: failed with `1 test files, 130 assertions,
    1 failures`
  - Failure: `lets unicode byte order marks override stale source encoding
    hints` expected encoding `utf-8`, actual `windows-1252`.

Post-implementation verification:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 142 assertions, 0 failures`
  - Delta: `+13` focused assertions and `+1` focused PHP PASS case.
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`
- `php -l lanes/pandoc/src/UnicodeText.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`
  - Result: no syntax errors.
- `php -r '$files = ["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"]; foreach ($files as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); fwrite(STDOUT, $file . " json ok\n"); }'`
  - Result: both pandoc JSON files decoded successfully.
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice extends the existing native PHP
`UnicodeText` helper and reuses the existing Markdown/WordPress charset handoff
path. It does not invoke Pandoc, Cabal, Haskell test binaries, citeproc,
BibTeX, Biber, Word, LibreOffice, `zip`, `unzip`, `tar`, `lz4`, external
template engines, TeX/PDF engines, browser renderers, roff, Typst, MathJax,
KaTeX, terminal probes, online sanitizers, or online services.

## Non-Overlap

This patch does not repeat accepted XML/HTML5 DOM support, YAML metadata,
ZIP/OPC, relationship graphs, gzip/tar/LZ4 streams, doctemplate partials,
CSL/BibTeX processing, DOCX/ODT parsing, EPUB3 package handoff, legacy DOC/CFB
extraction, table geometry span normalization, Math/TeX conversion, PDF engine
handoff planning, line-ending normalization, Unicode normalization,
display-width breakpoint splitting, display-column wrapping, emoji presentation
width, emoji tag sequences, default-ignorable width accounting, East Asian
ambiguous-width policy, Unicode soft-break wrapping, or upstream-runner
dependency audit work. It only extends the charset/Unicode byte decoder with
BOM precedence over stale encoding hints.

## Follow-Up

Keep HTML/XML parser charset negotiation, default-reader normalization policy,
terminal-profile-specific emoji width variants, full Unicode line-breaking
classes, and writer-wide automatic Markdown wrapping policy as separate
bounded slices.
