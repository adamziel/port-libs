# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260606T143117Z`
Base accepted HEAD: `dbf88db9fb156d2bf6c53c71b80f704c08d7bae2`

## Behavior Added

- `MarkdownReader` now treats unquoted plain numeric-looking YAML metadata
  scalars as typed numeric values.
- Covered bounded forms:
  - decimal integers with underscores and signs, such as `1_024` and
    `-1_024`;
  - hexadecimal, binary, octal, and legacy-octal integers, such as `0x2A`,
    `0b101010`, `0o52`, and `052`;
  - sexagesimal integers, such as `1:20:30`, while preserving invalid
    sexagesimal source text such as `1:60`;
  - decimal and exponent floats with underscores;
  - plain `.inf`, `-.INF`, `+.INF`, and `.NaN` forms.
- Quoted numeric-looking values such as `"1_024"`, `"0x2A"`, and `".5"` stay
  strings for WordPress review packets.

## Source Truth Boundary

Pandoc's pinned `Text.Pandoc.Readers.Metadata` delegates YAML metadata parsing
to `Data.Yaml`, then converts YAML `Number` values into Pandoc metadata text.
`Data.Yaml` documents that plain special strings such as numeric-looking
values decode as numeric values unless quoted. This slice ports only that
bounded scalar recognition behavior inside the native PHP YAML metadata subset.

Source references:

- `src/Text/Pandoc/Readers/Metadata.hs` at `jgm/pandoc`
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83`:
  `https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Readers/Metadata.hs`.
- Hackage `Data.Yaml` documentation for special plain strings:
  `https://hackage.haskell.org/package/yaml-0.11.11.2/docs/Data-Yaml.html`.

No Pandoc binary, Cabal solver/build/test command, Haskell runner, external
YAML parser, browser renderer, online service, live provider test, or
live-service provider test was executed.

## Evidence

- Rework-note check:
  `ls -1 /home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md 2>/dev/null || true`
  - Result: no current Pandoc rework note was present.
- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 3200 assertions, 0 failures`.
- Red-first direct probe before implementation:
  plain `1_000`, `0x2A`, `0o52`, `0b101010`, `.NaN`, and `+.INF` metadata
  values remained source strings.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 3229 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+29` assertions.
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

- `lane-status.json` `phpPass`: `1346 -> 1347`.
- `UPSTREAM_TEST_MANIFEST.json` mapped native checks: `1760 -> 1761`.
- Added manifest inventory keys:
  - `mappedYamlMetadataPlainNumericScalarCases: 1`
  - `yamlMetadataPlainNumericScalarAssertions: 29`

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
top-level flow mapping documents, flow-map parsing, multiline flow
collections, flow comments, quoted flow scalars, block sequences, compact
sequence maps, anchors, aliases, alias diagnostics, alias path diagnostics,
merge keys, merge-sequence precedence, explicit scalar/core tag coverage,
explicit integer base or sexagesimal parsing, special float coercion,
non-specific tags, custom tag provenance paths, tag directives, `!!set`,
`!!omap`/`!!pairs`, timestamp/binary tags, block-scalar chomping, explicit
mapping key normalization, explicit sequence keys, flow explicit null keys,
duplicate-key diagnostics, plain multiline scalars, ambiguous top-level field
diagnostics, quoted ambiguous field preservation, or writer metadata emission.
It owns only unquoted plain numeric-looking YAML metadata scalar coercion and
quoted-string preservation around that behavior.

It also does not touch accepted Markdown/HTML reader/writer behavior,
CSL/BibTeX, DOCX/ODT, EPUB3, legacy DOC/CFB, ZIP/OPC, doctemplate, table
geometry, Math/TeX, archive compression, XML/HTML5 DOM, charset/Unicode,
syntax-highlighting, or PDF engine handoff support.

## Follow-Up

Keep true multi-document YAML streams, writer-side directive/comment emission,
richer source-location diagnostics, full YAML schema validation, merge-key edge
cases, richer Pandoc `MetaValue` fidelity, and full upstream runner dependency
planning as separate bounded slices.
