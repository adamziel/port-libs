# Pandoc Charset Unicode Width Core Current Base

Slice: `pandoc-charset-unicode-width-core-current-base-20260605T073620Z`

Base accepted HEAD: `edeac0d59e2932eecdef96341078d50d2caa9227`

## Behavior Added

- Extended bounded fallback Unicode normalization data for Latin-Extended
  reviewer names used by Markdown and WordPress handoff paths when `intl`
  normalization is unavailable or deliberately bypassed.
- Added canonical composition/decomposition pairs for focused Polish, Czech,
  Hungarian, and Romanian import text: ogonek, acute, caron, ring, double acute,
  dot-above, and comma-below combinations.
- Verified fallback NFC composes decomposed reviewer source like
  `Zażółć gęślą jaźń`, `Český Štěpán`, `kůň`, `őű`, and `șț`, while fallback
  NFD round-trips those characters back to their combining-mark source forms.
- Extended the WordPress charset handoff smoke with a `Latin Extended NFC`
  audit row so imported reviewer packets expose the same native fallback
  normalization behavior without invoking external converters.

## Source Truth

This slice owns the supervisor row `pandoc-charset-unicode-width-core-*`, which
covers byte decoding, Unicode repair, and display-width behavior needed by
Pandoc readers and writers. Earlier accepted charset slices already covered
BOMs, UTF-16, Windows-1252, malformed UTF-8 repair, line-ending normalization,
baseline Unicode normalization forms, fallback combining-mark ordering,
display-width breakpoint splitting, display-column wrapping, emoji
presentation and tag sequences, emoji ZWJ variation sequences, decomposed
Hangul Jamo width, Indic spacing-mark width, default-ignorable controls, East
Asian ambiguous-width policy, Unicode soft-break wrapping, Unicode separators,
and prepended format-control zero-width accounting.

The bounded upstream-facing behavior is Pandoc's UTF-8 text pipeline expectation
that imported source text can be normalized before reader/writer handoff. Full
Unicode normalization data remains a future slice; this patch only adds a
high-signal Latin-Extended cluster that appears in real reviewer names and
titles.

No current-base Pandoc rework note was present. No hydrated Pandoc checkout was
available in this worktree or upstream cache, so this remains static
source-truth mapping plus focused native PHP tests rather than Haskell runner
parity.

## Verification

Pre-slice focused baseline:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 203 assertions, 0 failures`

Red-first focused check:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result before implementation: failed with `1 test files, 204 assertions,
    1 failures`.
  - Failure: the new Latin-Extended fallback NFC case expected
    `Zażółć gęślą jaźń / Český Štěpán, kůň, őű, șț`, but the bounded fallback
    tables left most of the source decomposed.

Post-implementation verification:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 216 assertions, 0 failures`
  - Delta: `+13` focused assertions and `+1` focused PHP PASS case.
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`
- `php -l lanes/pandoc/src/UnicodeText.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`
  - Result: no syntax errors.
- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); fwrite(STDOUT, $file . " json ok\n"); }'`
  - Result: both JSON files decoded successfully.
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice extends the existing native PHP
`UnicodeText` helper and reuses the current Markdown reader plus WordPress
charset handoff example. It does not invoke Pandoc, Cabal, Haskell test
binaries, citeproc, BibTeX, Biber, Word, LibreOffice, `zip`, `unzip`, `tar`,
`lz4`, external template engines, TeX/PDF engines, browser renderers, roff,
Typst, MathJax, KaTeX, terminal probes, external normalizers, online
sanitizers, or online services.

## Non-Overlap

This patch does not repeat accepted XML/HTML5 DOM support, YAML metadata,
ZIP/OPC, relationship graphs, gzip/tar/LZ4 streams, doctemplate partials,
CSL/BibTeX processing, DOCX/ODT parsing, EPUB3 package handoff, legacy DOC/CFB
extraction, table geometry span normalization, Math/TeX conversion, PDF engine
handoff planning, line-ending normalization, byte decoding, display-width
breakpoint splitting, display-column wrapping, emoji presentation width,
emoji tag sequences, emoji ZWJ variation width, decomposed Hangul Jamo width,
Indic spacing-mark width, default-ignorable control width accounting, East
Asian ambiguous-width policy, Unicode soft-break wrapping, Unicode separator
wrapping, prepended format-control zero-width accounting, BOM precedence, or
upstream-runner dependency audit work.

It only extends the charset/Unicode primitive with a bounded fallback
normalization data cluster for Latin-Extended reviewer names.

## Follow-Up

Keep full Unicode normalization data tables, full Unicode line-breaking class
parity, dictionary-based segmentation, terminal-profile-specific emoji width
variants, HTML/XML parser charset negotiation, and writer-wide automatic
Markdown wrapping as separate bounded slices.
