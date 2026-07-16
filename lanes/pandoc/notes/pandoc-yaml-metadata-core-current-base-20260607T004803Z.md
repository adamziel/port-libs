# pandoc-yaml-metadata-core-current-base-20260607T004803Z

## Behavior Added

The native PHP Markdown reader now treats YAML document markers as front-matter fences only when the marker begins at column zero. Indented `---` and `...` lines are preserved as scalar content inside literal and folded block scalars, including nested sequence item scalars.

This keeps WordPress import review packets from losing metadata notes that quote source separators or YAML stream markers inside reviewer-facing block scalar fields.

## Source Truth Boundary

Pandoc delegates YAML metadata parsing to its YAML support stack. This slice ports the bounded format contract needed by the local reader: top-level document markers close the front matter, while marker-looking lines indented under YAML block scalars are scalar text.

No Pandoc runner, Cabal solver/build/test command, Haskell test binary, external YAML parser, online service, live provider test, or live-service provider test was executed.

## Evidence

- Rework notes: no `port-pandoc-*.needs-lane-rework.md` note was present for this lane before editing.
- Baseline focused command: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed with `1 test files, 3257 assertions, 0 failures`.
- Red-first probe: before the implementation change, an indented `...` line inside `abstract: |` ended YAML metadata early and leaked the following source into the document body.
- Final focused command: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed with `1 test files, 3267 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test` passed with `yaml metadata handoff self-test ok`.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `1423 -> 1424`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1838 -> 1839`.
- New manifest inventory keys:
  - `mappedYamlMetadataIndentedDocumentMarkerScalarCases`: `1`
  - `yamlMetadataIndentedDocumentMarkerScalarAssertions`: `10`

## Dependency Closure

No new support component is needed. The slice reuses native PHP `MarkdownReader`, `AstNode`, `MarkdownWriter`, `WordPressBlockWriter`, and the existing YAML metadata handoff example.

Full Pandoc runner parity remains blocked by hydrating the pinned upstream checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` and explicitly authorizing Haskell/Cabal solver, build, and runner work. This patch does not add that dependency and does not require an external YAML library.

## Non-Overlap

This does not repeat accepted YAML block placement, omitted opening marker handling, document-marker comments, fenced-code exclusion, JSON metadata, flow metadata, multiline flow metadata, anchors, aliases, merge keys, explicit tags, explicit sequence keys, or quoted ambiguous field-name diagnostics.

This slice owns only the column-zero document-marker boundary needed to preserve marker-looking lines inside YAML block scalar metadata.

## Follow-Up

Good YAML follow-up candidates are true multi-document stream policy, writer-side directive/comment emission, richer source-location diagnostics, and full YAML schema validation. Those should remain native and bounded unless the supervisor explicitly authorizes Pandoc/Haskell runner work.
