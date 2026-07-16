# Pandoc Charset Unicode Width Core Current Base

Slice: `pandoc-charset-unicode-width-core-current-base-20260605T002505Z`

Base accepted HEAD: `4c0f6049be2ab0d99e8dc12a6fb071ccbc3f5783`

## Behavior Added

- Extended `UnicodeText::graphemes()` so regional-indicator pairs stay in one
  display cluster instead of being split between flag code points.
- Treated emoji skin-tone modifiers as zero-width cluster extenders.
- Added emoji presentation display-width handling for:
  - U+FE0F variation-selector sequences such as checkbox glyphs;
  - keycap sequences such as `1` plus variation selector plus combining keycap;
  - newer emoji blocks such as transport symbols;
  - extended combining mark ranges used by imported Unicode text.
- Extended the WordPress charset handoff smoke with emoji display-width audit
  rows so reviewer tables expose the same native width decisions.

## Source Truth

This slice owns the support-library row named by the supervisor:
`pandoc-charset-unicode-width-core-*`, covering byte decoding, Unicode repair,
and display-width behavior needed by Pandoc readers/writers. The lane manifest
already records upstream Pandoc table/layout behavior that depends on display
width rather than byte or codepoint count. This patch keeps the scope bounded to
native PHP display-width primitives used by Markdown table writing and
WordPress review audit output.

No hydrated Pandoc checkout was available in
`/home/claude/port-libs/.upstream-cache/pandoc`, so this remains static
source-truth mapping plus focused native PHP tests rather than upstream Haskell
runner parity.

## Verification

Red-first focused check:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result before implementation: failed in
    `measures emoji presentation sequences as single display clusters` because
    U+2611 U+FE0F measured as width `1` instead of `2`.

Post-implementation verification:

- `php -l lanes/pandoc/src/UnicodeText.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 58 assertions, 0 failures`
  - Delta: `+11` focused assertions and `+1` focused PHP PASS case from the
    previous `47`-assertion charset test state.
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 4605 assertions, 0 failures`
- `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice extends the existing native PHP
`UnicodeText` helper and reuses `MarkdownWriter`, `WordPressBlockWriter`, and
AST paths through the existing charset handoff example. It does not invoke
Pandoc, Cabal, Haskell test binaries, citeproc, BibTeX, Biber, Word,
LibreOffice, `zip`, `unzip`, `tar`, `lz4`, external template engines, TeX/PDF
engines, browser renderers, roff, Typst, MathJax, KaTeX, or online services.

## Non-Overlap

This patch does not repeat accepted XML/HTML5 DOM support, YAML metadata,
ZIP/OPC, relationship graphs, gzip/tar/LZ4 streams, doctemplate partials, CSL or
BibTeX processing, DOCX/ODT parsing, EPUB3 package handoff, legacy DOC/CFB
extraction, table geometry span normalization, Math/TeX conversion, PDF engine
handoff planning, line-ending normalization, display-width breakpoint splitting,
or upstream-runner dependency audit work. It only extends the charset/Unicode
width primitive with bounded emoji presentation and modifier cluster behavior.

## Follow-Up

Keep full Unicode normalization forms, East Asian ambiguous-width policy,
HTML/XML parser charset negotiation, and full Unicode line-wrapping/layout
behavior as separate bounded slices.
