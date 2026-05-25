# Independent Audit - 2026-05-25T01:45Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, `dependency-backlog.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every `lanes/*/lane-status.json`, and recent Git history through `7a9fd9ea8b6c`.

I did not edit lane implementation files, launch agents or tmux sessions, push, read secrets, inspect process environments, credential stores, provider configs, or auth files. Bridge code, generated fixtures, shell-outs, whole applications, external converter wrappers, and hidden process launchers are treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC sample: 2026-05-25T01:45Z
HEAD: 7a9fd9ea8b6c
recent history: 7a9fd9ea Integrate isolated libsqlite json patch dispatch slice; e764c998 Integrate isolated esbuild node builtin import records slice; 3c4283f0 Integrate isolated lightningcss scoped nesting slice; bff652de Refresh independent audit status; 456e211e Correct gitoxide integration focused count; fae81e90 Integrate isolated gitoxide daemon URL service request slice; 605e62bb Correct difftastic integration root count; f390fafc Integrate isolated difftastic Python builtin highlight slice
status rows including untracked: 27422
tracked dirty rows: 311
dirty shortstat: 288 files changed, 194790 insertions(+), 64602 deletions(-)
targeted status: all 12 lane manifests and all 12 lane-status files remain dirty/live handoff metadata
exact no-argument root process gate: initial required sample matched PID 3286263 (`php tools/run-tests.php`); later sample was empty
root run by this audit: not started; the required gate had an active no-argument root PID and the checkout is not a frozen acceptance snapshot
dependency backlog: 37 `items`; 25 candidate, 11 deferred, 1 blocked; 0 rows have dependency-specific `upstreamDenominator` or `phpPass` ledgers
porting.html source snapshot: main 6cb369fd15d0, generated 2026-05-24 22:29:19 UTC, dashboard average progress 93.3%
```

## Findings

1. **Critical - aggregate acceptance is still blocked by the moving dirty checkout.**
   - Paths: `progress.md`, `tools/run-tests.php`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: commit small, reviewable slices with passing tests and periodically run repo-wide tests from a stable accepted snapshot.
   - Evidence: current `HEAD` is `7a9fd9ea8b6c`, while the checkout still has `27422` untracked-inclusive status rows, `311` tracked dirty rows, and `288 files changed`. All 12 lane manifests and all 12 lane-status files are dirty/live handoff metadata. No lane implementation output is accepted by this audit.

2. **Critical - root verification remains unserialized and unattributable.**
   - Paths: `tools/run-tests.php`, `audits/latest.md`, `progress.md`.
   - Goal requirement at risk: repo-wide verification must be serialized and tied to one frozen accepted batch.
   - Evidence: the required exact pre-root gate returned active no-argument root PID `3286263` (`php tools/run-tests.php`), so this audit did not start a duplicate. A later empty sample did not make the tree stable enough for an audit-owned root run because the checkout is still a broad mixed handoff pile.

3. **Critical - dashboard/status alignment is not trustworthy.**
   - Paths: `porting.html`, `porting-summary.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `porting.html` must show denominator, mapped tests, PHP pass/fail, phase, audit, blocker, and commit for a consistent accepted snapshot.
   - Evidence: `porting.html` claims source commit `6cb369fd15d0` and average progress `93.3%`, while current `HEAD` is `7a9fd9ea8b6c`. Rows also include newer pending lane text and `pending`/`not committed` commit fields, so the page mixes accepted snapshot metadata with unaccepted handoffs.

4. **High - manifest/status schemas still prevent reliable progress math.**
   - Paths: `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`, `porting.html`, `porting-summary.json`.
   - Goal requirement at risk: every lane needs comparable upstream denominator, mapped tests, PHP pass/fail, blocker, and commit fields.
   - Evidence: every manifest sampled has top-level `upstreamDenominator`, `mappedTests`, `phpPass`, and `phpFail` as absent/null while nested `benchmarkDenominator` holds lane-specific structures. Every lane-status file still reports top-level denominator/mapped fields as null. `phpPass` is not normalized: it can mean assertions, PASS-line counts, behavior checks, or selected test files depending on lane.

5. **High - support-library rows are visible routing only, not first-class completed ports.**
   - Paths: `dependency-backlog.json`, `progress.md`, `porting.html`.
   - Goal requirement at risk: support libraries require a bounded native PHP component, activation gate, dependency-specific upstream/spec denominator, mapped fixtures, PHP pass/fail evidence, malformed/corrupt cases where relevant, and as much upstream/spec-suite evidence as can honestly run.
   - Evidence: `dependency-backlog.json` still has 37 routing rows and 0 active support ports. All 37 rows lack dependency-specific `upstreamDenominator` and `phpPass` fields; `testExpectation` prose alone is not implementation evidence.

