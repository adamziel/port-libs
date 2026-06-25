# Supervisor Goal: EPUB3 Missing-Item Closure

## Outcome
- Keep `port_libs` polecats assigned to concrete EPUB3 parity gaps until the remaining in-scope items are either implemented with tests or explicitly proven out of scope.
- Maintain a current lane map that separates direct PHP EPUB3 support from full Pandoc runner parity, EPUBCheck-grade validation, browser/rendering behavior, and other broader format work.
- Integrate only work that lands on the recovered EPUB baseline and passes focused EPUB reader/writer tests plus the full Pandoc lane when practical.

## Intensity
- Level: high.
- Starting workers: 8-10 independent polecats when slots are healthy.
- Scaling rule: launch only for disjoint EPUB3 gaps with an inspectable artifact, test, or diff; do not multiply workers against the same ambiguous problem.

## Non-Goals
- JavaScript execution, browser runtime behavior, or dynamic scripted EPUB content.
- DRM, crypto authorization, decryption, or real XML signature cryptographic verification.
- PDF, DOCX, ODT, CSL, or generic non-EPUB format completion.
- Shell-out based format claims; direct EPUB support must remain native PHP.

## Ground Truth
- Recovered baseline: `origin/epub3-recovery-baseline-20260625T091320Z`, latest local commit `79d36bbe75`.
- Current evidence: `lanes/pandoc/lane-status.json` reports 1,153 passes, 0 failures, and 22,134 assertions for the recovered EPUB lane.
- Static upstream inventory: `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- Core code: `lanes/pandoc/src/EpubReader.php`, `lanes/pandoc/src/EpubWriter.php`, `lanes/pandoc/src/EpubPackageMetadataReader.php`.
- Core tests: `lanes/pandoc/tests/EpubReaderTest.php`, `lanes/pandoc/tests/EpubWriterTest.php`, `lanes/pandoc/tests/EpubPackageMetadataReaderTest.php`.

## Worker Topology
- supervisor: owns lane decomposition, dispatch, integration review, final verification, and anti-drift enforcement.
- lane-epub-fixtures: map remaining upstream EPUB fixtures to native PHP fixtures or prove no additional direct behavior is needed.
- lane-package-validation: close EPUBCheck-relevant OPF/container/package diagnostics that are still practical without embedding EPUBCheck.
- lane-nav-structure: stress primary and alternate nav, TOC, page-list, landmark, and NCX structural edge cases.
- lane-rootfile-graph: stress alternate rootfile and multi-rendition package graph combinations beyond the currently covered structured paths.
- lane-xhtml-dom: add bounded XHTML/HTML5 DOM fidelity where it affects import/export semantics, excluding JS runtime and browser layout.
- lane-media-layout: add remaining fixed-layout, page progression, rendition, and media-overlay validation that can be expressed as package metadata behavior.
- lane-mathml: close bounded TeX/MathML writer/parser gaps that affect EPUB output, while documenting non-goal full PlainMath parity.
- lane-epub2-output: closed by scope decision; no additional EPUB2 output parity is in scope for this EPUB3 closure beyond bounded OPF 2.0/NCX compatibility behavior.
- lane-evaluator: review worker artifacts, reject non-EPUB drift, and run focused and full lane verification.

## Workflow
1. Create fresh EPUB-specific beads for each lane and dispatch them to `port_libs` polecats.
2. Require every worker to leave a diff, test, fixture, or audit note before claiming completion.
3. Monitor polecats and merge queue; recover or replace stuck workers with smaller scoped prompts.
4. Integrate one coherent batch at a time onto the recovered baseline.
5. Run `php -l` on touched files, focused EPUB tests, and `php tools/run-tests.php lanes/pandoc/tests` before marking a lane complete.

## Quality Gates
- No worker may count JS runtime, DRM, decryption, or XML signature crypto as required work.
- New behavior must have PHP tests or a written proof that the gap is out of scope.
- EPUB work must avoid broad non-EPUB format blockers.
- Generated diagnostics must be scoped enough to avoid cascades in existing EPUB tests.
- Full lane verification is required before final closure unless a worker is explicitly audit-only.

## Rejected Distractions
- Closing old spawn-storm/recovery cleanup beads as if they were missing EPUB3 work.
- Re-opening deleted `EpubPackage` / `EpubPackageReader` side architectures.
- Polishing inventory wording without adding tests, implementation, or a concrete out-of-scope decision.
- Attempting to satisfy EPUBCheck by shelling out to EPUBCheck and calling that native support.

## Scope Decisions
- EPUB2 output: deeper EPUB2 writer parity remains out of scope for this EPUB3 closure. The only EPUB2 behavior that may count here is bounded compatibility needed to keep OPF 2.0/NCX edges stable for the EPUB3 package work, such as OPF 2.0 package shape, NCX navigation, guide references, linear spine handling, and suppression of EPUB3-only metadata when an explicit bounded EPUB2 mode exists.
- EPUB2 non-goals include full upstream Pandoc EPUB2 writer parity, reading-system compatibility matrices, EPUBCheck-backed EPUB2 validation, arbitrary EPUB2 fixture corpus parity, legacy renderer or device-specific behavior, and claiming `epub2` as a direct supported output format while the format registry marks it unsupported.

## Final Acceptance Criteria
- All in-scope lane beads are closed with evidence or superseded by a stricter completed lane.
- `gt mq list port_libs` is empty or only contains already-reviewed merge work.
- `gt polecat list port_libs` shows no active EPUB3 workers stalled with unpushed work.
- The recovered baseline includes the integrated EPUB3 closure commits.
- Focused EPUB reader/writer tests and the full Pandoc lane pass from the integrated baseline.
