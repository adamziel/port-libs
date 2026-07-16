# Pandoc YAML Metadata Current-Base Anchor Provenance

Slice: `pandoc-yaml-metadata-core-current-base-20260607T163653Z`
Base accepted HEAD: `d2e7be788a40ae9de50a145789df72120ce1ffab`

## Behavior

`MarkdownReader` now exposes YAML anchor declarations through
`yamlMetadataAnchorProvenance` document attributes. Each record includes:

- `type: yaml-anchor`
- `anchor`, including the leading `&`
- raw `name`
- JSON-pointer-style metadata `path`
- parsed value `kind` (`mapping`, `sequence`, `scalar`, `number`, `boolean`,
  or `null`)

The ordinary `meta` payload remains resolved and unchanged; the internal
`__yamlMetadataAnchorProvenance` transport key is filtered out before document
handoff. Sequence-item anchors keep the item path where the anchor token was
declared while updating the final kind after the nested node is parsed.

## Source-Truth Boundary

Pandoc YAML metadata relies on YAML anchors and aliases for reusable metadata.
This slice does not claim full YAML parser parity or source line/column
tracking. It adds bounded native review provenance for anchor declarations
already parsed by the lane's YAML metadata subset.

No Pandoc executable, Cabal solver/build/test command, Haskell runner, external
YAML parser, online service, live provider test, or live-service provider test
was executed.

## Evidence

- Rework note check: no `port-pandoc-*.needs-lane-rework.md` note existed for
  this lane before implementation.
- Baseline focused test: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  passed with `1 test files, 3440 assertions, 0 failures`.
- Red-first focused test after adding the case failed as expected with
  `1 test files, 3451 assertions, 1 failures` because
  `yamlMetadataAnchorProvenance` was absent.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  passed with `1 test files, 3458 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  passed with `yaml metadata handoff self-test ok`.
- Required final verification is recorded in the handoff: PHP lint for changed
  PHP files, JSON validation, and `git diff --check -- lanes/pandoc`.
- Root harness status: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1531 -> 1532`.
- Manifest mapped denominator: `1950 -> 1951`.
- Added manifest inventory keys:
  - `mappedYamlMetadataAnchorProvenanceCases: 1`
  - `yamlMetadataAnchorProvenanceAssertions: 18`

## Dependency Closure

No new support component is needed. The slice reuses the native
`MarkdownReader` YAML front-matter parser, existing metadata provenance
transport, `MarkdownReaderTest.php`, `MarkdownWriter`, `WordPressBlockWriter`,
and the existing WordPress YAML metadata handoff example.

## Non-Overlap

This does not repeat accepted YAML directive provenance, malformed `%TAG`,
URI-tag suffixes, punctuation anchor resolution, alias diagnostic paths,
source-comment provenance, duplicate-key diagnostics, merge-sequence precedence,
explicit merge tags, explicit/null mapping keys, quoted ambiguous-field
preservation, top-level flow mapping documents, indented document-marker scalar
handling, or plain scalar folding. It owns only anchor declaration provenance
for already parsed YAML metadata nodes.

Suggested follow-up: stay in non-overlapping YAML metadata gaps such as folded
scalar source-span diagnostics, multi-document stream policy, or writer
round-trip comment policy.
