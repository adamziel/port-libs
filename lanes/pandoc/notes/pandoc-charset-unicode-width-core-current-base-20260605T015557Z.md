# Pandoc Charset Unicode Width Core Current Base

Slice: `pandoc-charset-unicode-width-core-current-base-20260605T015557Z`

Base accepted HEAD: `8de98de08373f006264a82593ed3bdce6dc6d28e`

## Behavior Added

- Added a bounded East Asian Ambiguous width policy to `UnicodeText`.
- `displayWidth()`, `splitAtDisplayWidth()`,
  `splitByDisplayBreakpoints()`, `padDisplay()`, and
  `wrapByDisplayWidth()` keep their default narrow ambiguous-width behavior,
  but now accept an opt-in `wide` policy for CJK terminal/reviewer layouts.
- Added ambiguous-width coverage for common Unicode Ambiguous classes used in
  Pandoc table/wrap output audits, including Latin-1 punctuation, Greek,
  Cyrillic, dashes, ellipses, mathematical symbols, arrows, enclosed
  alphanumerics, box-drawing symbols, and private-use/replacement glyphs.
- Extended the WordPress charset handoff smoke with narrow-vs-wide audit rows
  for ambiguous display-width decisions without invoking Pandoc or terminal
  tooling.

## Source Truth

This slice owns the supervisor row `pandoc-charset-unicode-width-core-*`, which
covers byte decoding, Unicode repair, and display-width behavior needed by
Pandoc readers and writers. Earlier charset notes explicitly left East Asian
ambiguous-width policy as a follow-up after byte decoding, line-ending repair,
display-width splitting, wrapping, emoji presentation clusters, and
normalization forms.

No hydrated Pandoc checkout was available in this worktree, so this remains
static source-truth mapping plus focused native PHP tests rather than upstream
Haskell runner parity.

## Verification

Red-first focused check:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result before implementation: failed with `1 test files, 92 assertions, 2
    failures` because ambiguous characters were still counted with the default
    narrow width under the new wide-policy expectations.

Post-implementation verification:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 100 assertions, 0 failures`
  - Delta: `+13` focused assertions and `+2` focused UnicodeText cases over
    the current accepted code baseline.
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 5423 assertions, 0 failures`
- `php -l lanes/pandoc/src/UnicodeText.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`
  - Result: no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice extends the existing native PHP
`UnicodeText` helper and reuses the existing Markdown/WordPress charset handoff
path. It does not invoke Pandoc, Cabal, Haskell test binaries, citeproc,
BibTeX, Biber, Word, LibreOffice, `zip`, `unzip`, `tar`, `lz4`, external
template engines, TeX/PDF engines, browser renderers, roff, Typst, MathJax,
KaTeX, online sanitizers, or online services.

## Non-Overlap

This patch does not repeat accepted XML/HTML5 DOM support, YAML metadata,
ZIP/OPC, relationship graphs, gzip/tar/LZ4 streams, doctemplate partials,
CSL/BibTeX processing, DOCX/ODT parsing, EPUB3 package handoff, legacy DOC/CFB
extraction, table geometry span normalization, Math/TeX conversion, PDF engine
handoff planning, line-ending normalization, display-width breakpoint
splitting, emoji presentation width, display-column wrapping, Unicode
normalization forms, or upstream-runner dependency audit work. It only extends
the charset/Unicode width primitive with opt-in East Asian Ambiguous width
policy for native display-column decisions.

## Follow-Up

Keep full Unicode line-breaking, emoji terminal-profile variants beyond
explicit presentation sequences, HTML/XML parser charset negotiation, and
writer-wide automatic Markdown wrapping policy as separate bounded slices.
