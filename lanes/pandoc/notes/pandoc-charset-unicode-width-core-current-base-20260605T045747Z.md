# Pandoc Charset Unicode Width Core Current Base

Slice: `pandoc-charset-unicode-width-core-current-base-20260605T045747Z`

Base accepted HEAD: `cdb95742bd3c7687d0958af5d550c13a3176c52f`

## Behavior Added

- Added bounded decomposed Hangul Jamo display-width handling to `UnicodeText`.
- Hangul medial/final Jamo ranges U+1160..U+11FF and Hangul Jamo Extended-B
  ranges U+D7B0..U+D7C6 and U+D7CB..U+D7FB are now zero-width combining
  continuations for grapheme clustering and display-column accounting.
- Hangul Jamo Extended-A leading consonants U+A960..U+A97C are now treated as
  wide display characters.
- Extended the WordPress charset handoff smoke with a Hangul Jamo audit row so
  review tables expose decomposed Korean source text without over-padding or
  over-wrapping it.

## Source Truth

This slice owns the supervisor row `pandoc-charset-unicode-width-core-*`, which
covers byte decoding, Unicode repair, and display-width behavior needed by
Pandoc readers and writers. Earlier charset notes already covered BOMs,
UTF-16, Windows-1252, malformed UTF-8 repair, line-ending normalization,
Unicode normalization forms, display-width breakpoints, emoji presentation
clusters, emoji tag sequences, East Asian ambiguous-width policy, soft-break
wrapping, default-ignorable controls, and bounded Unicode separator wrapping.
This is a bounded follow-up for decomposed Korean text that may reach Markdown
table writers and WordPress review tables before optional NFC normalization.

No current-base Pandoc rework note was present. No hydrated Pandoc checkout was
available in this worktree or the upstream cache, so this remains static
source-truth mapping plus focused native PHP tests rather than Haskell runner
parity.

## Verification

Pre-slice focused baseline:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 153 assertions, 0 failures`
  - Baseline PASS lines: 18.

Red-first focused check:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result before implementation: failed with `1 test files, 154 assertions,
    1 failures`.
  - Failure: the new decomposed Hangul Jamo case expected
    `UnicodeText::displayWidth("U+1112 U+1161 U+11AB")` to return `2`, but it
    returned `4`.

Post-implementation verification:

- `php -l lanes/pandoc/src/UnicodeText.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`
  - Result: no syntax errors.
- `php -r '$files=["lanes/pandoc/lane-status.json","lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"]; foreach ($files as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " json ok\n"; }'`
  - Result: both Pandoc JSON files decoded successfully.
- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 162 assertions, 0 failures`
  - Delta: `+9` focused assertions and `+1` focused PHP PASS case.
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`

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
display-width breakpoint splitting, display-column wrapping, emoji
presentation width, emoji tag sequences, default-ignorable width accounting,
East Asian ambiguous-width policy, Unicode soft-break wrapping, Unicode
separator wrapping, BOM precedence, or upstream-runner dependency audit work.
It only extends the charset/Unicode width primitive with bounded decomposed
Hangul Jamo display-width clustering.

## Follow-Up

Keep HTML/XML parser charset negotiation, dictionary-based segmentation,
terminal-profile-specific emoji width variants, full Unicode line-breaking
class parity beyond bounded separators and soft breaks, and writer-wide
automatic Markdown wrapping as separate bounded slices.
