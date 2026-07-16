# Pandoc EPUB3 Package Core Current Base: Agent Display Sequence

Date: 2026-06-08 UTC
Micro-slice: `pandoc-epub3-package-core-current-base-20260608T072235Z`
Base accepted HEAD: `3b2384d9eed3a89fec07a417134e6fbeab3bfd4a`

## Source Truth

- Upstream lane source is bounded EPUB3 package behavior under
  `lanes/pandoc/**`.
- OPF creator and contributor metadata can be refined by package metadata such
  as `display-seq`, `role`, and `file-as`. WordPress import review needs the
  ordered contributor handoff without invoking a reading system or external
  converter.
- No local Pandoc upstream checkout was present under
  `/home/claude/port-libs/.upstream-cache/pandoc`; no Pandoc, Cabal solver,
  Haskell runner, zip/unzip, browser renderer, EPUBCheck, external validator,
  online service, live provider test, or live-service provider test was
  executed.

## Implemented Behavior

- `EpubReader` now exposes `metadata.agentDisplayOrder`.
- The report combines OPF `dc:creator` and `dc:contributor` entries, sorts
  valid positive-integer `display-seq` values first, and retains invalid or
  unsequenced agents after the ordered entries for reviewer triage.
- Each ordered agent preserves kind, source index, id, visible text, `file-as`,
  role refinements, primary role, language, direction, alternate scripts,
  linked OPF metadata resources, and raw refinements.
- Invalid display sequence values produce `invalid-agent-display-seq`
  diagnostics instead of being silently dropped.
- The WordPress EPUB3 package handoff smoke now asserts and prints the ordered
  creator/contributor summary.

## Evidence

- Baseline before editing:
  `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  passed with `1 test files, 2236 assertions, 0 failures`.
- Red-first after adding the focused test:
  `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  failed as expected because `metadata.agentDisplayOrder` was missing:
  `1 test files, 2237 assertions, 1 failures`.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  passed with `1 test files, 2263 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
  passed.

## Status Delta

- Added one focused PHP PASS case.
- Focused EPUB reader assertions increased by `+27`.
- `lane-status.json` `phpPass`: `1560 -> 1561`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1981 -> 1982`.
- EPUB3 package core cases: `6 -> 7`.
- EPUB3 package core assertions: `112 -> 139`.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `EpubReader`,
OPF metadata refinement parsing, OPF metadata link resolution, AST metadata
handoff, and the existing WordPress EPUB package smoke. Full upstream runner
parity remains out of scope until a hydrated pinned Pandoc checkout and a
reviewed non-mutating Cabal plan are available.

## Non-Overlap

This does not repeat accepted EPUB3 OCF container links, OPF unique identifier,
identifier/date/source/bibliographic summaries, title-type and direction
metadata, contributor-role preservation, package/resource refinements,
vendor metadata, nav/NCX, primary navigation policy, page breaks, guide/
collections, fallback/bindings, manifest media/resource reports, XHTML
resource scanning, cover assets, OCF sidecars, encryption, SMIL overlays,
remote resource retention, or EPUB CFI fragment preservation.

Useful follow-up: a bounded OPF contributor authority/file-as refinement review
edge, EPUB CFI target validation, or parser-level XHTML body conversion. Avoid
another metadata inventory-only patch unless it changes a real handoff
contract.
