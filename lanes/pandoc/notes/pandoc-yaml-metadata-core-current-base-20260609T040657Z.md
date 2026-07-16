# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260609T040657Z`

Accepted base: `39b1c5d5b6751a4cd8edd906dabeef64d6d0fc2e`

## Scope

This slice stays inside native PHP YAML/front-matter metadata parsing. It does
not shell out to Pandoc, Cabal/Haskell runners, external YAML parsers, Word,
LibreOffice, zip/unzip, external converters, online services, live provider
tests, or live-service provider tests.

## Behavior

`MarkdownReader` now treats reserved YAML directives such as
`%EXPORT WordPressReview 2026` as valid metadata-document preamble directives.
The directive is ignored for schema/tag semantics, but recorded in
`yamlMetadataDirectiveProvenance` with directive name, parameter text,
parameter count, and source line.

The parser also treats top-level reserved directives after metadata content as
the same non-fatal `directive-after-document-content` boundary diagnostic used
for late `%YAML` and `%TAG` directives. A child-line collection guard keeps a
late reserved directive from being folded into the previous single-line scalar.

Covered shapes:

- explicit `---` YAML metadata block with a reserved directive before the YAML
  document start marker;
- implicit document-start metadata beginning with a reserved directive;
- reserved directive after metadata content;
- WordPress YAML metadata handoff smoke packet preserving review metadata and
  directive provenance.

## Evidence

Baseline focused command before this slice:

`php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`

Result: `1 test files, 4583 assertions, 0 failures`.

Red check before implementation:

`php -r 'require "tools/bootstrap.php"; use PortLibs\Pandoc\MarkdownReader; $doc=(new MarkdownReader())->read("---\n%EXPORT wordpress review\n---\ntitle: Reserved **Packet**\nreview: {status: queued}\n...\n\n# Body\n"); var_export($doc->attr("meta", null)); echo "\n"; var_export(array_column($doc->attr("yamlMetadataDirectiveProvenance", []), "directive")); echo "\n";'`

Result: `NULL` metadata and empty directive provenance.

Red-first focused run after adding the test exposed the child-line boundary
bug:

`php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`

Result: `1 test files, 4604 assertions, 1 failures` because a late reserved
directive was folded into the previous `title` scalar.

Final focused verification:

`php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`

Result: `1 test files, 4612 assertions, 0 failures`.

Example smoke:

`php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`

Result: `yaml metadata handoff self-test ok`.

Syntax checks:

- `php -l lanes/pandoc/src/MarkdownReader.php`
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
- `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php`

All reported no syntax errors.

Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `2275 -> 2276`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator:
  `2677 -> 2678`.
- New manifest counters:
  - `mappedYamlMetadataReservedDirectiveCases`: `1`
  - `yamlMetadataReservedDirectiveAssertions`: `29`

## Dependency Closure

No new support component is needed. This reuses native `MarkdownReader`
YAML/front-matter parsing, directive provenance, metadata diagnostics,
`MarkdownReaderTest.php`, and the WordPress YAML metadata handoff example.
Full upstream Pandoc runner parity remains separate because it requires a
hydrated Pandoc checkout and Haskell test executables.

## Non-Overlap

This does not repeat accepted `%YAML` version handling, `%TAG` handle
expansion, invalid `%TAG` syntax diagnostics, late `%TAG` rebinding guards,
primary/named/verbatim tag provenance, undefined tag-handle diagnostics,
non-specific tags, anchors, aliases, merge diagnostics, set/omap/pairs
handling, scalar provenance, collection provenance, writer-side YAML emission,
or YAML block placement. It owns only reserved YAML directives in metadata
preambles and late reserved directive boundary diagnostics.

## Follow-Up

Next YAML metadata work should target a non-overlapping source-position or
writer-side metadata provenance gap.
