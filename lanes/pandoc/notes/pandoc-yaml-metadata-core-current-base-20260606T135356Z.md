# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260606T135356Z`
Base accepted HEAD: `3ba404c70030aefa6ea8fb919a97080f7ba0981b`

## Behavior Added

- `MarkdownReader` now coerces YAML boolean synonyms in metadata scalars:
  `y`, `yes`, `true`, `on` map to `true`; `n`, `no`, `false`, `off` map to
  `false`, case-insensitively.
- Explicit `!!bool` and `!<tag:yaml.org,2002:bool>` values use the same
  synonym table, so `!!bool ON`, `!!bool y`, and `!!bool off` no longer remain
  source strings.
- Quoted values such as `"yes"`, `'NO'`, `"off"`, and `"n"` remain strings for
  WordPress review packets.
- Covered positions include block mappings, flow mappings, and nested
  reference metadata maps.

## Source Truth Boundary

Pandoc's pinned `Text.Pandoc.Readers.Metadata` delegates YAML metadata parsing
to `Data.Yaml` before conversion into Pandoc metadata values. The `Data.Yaml`
documentation warns that special plain strings such as `yes`, `no`, `null`,
and numeric-looking values should not be emitted in plain style because they
decode as typed YAML values. This slice ports only the bounded boolean-synonym
part of that native metadata behavior.

Source references:

- `src/Text/Pandoc/Readers/Metadata.hs` at `jgm/pandoc`
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83`:
  `https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Readers/Metadata.hs`.
- Hackage `Data.Yaml` documentation for special plain strings:
  `https://hackage.haskell.org/package/yaml/docs/Data-Yaml.html`.

No Pandoc binary, Cabal solver/build/test command, Haskell runner, external
YAML parser, online service, live provider test, or live-service provider test
was executed.

## Evidence

- Rework-note check:
  `find /home/claude/port-libs/.tmux-team/tmp/handoff-candidates -maxdepth 1 -name 'port-pandoc-*.needs-lane-rework.md' -print | sort`
  - Result: no current Pandoc rework note was present.
- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 3175 assertions, 0 failures`.
- Red-first direct probe before implementation:
  `approved: yes`, `blocked: NO`, `legacy: On`, `explicit: !!bool off`, and
  flow values `y`, `n`, `ON`, `OFF` all remained source strings.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 3200 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+25` assertions.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`.
- PHP lint:
  `php -l lanes/pandoc/src/MarkdownReader.php`
  `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php`
  - Result: no syntax errors detected.

Additional required checks are recorded in the final handoff: JSON metadata
validation and `git diff --check -- lanes/pandoc`.

Root harness not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1338 -> 1339`.
- `UPSTREAM_TEST_MANIFEST.json` mapped native checks: `1752 -> 1753`.
- Added manifest inventory keys:
  - `mappedYamlMetadataBooleanSynonymCases: 1`
  - `yamlMetadataBooleanSynonymAssertions: 25`

## Dependency Closure

No new support component is needed. This extends the existing native PHP YAML
metadata subset inside `MarkdownReader` and reuses existing AST metadata,
`MarkdownWriter`, `WordPressBlockWriter`, and the WordPress YAML metadata
handoff example. It does not require Pandoc, Cabal, Haskell runners, external
YAML libraries, external template engines, browser renderers, online services,
live provider tests, or live-service provider tests.

## Non-Overlap

This patch does not repeat accepted YAML block placement, omitted opening
markers, document marker comments, fenced-code exclusion, JSON-object metadata,
top-level flow mapping documents, flow-map parsing, multiline flow
collections, flow comments, quoted flow scalars, block sequences, compact
sequence maps, anchors, aliases, alias diagnostics, alias path diagnostics,
merge keys, merge-sequence precedence, explicit scalar/core tag coverage for
integer/float/null/timestamp/binary values, explicit integer base or
sexagesimal parsing, special float coercion, non-specific tags, custom tag
provenance paths, tag directives, `!!set`, `!!omap`/`!!pairs`, block-scalar
chomping, explicit mapping key normalization, explicit sequence keys, flow
explicit null keys, duplicate-key diagnostics, plain multiline scalars,
ambiguous top-level field diagnostics, quoted ambiguous field preservation, or
writer metadata emission. It owns only YAML boolean synonym scalar coercion and
quoted-string preservation.

It also does not touch accepted Markdown/HTML reader/writer behavior,
CSL/BibTeX, DOCX/ODT, EPUB3, legacy DOC/CFB, ZIP/OPC, doctemplate, table
geometry, Math/TeX, archive compression, XML/HTML5 DOM, charset/Unicode,
syntax-highlighting, or PDF engine handoff support.

## Follow-Up

Keep true multi-document YAML streams, writer-side directive/comment emission,
richer source-location diagnostics, full YAML schema validation, richer Pandoc
`MetaValue` fidelity, and full upstream runner dependency planning as separate
bounded slices.
