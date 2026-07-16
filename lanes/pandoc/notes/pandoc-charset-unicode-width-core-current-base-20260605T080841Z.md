# Pandoc Charset Unicode Width Core Current Base

Slice: `pandoc-charset-unicode-width-core-current-base-20260605T080841Z`

Base accepted HEAD: `4ebc3c31816e39da5e62e366b5a64877e49deb7a`

## Behavior Added

- Extended `UnicodeText` display-width classification for bounded
  supplementary East Asian wide symbols not covered by the older BMP/CJK and
  emoji ranges.
- Added two-column width accounting for focused ideographic marks, Kana
  Supplement/Extended/Small Kana, Nushu, Mahjong red dragon, playing-card
  joker, enclosed ideographic, and related squared/circled CJK symbols.
- Preserved the existing zero-width combining/format-control path for Khitan
  filler and Vietnamese alternate-reading marks before wide-codepoint handling.
- Extended the WordPress charset handoff smoke with a supplementary-wide audit
  row so reviewer tables expose the two-column wrapping and padding decision.

## Source Truth

This slice owns the supervisor row `pandoc-charset-unicode-width-core-*`, which
covers byte decoding, Unicode repair, and display-width behavior needed by
Pandoc readers and writers. Prior accepted charset notes used Pandoc's
`Text.Pandoc.Shared`/`Text.DocLayout.charWidth` contract as source truth:
combining marks are zero-width, regular characters are one column, and East
Asian wide characters are two columns while ambiguous-width characters remain
policy-controlled. This patch only fills bounded supplementary East Asian wide
blocks that were absent from the native PHP table.

No current-base Pandoc rework note was present. No hydrated Pandoc checkout was
available in this worktree or upstream cache, so this remains static
source-truth mapping plus focused native PHP tests rather than Haskell runner
parity.

## Verification

Pre-slice focused baseline:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 216 assertions, 0 failures`

Pre-patch focused probe:

- `php -r 'require "tools/bootstrap.php"; ... UnicodeText::displayWidth(...)'`
  - Result: U+16FE0, U+1B000, U+1F200, U+1F004, and U+1F0CF returned width
    `1` before implementation.

Post-implementation verification:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 229 assertions, 0 failures`
  - Delta: `+13` focused assertions and `+1` focused PHP PASS case.
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
KaTeX, terminal probes, online sanitizers, or online services.

## Non-Overlap

This patch does not repeat accepted XML/HTML5 DOM support, YAML metadata,
ZIP/OPC, relationship graphs, gzip/tar/LZ4 streams, doctemplate partials,
CSL/BibTeX processing, DOCX/ODT parsing, EPUB3 package handoff, legacy DOC/CFB
extraction, table geometry span normalization, Math/TeX conversion, PDF engine
handoff planning, line-ending normalization, byte decoding, Unicode
normalization, display-width breakpoint splitting, display-column wrapping,
emoji presentation width, emoji tag sequences, emoji ZWJ variation width,
decomposed Hangul Jamo width, Indic spacing-mark width, default-ignorable
control width accounting, East Asian ambiguous-width policy, Unicode
soft-break wrapping, Unicode separator wrapping, prepended format-control
zero-width accounting, BOM precedence, or upstream-runner dependency audit
work.

It only extends the charset/Unicode primitive with bounded supplementary East
Asian wide-symbol display-column accounting.

## Follow-Up

Keep full Unicode line-breaking class parity, dictionary-based segmentation,
terminal-profile-specific emoji width variants, HTML/XML parser charset
negotiation, full Unicode normalization data tables, ICU-driven width table
refreshes, and writer-wide automatic Markdown wrapping as separate bounded
slices.
