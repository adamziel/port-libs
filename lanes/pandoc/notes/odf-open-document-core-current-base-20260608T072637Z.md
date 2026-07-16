# Pandoc ODF OpenDocument Field Style Names 2026-06-08

## Scope

Micro-slice: `pandoc-odf-open-document-core-current-base-20260608T072637Z`.

Accepted base: `fa0bf1a496fd8fffbd7a8cd81e2d1c2d1eb8804a`.

This is a native PHP ODF/OpenDocument behavior slice. No Pandoc binary, Cabal
solver/build/test command, Haskell test binary, Word, LibreOffice, `zip` or
`unzip`, external converter, online service, live provider test,
live-service provider test, or office tool was executed as progress.

## Source Truth

Static source truth is the pinned Pandoc upstream
`0640c4c9859aa5a3ede082c190fcd5883c24ac83` ODT reader behavior in
`src/Text/Pandoc/Readers/ODT/ContentReader.hs`, where inline content is
preserved as Pandoc inlines and `text:sequence` is consumed as inline content.

The local support-library contract is the accepted bounded ODF field handoff:
ODF fields remain reviewer-visible `odf-field` spans with source metadata for
Markdown and WordPress import review without executing Pandoc or an office
suite.

## Implemented Behavior

`OdfReader` now preserves ODF text-field style provenance on field review
spans:

- `text:style-name` is exposed as `fieldMetadata['styleName']`;
- the existing `style:data-style-name` path remains the fallback when no
  `text:style-name` is present;
- Markdown and WordPress handoff attributes include
  `data-odf-field-style-name`;
- author, page-number, and sender-email field examples keep visible field
  text unchanged while adding formatting provenance.

## WordPress Handoff

The WordPress ODF open-document example now marks the source author field with
`text:style-name="ReviewerField"`. The self-test checks that WordPress output
renders:

`data-odf-field-style-name="ReviewerField"`

on the existing `odf-field odf-field-author-name` review span.

## Dependency Closure

No new support component is needed. This slice reuses:

- `OdfReader` field-span mapping;
- `MarkdownWriter` bracketed-span attributes;
- `WordPressBlockWriter` safe span attributes;
- in-process ODT ZIP fixtures.

The upstream runner dependency blocker is unchanged: full parity still needs a
hydrated pinned Pandoc checkout and an explicitly reviewed non-mutating Cabal
plan before any runner execution is considered.

## Non-Overlap

This patch only changes ODF text-field style-name provenance. It deliberately
does not repeat accepted ODF text:tab normalization, heading auto identifiers,
heading source ids, paragraph blockquote mapping, table-caption post-process,
conditional/hidden text field handoff, drop-down field handoff, page-variable
and statistic fields, database fields, form controls, generated indexes,
embedded objects, chart metadata, table styles, linked/protected sections, or
tracked changes.

## Verification

- Baseline focused test before edits:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 1818 assertions, 0 failures`
- Red-first focused run after adding the failing fixture:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 1821 assertions, 1 failures`
  - Failure cause: `text:style-name` did not populate
    `fieldMetadata['styleName']`.
- Focused test after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 1840 assertions, 0 failures`
  - Focused delta: `+1` PASS case / `+22` assertions
- Example smoke:
  `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`
  - `odf open document handoff self-test ok`
- PHP lint:
  `php -l lanes/pandoc/src/OdfReader.php`
  - `No syntax errors detected`
- PHP lint:
  `php -l lanes/pandoc/tests/OdfReaderTest.php`
  - `No syntax errors detected`
- PHP lint:
  `php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php`
  - `No syntax errors detected`
- JSON validation:
  `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - `pandoc json ok`
- Whitespace check:
  `git diff --check -- lanes/pandoc`
  - passed with no output
- Root harness: not run - isolated micro-slice.

## Next Task

Continue ODF/OpenDocument core with a non-overlapping content/styles/meta XML
mapping gap such as additional field formatting metadata, list/table metadata,
or package relationship provenance.
