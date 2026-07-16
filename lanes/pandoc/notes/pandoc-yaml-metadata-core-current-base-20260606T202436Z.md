# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260606T202436Z`
Base accepted HEAD: `7ae9bd829c4ac182c3749c9a2dca4c1799cec369`

## Behavior Added

- `MarkdownReader` now treats explicit YAML core merge-tag keys as metadata
  merge keys when the tagged key scalar is `<<`.
- Covered bounded forms:
  - block mapping keys such as `!!merge <<: *defaults`;
  - flow mapping keys such as `{!!merge <<: *defaults, owner: Desk}`;
  - verbatim core merge tags such as `!<tag:yaml.org,2002:merge> <<`;
  - explicit-key mappings such as `? !!merge <<` followed by `: *defaults`.
- Core `!!merge` stays out of custom YAML tag provenance, matching the existing
  treatment for `!!str`, `!!int`, `!!map`, and other core tags.
- The WordPress YAML metadata handoff smoke now covers block, flow, and
  explicit-key merge-tag review packets without leaking raw `!!merge <<`
  fields.

## Source Truth Boundary

Pandoc's pinned `Text.Pandoc.Readers.Metadata` path delegates YAML metadata
parsing to `Data.Yaml` before converting the parsed object into Pandoc
metadata. YAML's core merge type is the scalar key `<<` with the
`tag:yaml.org,2002:merge` tag. This slice ports only that bounded key
recognition behavior in the native PHP YAML metadata subset.

No Pandoc binary, Cabal solver/build/test command, Haskell runner, external
YAML parser, browser renderer, online service, live provider test, or
live-service provider test was executed.

## Evidence

- Rework-note check:
  `ls -1 /home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md 2>/dev/null | tail -20`
  - Result: no current Pandoc rework note was present.
- Red-first direct PHP probe before implementation:
  - Result: block and flow `!!merge <<` keys were exposed as literal
    `!!merge <<` metadata fields instead of merging their alias/default map.
  - Existing explicit-key `? !!merge <<` already merged, but core `!!merge`
    could still be recorded as custom tag provenance.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 3257 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+19` focused assertions.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`.
- PHP lint:
  `php -l lanes/pandoc/src/MarkdownReader.php`
  `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php`
  - Result: no syntax errors detected.

Additional required checks are recorded in the final handoff:
`git diff --check -- lanes/pandoc`.

Root harness not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1398 -> 1399`.
- `UPSTREAM_TEST_MANIFEST.json` mapped native checks: `1811 -> 1812`.
- Added manifest inventory keys:
  - `mappedYamlMetadataExplicitMergeTagCases: 1`
  - `yamlMetadataExplicitMergeTagAssertions: 19`

## Dependency Closure

No new support component is needed. This extends the existing native PHP YAML
metadata subset inside `MarkdownReader` and reuses existing AST metadata,
`MarkdownWriter`, `WordPressBlockWriter`, and the WordPress YAML metadata
handoff example. It does not require Pandoc, Cabal, Haskell runners, external
YAML libraries or parsers, browser renderers, online services, live provider
tests, or live-service provider tests.

## Non-Overlap

This patch does not repeat accepted YAML block placement, omitted opening
markers, document marker comments, fenced-code exclusion, JSON-object metadata,
top-level flow mapping documents, ordinary flow-map parsing, multiline flow
collections, flow comments, flow quoted scalars, block sequences, compact
sequence maps, anchors, aliases, alias diagnostics, alias path diagnostics,
ordinary merge keys, merge-sequence precedence, explicit scalar/core tag
coercion, explicit integer/float/boolean/timestamp/binary parsing,
non-specific tags, custom tag provenance paths, tag directives, `!!set`,
`!!omap`/`!!pairs`, block-scalar chomping, explicit sequence/map keys, flow
explicit null keys, duplicate-key diagnostics, plain multiline scalars,
ambiguous top-level field diagnostics, quoted ambiguous field preservation,
abstract block handoff, or writer metadata emission. It owns only explicit
core merge-tag keys attached to the merge scalar `<<`.

## Follow-Up

Keep true multi-document YAML streams, writer-side directive/comment emission,
richer source-location diagnostics, full YAML schema validation, richer Pandoc
`MetaValue` fidelity, and full upstream runner dependency planning as separate
bounded slices.
