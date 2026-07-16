# pandoc-odf-open-document-core-current-base-20260608T152700Z

## Scope

Implemented bounded native ODF/OpenDocument field-format metadata handoff in
`OdfReader`.

This slice preserves source formatting metadata on reviewer-visible `odf-field`
spans:

- `style:num-prefix`;
- `style:num-suffix`;
- `style:num-letter-sync`;
- `text:date-adjust`;
- `text:time-adjust`.

Field values are not evaluated. The visible field text remains the package
source text or the existing field fallback value.

## Source Truth

The pinned local Pandoc checkout is absent in this environment, so this slice
uses the accepted lane ODF support-library contract and the pinned Pandoc ODT
reader source inspection via GitHub raw content at
`jgm/pandoc@0640c4c9859aa5a3ede082c190fcd5883c24ac83`. Pandoc's ODT
`ContentReader` handles inline text, tabs, links, notes, references, frames, and
`text:sequence` as content-reader inputs; this bounded PHP support path keeps
additional ODF field provenance visible for WordPress review rather than
attempting office-suite field recalculation.

No Pandoc binary, Cabal solver/build/test command, Haskell runner, Word,
LibreOffice, `zip`, `unzip`, external converter, online service, live provider
test, or live-service provider test was executed.

## Behavior

- `OdfReader::fieldMetadata()` now exposes numeric prefix/suffix and
  letter-sync metadata as `fieldMetadata` plus `data-odf-field-*` attributes.
- Number prefix/suffix values are read without trimming so significant source
  spaces survive review handoff.
- Date/time adjustment attributes are preserved on date/time metadata fields.
- Markdown and WordPress output preserve the new safe data attributes on
  existing `odf-field` spans.
- The WordPress ODF smoke now checks formatted page-number, adjusted date, and
  adjusted time field metadata.

## Verification

- Baseline focused ODF reader test before edits:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 2017 assertions, 0 failures`
- First focused run after implementation caught trimmed prefix spacing:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 2021 assertions, 1 failures`
- Final focused ODF reader test:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 2049 assertions, 0 failures`
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`
  - `odf open document handoff self-test ok`

Focused movement: +1 PHP PASS case and +32 focused assertions. Lane `phpPass`
moves from `1695` to `1696`. `benchmarkDenominator.mapped` moves from `2115`
to `2116`.

## Dependency Closure

No new native PHP support component is needed. This slice reuses:

- `OdfReader` field-span and metadata plumbing;
- `MarkdownWriter` bracketed-span attribute output;
- `WordPressBlockWriter` safe span/data-attribute handoff;
- in-process ODT ZIP fixtures.

Full upstream Pandoc ODT runner parity, office-suite field evaluation, Word,
LibreOffice, `zip`/`unzip`, Cabal/Haskell runners, online services, live
provider tests, and live-service provider tests remain out of scope.

## Non-Overlap

This patch only changes ODF field-format metadata. It does not repeat accepted
ODF text-tab normalization, heading anchors, user-defined fields, typed boolean
or currency values, field style-name metadata, dropdown fields, database fields,
page-variable/statistic fields, conditional/hidden fields, DDE/script fields,
forms, generated indexes, charts, embedded objects, linked/protected sections,
tracked changes, DOCX, EPUB3, XML/HTML5 DOM, archive compression, or
BibTeX/CSL support.

## Next Task

Continue ODF/OpenDocument core with a non-overlapping content/package mapping
gap such as data-pilot metadata, tracked table changes, additional style-driven
table semantics, or package relationship provenance.
