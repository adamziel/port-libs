# Pandoc YAML Metadata Plain Scalar Provenance

Slice: `pandoc-yaml-metadata-core-current-base-20260608T091601Z`
Base accepted HEAD: `d9949f7212f1baa1739072f7847d9100f9fa82cb`

## Behavior

`MarkdownReader` now records folded YAML plain scalar source provenance in the
existing `yamlMetadataScalarProvenance` document attribute.

The new provenance records cover:

- block-style plain scalars such as `title:` followed by indented lines;
- mapping-line plain scalars continued by indented lines;
- folded sequence item plain scalars;
- nested reference metadata plain scalars.

Each record includes `type: yaml-plain-scalar`, `style: plain`, the
JSON-pointer-style metadata `path`, the mapping or sequence-item `sourceLine`,
`contentStartLine`, `contentEndLine`, and `contentLineCount`. The ordinary
`meta` payload is unchanged and the internal `__yamlMetadataScalarProvenance`
transport key remains hidden from user metadata.

## Source-Truth Boundary

Pandoc YAML metadata blocks are YAML documents before conversion to metadata.
YAML plain multiline scalars fold into scalar values, but import review tooling
also needs source-span provenance so a WordPress reviewer can trace generated
metadata back to front matter. This slice ports only bounded plain-scalar
provenance for the native PHP front-matter parser; it does not claim full YAML
source-map or parser parity.

No Pandoc executable, Cabal solver/build/test command, Haskell runner, external
YAML parser, online service, live provider test, or live-service provider test
was executed.

## Evidence

- Rework note check: no `port-pandoc-*.needs-lane-rework.md` note existed for
  this lane before implementation.
- Baseline focused test:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  passed with `1 test files, 3618 assertions, 0 failures`.
- Red-first focused test after adding the case failed as expected with
  `1 test files, 3626 assertions, 1 failures` because
  `yaml-plain-scalar` provenance records were absent.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  passed with `1 test files, 3633 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  passed with `yaml metadata handoff self-test ok`.
- Required final verification is recorded in the handoff: PHP lint for changed
  PHP files, JSON validation, and `git diff --check -- lanes/pandoc`.
- Root harness status: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1590 -> 1591`.
- Manifest mapped denominator: `2010 -> 2011`.
- Added manifest inventory keys:
  - `mappedYamlMetadataPlainScalarProvenanceCases: 1`
  - `yamlMetadataPlainScalarProvenanceAssertions: 15`

## Dependency Closure

No new support component is needed. This slice reuses the existing native
`MarkdownReader` YAML/front-matter parser, metadata provenance transport,
`MarkdownReaderTest.php`, `WordPressBlockWriter`, and the WordPress YAML
metadata handoff example.

## Non-Overlap

This does not repeat accepted YAML directive provenance, malformed `%TAG`,
URI-tag suffixes, punctuation anchor resolution, anchor declaration provenance,
alias diagnostic paths, source-comment provenance, duplicate-key diagnostics,
merge-sequence precedence, explicit merge tags, explicit/null mapping keys,
sequence explicit null keys, quoted ambiguous-field preservation, top-level
flow mapping documents, indented document-marker scalar handling, stream
provenance, block-scalar provenance, or plain scalar folding. It owns only
source-line/path provenance for YAML plain multiline scalars already parsed by
the bounded native metadata parser.

Suggested follow-up: stay in non-overlapping YAML metadata gaps such as
explicit stream-boundary diagnostics, writer-side source-location policy, or
quoted-scalar provenance.
