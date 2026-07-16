# ODF/OpenDocument Current-Base Typed Sequence References

Micro-slice: `pandoc-odf-open-document-core-current-base-20260609T062540Z`
Base accepted HEAD: `fc8eeee0d58103faabecc24a17572b78d812884d`

## Source Truth

OpenDocument text content represents caption counters with
`text:sequence-decls` / `text:sequence-decl` declarations and inline
references with `text:sequence-ref`. This slice keeps the existing native PHP
ODF package path and maps the declaration contract into the Pandoc-like AST
metadata used by Markdown and WordPress block output.

## Implementation

- `OdfReader` now enriches `text:sequence-ref` fields that carry `text:name`
  from the parsed `text:sequence-decl` entry with:
  - `sequenceDisplayOutlineLevel`
  - `sequenceSeparationCharacter`
  - `declared`
- Untyped sequence references and typed references with no matching
  declaration keep their previous fallback behavior and do not claim
  declaration metadata.
- The WordPress ODF handoff smoke now exercises a typed `Illustration`
  sequence reference and verifies declaration-backed data attributes.

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
  - `No syntax errors detected in lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php`
  - `No syntax errors detected in lanes/pandoc/examples/wordpress-odf-open-document-handoff.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 3204 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`
  - `odf open document handoff self-test ok`

## Count Delta

- Focused PHP PASS cases: `+1`
- Focused assertions: `+29`
- `lane-status.json` `phpPass`: `2443 -> 2444`
- ODF/OpenDocument mapped core cases: `13 -> 14`
- ODF/OpenDocument core assertions: `295 -> 324`

## Dependency Closure

No new native support component is needed. The slice reuses the existing
native PHP `OdfReader` content declaration extraction, `MarkdownWriter`,
`WordPressBlockWriter`, and ODF WordPress handoff example. Pandoc, Cabal,
Haskell runners, Word, LibreOffice, zip/unzip, external template engines,
TeX/PDF engines, browser renderers, online services, live provider tests, and
live-service provider tests were not executed.

## Non-Overlap

This does not revisit line-numbering configuration, sender-field settings
fallback, drop-down fields, database ranges/subtotals, data-pilot metadata,
calculation settings, consolidation declarations, table tracked changes, draw
layers, or the existing untyped `sequence-ref` output contract.
