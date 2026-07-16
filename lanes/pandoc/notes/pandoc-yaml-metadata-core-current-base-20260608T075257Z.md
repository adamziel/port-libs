# Pandoc YAML Metadata Sequence Explicit Null Keys

Slice: `pandoc-yaml-metadata-core-current-base-20260608T075257Z`
Base accepted HEAD: `4f4ec14067c4ea71e3842b1e15edcba8b6688571`

## Behavior

`MarkdownReader` now maps block sequence items that use YAML explicit keys
without a value into null-valued item maps.

Examples now covered:

- `- ? source` becomes `['source' => null]`
- `- ? [source, uri]` becomes `['[source, uri]' => null]`
- `- ? {owner: desk, ticket: 7}` becomes
  `['{owner: desk, ticket: 7}' => null]`
- tagged explicit keys such as `- ? !wp-null tagged-source` keep tag
  provenance at the item-map key path

This closes the sequence-item counterpart to the existing block-map explicit
null key support. Previously the no-child sequence form was parsed as a raw
scalar string such as `'? source'`.

## Source-Truth Boundary

Pandoc's `yaml_metadata_block` extension treats front matter as YAML before
metadata conversion. YAML explicit keys may appear as block sequence item keys,
and a missing mapping value is a null value. This slice ports that bounded
front-matter behavior for native PHP metadata handoff only; it does not claim
full YAML schema/parser parity.

No Pandoc executable, Cabal solver/build/test command, Haskell runner, external
YAML parser, online service, live provider test, or live-service provider test
was executed.

## Evidence

- Rework note check: no `port-pandoc-*.needs-lane-rework.md` note existed for
  this lane before implementation.
- Baseline focused test:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  passed with `1 test files, 3598 assertions, 0 failures`.
- Red-first direct probe before implementation parsed `- ? source` and
  `- ? [source, uri]` sequence items as raw scalar strings:
  `'? source'` and `'? [source, uri]'`.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  passed with `1 test files, 3618 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  passed with `yaml metadata handoff self-test ok`.
- Required final verification is recorded in the handoff: PHP lint for changed
  PHP files, JSON validation, and `git diff --check -- lanes/pandoc`.
- Root harness status: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1567 -> 1568`.
- Manifest mapped denominator: `1988 -> 1989`.
- Added manifest inventory keys:
  - `mappedYamlMetadataSequenceExplicitNullKeyCases: 1`
  - `yamlMetadataSequenceExplicitNullKeyAssertions: 20`

## Dependency Closure

No new support component is needed. This slice reuses the existing native
`MarkdownReader` YAML/front-matter parser, metadata path/provenance helpers,
`MarkdownReaderTest.php`, and the WordPress YAML metadata handoff example.

## Non-Overlap

This does not repeat accepted YAML directive provenance, malformed `%TAG`,
URI-tag suffixes, punctuation anchor resolution, anchor declaration provenance,
alias diagnostic paths, source-comment provenance, duplicate-key diagnostics,
merge-sequence precedence, explicit merge tags, explicit mapping keys with
values, block-map explicit null keys, flow explicit/implicit null keys, quoted
ambiguous-field preservation, top-level flow mapping documents, indented
document-marker scalar handling, stream provenance, or plain scalar folding.
It owns only no-value explicit keys inside block sequence metadata items.

Suggested follow-up: stay in non-overlapping YAML metadata gaps such as
writer-side source-location policy, richer scalar provenance, or explicit
stream-boundary diagnostics.
