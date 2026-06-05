# Pandoc Citation/CSL Current Base

Slice: `pandoc-citation-csl-core-current-base-20260605T142521Z`
Base accepted HEAD: `6c126186066ceb7460fca9cb3fcff42503b6c891`

## Behavior

- Added bounded CSL short-form text-variable rendering for `cs:text`.
- `CitationCslProcessor` now normalizes CSL JSON `title-short` aliases into the existing `shortTitle` field.
- `cs:text variable="title" form="short"` renders `title-short` / short-title metadata when present and falls back to the full title.
- `cs:text variable="container-title" form="short"` renders `container-title-short` / journal abbreviation metadata when present and falls back to the full container title.
- Added a WordPress handoff smoke for compact reviewer citation labels without invoking Pandoc, citeproc, BibTeX, Biber, or external conversion services.

## Source Truth

- Bounded native PHP support for CSL style text variables in the existing `CslStyle` / `CitationCslProcessor` renderer.
- This maps the citation processor contract for short title/container-title rendering needed by Pandoc/citeproc-style handoff tests while staying short of full citeproc parity.

## Verification

Baseline:

```bash
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
```

Result:

```text
1 test files, 1170 assertions, 0 failures
```

After implementation:

```bash
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
```

Result:

```text
1 test files, 1182 assertions, 0 failures
```

Example smoke:

```bash
php lanes/pandoc/examples/wordpress-citation-csl-short-form-handoff.php --self-test
```

Result:

```text
wordpress-citation-csl-short-form-handoff self-test passed
```

Lint:

```bash
php -l lanes/pandoc/src/CitationCslProcessor.php
php -l lanes/pandoc/tests/CitationCslProcessorTest.php
php -l lanes/pandoc/examples/wordpress-citation-csl-short-form-handoff.php
```

Result: no syntax errors detected.

Focused delta:

- `CitationCslProcessorTest.php`: `60 -> 61` PASS cases.
- Focused assertions: `1170 -> 1182` (+12).
- Lane `phpPass`: `943 -> 944`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP CSL style parser, citation renderer, Markdown reader, and WordPress block writer. No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external template engine, TeX/PDF engine, browser renderer, JavaScript, online sanitizer, external validator, office tool, online service, or live provider test was executed.

## Non-Overlap

This slice does not repeat accepted CSL date-part/date-form rendering, text-case, quote/strip-periods, macro rendering, choose conditionals, locator/label rendering, citation-number assignment/collapse, citation-position/near-note handling, year suffixes, subsequent-author substitution, BibTeX short-title parsing, BibLaTeX journal abbreviation parsing, table geometry, DOCX/ODT/EPUB, PDF engine handoff, archive compression, syntax highlighting, YAML metadata, doctemplates, XML/HTML5 DOM, charset/Unicode, or upstream-runner dependency audit work.

## Follow-Up

Keep note-style citations, bibliography id generation, richer title abbreviation/localization behavior, disambiguation beyond year suffixes, and full citeproc parity as separate bounded slices.
