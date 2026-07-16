## pandoc-yaml-metadata-core-current-base-20260608T165737Z

Accepted base: `d95c6bf59d89f3e3e2b403b79e9517c83cdff5a1`

### Behavior

This slice adds bounded native YAML metadata comment provenance for comments
inside multiline flow collections. `MarkdownReader` now records `#` comments
inside multiline flow sequences and mappings as `yamlMetadataCommentProvenance`
entries with:

- `context: flow`
- the enclosing metadata JSON-pointer path
- source line, when available
- comment text with the marker trimmed

The parser still strips these comments from the flow collection source before
value parsing, so parsed metadata values remain comment-free.

### Non-overlap

This extends the existing YAML/front-matter support without repeating the
accepted standalone/trailing block-comment provenance slice. The new assertions
cover comments embedded inside multiline flow sequences and mappings, including
nested collection handoff, which previously produced no flow comment provenance.

No Pandoc, Cabal solver/build/test command, Haskell runner, external YAML
parser, online service, live provider test, or live-service provider test was
executed.

### Evidence

- Baseline focused test:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  -> `1 test files, 3791 assertions, 0 failures`
- Red-first focused test after adding assertions:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  -> `1 test files, 3789 assertions, 1 failures`
  because flow comment provenance was empty.
- Final focused test after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  -> `1 test files, 3794 assertions, 0 failures`
- Example smoke:
  `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  -> `yaml metadata handoff self-test ok`

Root harness: not run - isolated micro-slice.

### Status Delta

- `lanes/pandoc/lane-status.json`: `phpPass` `1695 -> 1696`
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`: mapped denominator `2115 -> 2116`
- New manifest counters:
  `mappedYamlMetadataFlowCommentProvenanceCases: 1`
  `yamlMetadataFlowCommentProvenanceAssertions: 3`

### Dependency Closure

No new support component is needed. The slice reuses the native PHP
`MarkdownReader` YAML/front-matter parser, existing metadata comment provenance
attrs, `WordPressBlockWriter`, and the existing WordPress YAML metadata handoff
example.

### Follow-up

Next YAML metadata work should stay non-overlapping: flow collection diagnostics,
directive/tag provenance, merge precedence, or scalar/source-position review
metadata are reasonable bounded targets.
