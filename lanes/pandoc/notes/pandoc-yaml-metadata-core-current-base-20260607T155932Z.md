# Pandoc YAML Metadata Comment Provenance

Slice: `pandoc-yaml-metadata-core-current-base-20260607T155932Z`
Base: `4fa012f593053e9172158b015d96f6a54032a32d`

## Behavior

`MarkdownReader` now records standalone YAML front-matter comments and trailing
mapping-value comments as `yamlMetadataCommentProvenance` document attributes
with JSON-pointer-style metadata paths. Comments remain excluded from parsed
metadata values, while comment-only mapping values such as `review: # comment`
can still introduce a nested mapping below the key.

The WordPress YAML metadata handoff example now asserts source-comment
provenance for title, keyword, block-scalar header, and later metadata-block
comments so importer review tooling can audit source comments without rendering
them as body content.

## Evidence

- No lane rework note existed for `port-pandoc-*.needs-lane-rework.md` before
  this slice started.
- Baseline `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  passed with `1 test files, 3426 assertions, 0 failures`.
- Red-first `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  failed as expected with `1 test files, 3428 assertions, 1 failures` because
  comment provenance was absent and a comment-only mapping line prevented the
  nested map from being parsed.
- Final focused `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  passed with `1 test files, 3440 assertions, 0 failures`.
- Example smoke `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  passed with `yaml metadata handoff self-test ok`.
- PHP lint passed for `lanes/pandoc/src/MarkdownReader.php`,
  `lanes/pandoc/tests/MarkdownReaderTest.php`, and
  `lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php`.
- JSON validation passed for `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` and
  `lanes/pandoc/lane-status.json`.
- `git diff --check -- lanes/pandoc` passed with no output.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `1529` -> `1530`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1948` -> `1949`.
- Added `mappedYamlMetadataCommentProvenanceCases: 1` and
  `yamlMetadataCommentProvenanceAssertions: 14`.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP
`MarkdownReader` YAML front-matter parser, existing document attributes,
`MarkdownWriter`, `WordPressBlockWriter`, focused PHP tests, and the existing
WordPress YAML metadata example. No Pandoc, Cabal solver/build/test command,
Haskell runner, external YAML parser, online service, live provider test, or
live-service provider test was executed.

## Non-Overlap

This slice does not repeat prior YAML support for directives, invalid TAG
diagnostics, URI tag suffixes, punctuation anchors, null keys, indented
document-marker block scalar handling, writer block scalars, flow explicit keys,
alias diagnostic paths, duplicate keys, quoted ambiguous fields, top-level flow
mapping documents, plain scalar folding, or nested explicit mapping keys. It
only owns YAML source-comment provenance and the parser edge where a trailing
comment on an otherwise empty mapping value should still allow nested metadata.
