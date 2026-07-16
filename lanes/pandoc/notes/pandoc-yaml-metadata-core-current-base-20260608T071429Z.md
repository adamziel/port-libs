# Pandoc YAML Metadata Current-Base Stream Provenance

Slice: `pandoc-yaml-metadata-core-current-base-20260608T071429Z`
Base accepted HEAD: `46202efa14a54e48d6402cf95aed247ffe0ec061`

## Behavior

`MarkdownReader` now exposes per-document provenance for YAML metadata streams
through the document attribute `yamlMetadataStreamProvenance`.

Each record includes:

- `type: yaml-document`
- `documentIndex`
- `source` (`implicit` or `explicit`)
- `openingMarker` and `endMarker`
- `startLine`, `contentStartLine`, and `endLine`
- `fieldCount`
- JSON-encoded top-level `fields`

The public `meta` payload remains merged as before. The internal transport key
`__yamlMetadataStreamProvenance` is filtered out before AST handoff.

## Source-Truth Boundary

Pandoc supports YAML metadata streams with multiple metadata documents. This
slice records bounded native provenance for the already parsed and merged YAML
front-matter documents so WordPress import review can explain which YAML
document supplied a metadata field cluster.

No Pandoc executable, Cabal solver/build/test command, Haskell runner, external
YAML parser, online service, live provider test, or live-service provider test
was executed.

## Evidence

- Rework note check: no `port-pandoc-*.needs-lane-rework.md` note existed for
  this lane before implementation.
- Baseline focused test: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  passed with `1 test files, 3588 assertions, 0 failures`.
- Red-first focused test after adding the case failed as expected with
  `1 test files, 3590 assertions, 1 failures` because
  `yamlMetadataStreamProvenance` was absent.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  passed with `1 test files, 3598 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  passed with `yaml metadata handoff self-test ok`.
- Required final verification is recorded in the handoff: PHP lint for changed
  PHP files, JSON validation, and `git diff --check -- lanes/pandoc`.
- Root harness status: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1558 -> 1559`.
- Manifest mapped denominator: `1979 -> 1980`.
- Added manifest inventory keys:
  - `mappedYamlMetadataStreamProvenanceCases: 1`
  - `yamlMetadataStreamProvenanceAssertions: 10`

## Dependency Closure

No new support component is needed. The slice reuses the native
`MarkdownReader` YAML/front-matter parser, existing metadata merge transport,
`MarkdownReaderTest.php`, `MarkdownWriter`, `WordPressBlockWriter`, and the
existing WordPress YAML metadata handoff example.

## Non-Overlap

This does not repeat accepted YAML directive provenance, malformed `%TAG`,
URI-tag suffixes, punctuation anchor resolution, anchor declaration provenance,
alias diagnostic paths, source-comment provenance, duplicate-key diagnostics,
merge-sequence precedence, explicit merge tags, explicit/null mapping keys,
quoted ambiguous-field preservation, top-level flow mapping documents, indented
document-marker scalar handling, or plain scalar folding. It owns only
per-document stream provenance for YAML metadata documents already accepted by
the bounded native parser.

Suggested follow-up: stay in non-overlapping YAML metadata gaps such as
writer-side source-location policy, richer scalar provenance, or explicit
stream-boundary diagnostics.
