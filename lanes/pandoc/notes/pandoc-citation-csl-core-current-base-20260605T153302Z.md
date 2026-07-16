# Pandoc Citation/CSL Core Current Base - Et Al Use Last

Slice: `pandoc-citation-csl-core-current-base-20260605T153302Z`

Accepted base: `9f5c2e5a2a488d9988b860638e73fa38efd5184e`

## Behavior

- Added bounded native CSL `et-al-use-last` support for `cs:names` /
  `cs:name` name rendering options.
- `CslStyle` now parses and validates the option as a boolean, exposes it in
  style summaries and rendering-element metadata, and carries the default CSL
  ellipsis term.
- `CitationCslProcessor` now renders truncated name lists as first visible
  names, an ellipsis, and the final original name when `et-al-use-last="true"`
  and the configured `et-al-min` / `et-al-use-first` values actually omit
  middle names. Explicit BibLaTeX `others` sentinels still use the existing
  et-al term path because no final concrete name is known.
- Added `wordpress-citation-csl-et-al-use-last-handoff.php` to exercise
  Markdown import, CSL style metadata, citation output, bibliography output,
  and WordPress block handoff without invoking external citation tooling.

## Source Truth

- CSL name rendering supports `et-al-use-last` as a bounded option for
  retaining the final name in an abbreviated name list.
- This slice maps only the native PHP handoff contract for final-name
  retention in truncated name lists. It does not attempt richer name
  disambiguation, locale-specific citation collapse punctuation, note-style
  output, style catalogs, or full citeproc parity.

## Verification

- Baseline:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 1245 assertions, 0 failures`.
- Green:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 1257 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-citation-csl-et-al-use-last-handoff.php --self-test`
  passed.
- Lint:
  `php -l lanes/pandoc/src/CslStyle.php`,
  `php -l lanes/pandoc/src/CitationCslProcessor.php`,
  `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`, and
  `php -l lanes/pandoc/examples/wordpress-citation-csl-et-al-use-last-handoff.php`
  passed.
- JSON validation:
  `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  passed.
- Diff check:
  `git diff --check -- lanes/pandoc` passed.

## Status Delta

- `CitationCslProcessorTest.php` moves from 64 focused PASS cases and 1245
  assertions to 65 focused PASS cases and 1257 assertions.
- `lanes/pandoc/lane-status.json` `phpPass` moves from 974 to 975.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped coverage moves from
  `1429` to `1430`, and `mappedCitationCslCoreCases` moves from `10` to `11`.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP
`CslStyle`, `CitationCslProcessor`, `MarkdownReader`, `MarkdownWriter`, and
`WordPressBlockWriter` support paths.

No Pandoc, Cabal solver/build/test command, Haskell runner, citeproc, BibTeX,
Biber, bibliography manager, Word, LibreOffice, zip/unzip, TeX/PDF engine,
browser renderer, online sanitizer, or online service was executed.

Full upstream Pandoc/citeproc runner parity remains gated on hydrating a local
Pandoc checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with
`cabal.project`, `pandoc.cabal`,
`pandoc-lua-engine/pandoc-lua-engine.cabal`, `test/test-pandoc.hs`, and
`pandoc-lua-engine/test/test-pandoc-lua-engine.hs` present before any
non-mutating Cabal solver/build plan.

## Non-Overlap

This does not repeat accepted CSL JSON item normalization, source-access
date/name metadata, BibTeX/BibLaTeX parsing, short-form text variables,
date-part/date-form rendering, macros, choose conditionals, labels, numbers,
text-case, quotes, punctuation-in-quote, name-part formatting, initialization
hyphen handling, sort-separator handling, delimiter-precedes-et-al, explicit
`cs:et-al` rendering, citation-number/year collapse, near-note behavior,
subsequent-author bibliography substitution, subsequent et-al thresholds,
table geometry, DOCX/ODT/EPUB, PDF, YAML, doctemplate, ZIP/OPC, archive
compression, charset/Unicode, XML/HTML5 DOM, legacy DOC/CFB, or
syntax-highlighting work.

Next bounded CSL follow-ups: richer name disambiguation, locale-specific
collapse punctuation, note-style output, and full citeproc parity.