6. **High - Pandoc rich-format coverage is routed but not satisfied.**
   - Paths: `dependency-backlog.json`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`, `lanes/pandoc/lane-status.json`.
   - Goal requirement at risk: Pandoc must keep DOC, DOCX/OpenXML, PDF input/output handoff, EPUB, ODT/OpenDocument, citations, math, tables, templates, package containers, XML/HTML, Unicode/charset, JSON/YAML metadata, syntax highlighting, and archive/compression visible behind real gates with upstream/spec evidence.
   - Evidence: those areas are visible as candidate/deferred rows (`legacy-doc-cfb-core`, `docx-openxml-core`, `pandoc-pdf-engine-handoff-core`, `epub3-package-core`, `odf-open-document-core`, `citation-bibliography-csl-core`, `math-tex-conversion-core`, `table-geometry-core`, `pandoc-doctemplates-core`, `shared-zip-package-core`, `xml-html5-dom-core`, `unicode-text-repair-width`, `charset-encoding-core`, `json-json5-document-core`, `yaml-metadata-core`, `archive-compression-streams`, and syntax highlighting). None has a dependency-specific suite attempt, malformed/corrupt ledger, bounded `sudo -n` install-attempt evidence, or PHP pass/fail counts.

7. **High - base lanes continue to advance into inactive reusable dependency territory.**
   - Paths: `dependency-backlog.json`, `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`, `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: dependency expansion must be bounded, gated, tested, shared where appropriate, and not counted as reusable support progress before activation.
   - Evidence: esbuild resolver work remains ahead of deferred `js-package-resolution-core`; rclone WebDAV/XML/gzip work remains ahead of inactive WebDAV/XML/archive rows; Dolt/libsqlite JSON and SQL expression work remain ahead of inactive JSON/SQL rows; Syncthing protocol/route work remains ahead of inactive protobuf/QR/protocol rows; Gitoxide URL/wire/hash/archive needs remain inactive rows.

8. **High - markerPDF progress still risks over-crediting low-level PDF plumbing as structured extraction.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`, `lanes/markerpdf/lane-status.json`, `dependency-backlog.json`.
   - Goal requirement at risk: markerPDF must become a native PDF-to-structured-content extraction pipeline suitable for WordPress import and document conversion.
   - Evidence: current markerPDF handoffs are narrow stream/filter/font/operator handling, including MacRoman/simple-font and stream decoding slices. Required support rows for PDF text dictionaries, page layout, OCR/layout result ingestion, table geometry, Unicode/charset, and archive/package handling remain inactive and lack dependency-specific pass/fail ledgers.

9. **Medium - broad dirty lane piles are still not reviewable units.**
   - Paths: `lanes/readability/*`, `lanes/quadrable/*`, `lanes/gitoxide/*`, `lanes/rclone/*`, `lanes/syncthing/*`, `lanes/dolt/*`, `lanes/libsqlite/*`.
   - Goal requirement at risk: prefer small correct slices over broad shallow ports, with committed passing tests.
   - Evidence: lane-status files advertise many interleaved uncommitted slices in the same lane files. Readability reports eighteen interleaved slices, and rclone/syncthing/gitoxide/quadrable/libsqlite/dolt all describe broad pending stacks whose verification belongs to an integrator freeze, not to an audit-owned aggregate run.

10. **Medium - missing-package/full-suite evidence remains too weak for support-row activation.**
    - Paths: `dependency-backlog.json`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`.
    - Goal requirement at risk: missing packages are not final blockers until bounded `sudo -n` installs were attempted or ruled out, and fixture-only credit is insufficient unless broader suites were attempted and honestly bounded.
    - Evidence: the support tracker still lacks install-attempt fields, upstream/spec-suite attempt fields, malformed/corrupt-case ledgers, and dependency-specific pass/fail counts. This is especially important for Pandoc package/document formats and markerPDF/rclone archive or XML-adjacent gates.

## Root Harness Decision

I did not run `php tools/run-tests.php`. The required exact process gate initially found active no-argument root PID `3286263` (`php tools/run-tests.php`), and the later empty sample did not make the tree stable enough for an audit-owned aggregate run.

## Next Intervention

Freeze writers/status publishers/dashboard regeneration/test-loop starters for two stable polls; isolate exactly one owner-free reduced batch; run focused verification and `git diff --check`; run one serialized no-argument root harness only from that same frozen snapshot with an empty exact process gate; normalize manifest/status count units before updating dashboard math; keep support rows inactive unless a real accepted base-lane gate or blocker exists; regenerate dashboard artifacts from the accepted commit; then commit or reject.
