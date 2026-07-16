# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260605T160420Z`

Base accepted HEAD: `2e813bea91bc8b597a218cba9f792e088892e3a0`

## Behavior Added

- Extended the native `MarkdownReader` YAML metadata subset to recognize
  bounded `%YAML` and `%TAG` directive lines inside metadata blocks.
- `%TAG` handles are reset per metadata block, preserve the default `!!`
  `tag:yaml.org,2002:` core handle, and expand primary/named custom
  handle-form tags such as `!primary` and `!wpd!reviewer` before scalar
  parsing.
- Custom handle-expanded tags are recorded in `yamlMetadataTagProvenance` as
  normalized verbatim tags, while core handle-expanded tags such as
  `!yaml!str` and `!yaml!int` still drive scalar coercion without being
  reported as custom provenance.
- Updated the WordPress YAML metadata handoff smoke so reviewer metadata can
  preserve application tag provenance without leaking raw `%TAG` handle text
  into import packets.

## Source Truth

Pandoc's `yaml_metadata_block` extension treats front matter as YAML before it
is converted into document metadata. YAML documents can begin with `%YAML` and
`%TAG` directives; this slice ports only the bounded metadata handoff behavior
needed by native PHP conversion. It does not claim full YAML schema parity,
multi-document stream support, or external YAML parser equivalence.

No Pandoc binary, Cabal build, Haskell runner, or external YAML parser was
executed.

## Verification

- Current-base rework notes:
  `find /home/claude/port-libs/.tmux-team/tmp/handoff-candidates -maxdepth 1 -name 'port-pandoc-*.needs-lane-rework.md' -print`
  - Result: no output.
- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2908 assertions, 0 failures`.
- Red-first after adding the focused tag-directive case:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2912 assertions, 1 failures`.
  - Failure: `!wp!reviewer Import Desk` was preserved literally instead of
    expanding the `%TAG !wp!` handle and stripping the tag directive.
- Primary-handle red check:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2920 assertions, 1 failures`.
  - Failure: custom `%TAG !` primary-handle provenance stayed as `!primary`
    instead of expanding to the configured tag prefix.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2930 assertions, 0 failures`.
  - Delta: `+22` focused assertions and `+1` focused PASS case.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`.
- PHP lint:
  `php -l lanes/pandoc/src/MarkdownReader.php`
  `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php`
  - Result: no syntax errors detected.
- JSON metadata validation:
  `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " valid\n"; }'`
  - Result: both JSON files valid.
- `git diff --check -- lanes/pandoc`
  - Result: no output.

Root harness not run - isolated micro-slice.

## Status Delta

- Manifest mapped native checks: `1442 -> 1443`.
- Lane status `phpPass`: `987 -> 988`.
- Added `mappedYamlMetadataTagDirectiveCases: 1`.
- Added `yamlMetadataTagDirectiveAssertions: 22`.

## Dependency Closure

No new support component is needed. This extends the existing native PHP YAML
metadata subset inside `MarkdownReader` and reuses the existing AST and
WordPress YAML handoff example paths. It does not invoke Pandoc, Cabal,
Haskell test binaries, external YAML libraries or parsers, Word, LibreOffice,
`zip`, `unzip`, external template engines, TeX/PDF engines, browser renderers,
roff, Typst, MathJax, KaTeX, online sanitizers, or online services.

## Non-Overlap

This patch does not repeat accepted YAML block placement, fenced-code
exclusion, JSON-object metadata, ordinary flow-map metadata, multiline flow
collection balancing, flow comments, flow quoted scalars, verbatim flow tag
scanner handling, block-style nested sequence metadata, compact sequence maps,
anchors, valid aliases, alias diagnostics, ordinary merge keys,
merge-sequence precedence, explicit scalar/core tags, explicit integer base
tags, non-specific tags, explicit set tags, ordered `!!omap` / `!!pairs`
metadata handoff, timestamp/binary tags, comments outside flow collections,
scalar block-scalar chomping, quoted scalar folding, empty scalar null
semantics, sequence block-scalar metadata, explicit mapping-key parsing,
explicit sequence/map keys, explicit key-only flow-map entries, custom tag
provenance without directives, or plain colon-bearing flow keys. It owns only
bounded `%YAML`/`%TAG` directive handling and primary/named tag-handle
expansion in YAML metadata blocks.

It also does not touch accepted Markdown/HTML reader/writer behavior,
CSL/BibTeX, DOCX/ODT, EPUB3, legacy DOC/CFB, ZIP/OPC, doctemplate, table
geometry, Math/TeX, archive compression, XML/HTML5 DOM, charset/Unicode,
syntax-highlighting, or PDF engine handoff support.

## Follow-Up

Keep multi-document YAML stream handling, writer-side YAML emission,
path-aware tag provenance, full YAML schema validation, and full upstream
runner dependency planning as separate bounded slices.
