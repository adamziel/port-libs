# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260606T041225Z`
Base accepted HEAD: `fdb5e0848e0c9db10bdf0b11405b74a8d6240d9f`

## Change

- `MarkdownReader` now treats a `---` line after a directive-only YAML preamble
  (`%YAML` / `%TAG`, plus blank/comment preamble lines) as the YAML document
  start marker, not as the Markdown metadata closing fence.
- The same behavior works for explicit `---` front matter blocks and
  omitted-opening metadata at document start.
- Directive preambles are consumed before JSON-object or top-level flow-map
  metadata detection, so tag handles remain available for flow-map values and
  custom tag provenance paths.
- `wordpress-yaml-metadata-handoff.php --self-test` now exercises the
  standards-shaped directive preamble by placing the YAML document-start marker
  after `%YAML` / `%TAG` lines.

## Source Truth

Pandoc's `yaml_metadata_block` extension treats front matter as YAML metadata.
YAML directive streams use an explicit document-start marker after directives;
the bounded native PHP parser now recognizes that marker only when every
previous nonblank preamble line is a supported directive. Ordinary `---` lines
after metadata content continue to close the Markdown metadata block.

This slice does not claim full YAML stream parity and does not parse multiple
YAML documents in one metadata block.

## Red Baseline

After adding the focused test, before the parser change:

`php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`

Result: failed in `maps pandoc yaml directive document start markers in
metadata blocks`; explicit directive-start metadata produced `NULL` metadata.
The baseline run reported `1 test files, 3099 assertions, 1 failures`.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 3117 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`.

Additional required checks are recorded in the final handoff:
PHP lint for changed PHP files and `git diff --check -- lanes/pandoc`.

## Manifest / Status Delta

- Added `mappedYamlMetadataDirectiveDocumentStartCases: 1`.
- Added `yamlMetadataDirectiveDocumentStartAssertions: 18`.
- Bumped `benchmarkDenominator.mapped` from `1636` to `1637`.
- Bumped lane `phpPass` from `1188` to `1189`.

## Dependency Closure

No new support component is needed. This extends the existing native PHP YAML
metadata subset in `MarkdownReader` and reuses existing AST metadata,
`MarkdownWriter`, `WordPressBlockWriter`, and the WordPress YAML metadata
handoff example. No Pandoc binary, Cabal solver/build/test command, Haskell
runner, external YAML parser, online sanitizer, online service, or live
provider test was executed.

## Non-Overlap

This patch does not repeat accepted YAML block placement, omitted opening
metadata without directives, fenced-code exclusion, JSON-object metadata,
top-level flow mapping documents, flow comments, flow quoted scalars, compact
sequence maps, anchors, aliases, alias diagnostics, merge keys, merge-sequence
precedence, explicit tags, custom tag provenance paths, tag handle expansion
without document-start markers, sets, ordered pairs, timestamp/binary tags,
empty scalar null semantics, sequence block scalars, explicit mapping keys,
flow explicit null keys, ambiguous field diagnostics, quoted ambiguous field
preservation, or writer metadata emission. It owns only directive preambles
with the YAML document-start marker inside Markdown metadata blocks.

## Follow-Up

Keep true multi-document YAML stream merging, writer-side directive emission,
full YAML schema validation, richer source-location diagnostics, and full
upstream Pandoc runner dependency planning as separate bounded slices.
