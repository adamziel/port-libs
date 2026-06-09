# YAML Metadata Binary Writer Round Trip

Slice: `pandoc-yaml-metadata-core-current-base-20260609T083512Z`
Base accepted HEAD: `436db66ac9717cbf75ff2ec29905ae0ddef22b3a`

## Behavior

`MarkdownWriter` now reuses `yamlMetadataScalarProvenance` from `MarkdownReader`
when writing YAML metadata. Valid explicit `!!binary` scalar provenance is
emitted back as `!!binary` with base64-encoded decoded bytes for mapping values,
block-scalar values, sequence items, and flow mapping values. Invalid binary
source text is not promoted; it remains ordinary quoted metadata and keeps the
reader's existing invalid-binary diagnostics.

## Evidence

Red-first check after adding the focused test and before the writer change:

`php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`

Result: `1 test files, 5065 assertions, 1 failures` on
`writes pandoc yaml binary metadata using reader scalar provenance`.

Final focused verification:

`php -l lanes/pandoc/src/MarkdownWriter.php`
`php -l lanes/pandoc/tests/MarkdownReaderTest.php`
`php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php`

Result: all reported no syntax errors.

`php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`

Result: `1 test files, 5102 assertions, 0 failures`.

`php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`

Result: `yaml metadata handoff self-test ok`.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
Markdown YAML metadata parser, scalar provenance records, MarkdownWriter
metadata serializer, base64 handling, and WordPress block writer. Pandoc,
Cabal/Haskell runners, Word, LibreOffice, zip/unzip, external template engines,
TeX/PDF engines, browser renderers, online services, and live providers were
not run.

## Non-Overlap

This does not alter the prior YAML merge-source provenance behavior from
`pandoc-yaml-metadata-core-current-base-20260609T082004Z`. It is limited to
writer preservation for valid explicit binary scalar provenance and invalid
binary text visibility.

## Next

Continue YAML metadata support with a non-overlapping writer/reader gap such as
explicit timestamp/null scalar tag preservation in writer output, or malformed
flow-collection diagnostic hardening.
