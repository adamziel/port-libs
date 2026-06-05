# Pandoc Charset Unicode Width Core Current Base

Slice: `pandoc-charset-unicode-width-core-current-base-20260605T084101Z`

Base accepted HEAD: `88f2990f50bf7e13b31f3140a7d4cbf9f16fa050`

## Behavior Added

- Extended `UnicodeText::graphemes()` so Thai SARA AM (U+0E33) and Lao vowel
  sign AM (U+0EB3) attach to the preceding base character for display slicing.
- Preserved the existing display-width accounting: each AM sign still
  contributes one column inside the resulting two-column cluster instead of
  being treated as a zero-width combining mark.
- Extended display breakpoint splitting, padding, wrapping, and the WordPress
  charset handoff audit row so Thai/Lao import text is not cut between a base
  consonant and AM sign.

## Source Truth

This slice owns the supervisor row `pandoc-charset-unicode-width-core-*`, which
covers byte decoding, Unicode repair, and display-width behavior needed by
Pandoc readers and writers. Prior accepted charset notes used Pandoc's
`Text.Pandoc.Shared`/`Text.DocLayout.charWidth` contract as source truth:
combining marks are zero-width, regular characters are one column, and display
slicing should avoid cutting grapheme clusters. The local PHP Intl grapheme
implementation treats Thai SARA AM and Lao vowel sign AM as part of the
preceding grapheme cluster, while the pre-patch native PHP fallback split those
visual syllables before display breakpoint slicing.

No current-base Pandoc rework note was present. No hydrated Pandoc checkout was
available in this worktree or upstream cache, so this remains static
source-truth mapping plus focused native PHP tests rather than Haskell runner
parity.

## Verification

Pre-slice focused baseline:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 229 assertions, 0 failures`

Red-first focused run after adding the Thai/Lao AM test and before the
implementation:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 232 assertions, 1 failures`
  - Failure: `UnicodeText::graphemes()` returned separate clusters for base
    consonant and AM sign instead of `[Thai AM cluster, Lao AM cluster, X]`.

Post-implementation verification:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 238 assertions, 0 failures`
  - Delta: `+9` focused assertions and `+1` focused PHP PASS case.
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`
- `php -l lanes/pandoc/src/UnicodeText.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`
  - Result: no syntax errors.
- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " json ok\n"; }'`
  - Result: both JSON files decoded successfully.
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice extends the existing native PHP
`UnicodeText` helper and reuses the current Markdown/WordPress charset handoff
example. It does not invoke Pandoc, Cabal, Haskell test binaries, citeproc,
BibTeX, Biber, Word, LibreOffice, `zip`, `unzip`, `tar`, `lz4`, external
template engines, TeX/PDF engines, browser renderers, roff, Typst, MathJax,
KaTeX, terminal probes, external normalizers, online sanitizers, or online
services.

## Non-Overlap

This patch does not repeat accepted XML/HTML5 DOM support, YAML metadata,
ZIP/OPC, relationship graphs, gzip/tar/LZ4 streams, doctemplate partials,
CSL/BibTeX processing, DOCX/ODT parsing, EPUB3 package handoff, legacy DOC/CFB
extraction, table geometry span normalization, Math/TeX conversion, PDF engine
handoff planning, line-ending normalization, byte decoding, Unicode
normalization, display-width breakpoint splitting, display-column wrapping,
emoji presentation width, emoji tag sequences, emoji ZWJ variation width,
supplementary East Asian wide-symbol width, decomposed Hangul Jamo width,
Indic spacing-mark width, default-ignorable control width accounting, East
Asian ambiguous-width policy, Unicode soft-break wrapping, Unicode separator
wrapping, prepended format-control zero-width accounting, BOM precedence, or
upstream-runner dependency audit work.

It only extends the charset/Unicode primitive with bounded Thai/Lao AM
grapheme-cluster preservation for display-column slicing.

## Follow-Up

Keep broader Unicode grapheme-break tables, dictionary-based segmentation,
terminal-profile-specific width variants, HTML/XML parser charset negotiation,
full Unicode normalization data tables, ICU-driven width table refreshes, and
writer-wide automatic Markdown wrapping as separate bounded slices.
