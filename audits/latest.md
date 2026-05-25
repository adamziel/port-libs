# Independent Audit - 2026-05-25T01:22Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, `dependency-backlog.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, sampled current `lanes/*/lane-status.json`, and recent Git history through `605e62bb51dc`.

I did not edit lane implementation files, launch agents or tmux sessions, push, read secrets, inspect process environments, credential stores, provider configs, or auth files. Bridge code, generated fixtures, shell-outs, whole applications, external converter wrappers, and hidden process launchers are treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC sample: 2026-05-25T01:22Z
HEAD: 605e62bb51dc
recent history: 605e62bb Correct difftastic integration root count; f390fafc Integrate isolated difftastic Python builtin highlight slice; 2cc543ae Integrate isolated markerpdf Flate predictor slice; a6817c83 Integrate isolated libsqlite JSON remove dispatch slice; 375e8c0d Integrate isolated gitoxide receive-pack control-byte slice; 477df7a8 Include esbuild exports array custom condition slice; 302ec14d Integrate isolated esbuild supervisor-next-20260525T010040Z slice; 54115731 Integrate isolated lightningcss supervisor-next-20260525T005555Z slice
status rows including untracked: 27100
tracked dirty rows: 307
dirty shortstat: 288 files changed, 194790 insertions(+), 64602 deletions(-)
targeted status: all 12 lane manifests and all 12 sampled lane-status files remain dirty/live handoff metadata
exact no-argument root process gate: initial sample matched PID 3239787 (`php tools/run-tests.php`); later 01:21Z sample was empty
root run by this audit: not started; the tree is not a frozen acceptance snapshot and an active root PID was observed at the required gate
dependency backlog: 37 `items`; dashboard shows candidate/deferred/blocked routing only; no active support port has dependency-specific denominator/pass-fail evidence
porting.html source snapshot: main 6cb369fd15d0, generated 2026-05-24 22:29:19 UTC, dashboard average progress 93.3%
```

## Findings

1. **Critical - aggregate acceptance is still blocked by the moving dirty checkout.**
   - Paths: `progress.md`, `tools/run-tests.php`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: small reviewable slices with passing verification and periodically run repo-wide tests from a stable snapshot.
   - Evidence: `HEAD` is `605e62bb51dc`, the checkout has `27100` untracked-inclusive status rows, `307` tracked dirty rows, and a dirty shortstat of `288 files changed`. Every lane manifest and every sampled lane-status file is dirty/live handoff metadata. No lane implementation output is accepted by this audit.

2. **Critical - root verification remains unserialized and unattributable.**
   - Paths: `tools/run-tests.php`, `audits/latest.md`, `progress.md`.
   - Goal requirement at risk: repo-wide verification must be serialized and tied to one frozen accepted batch.
   - Evidence: the required exact gate initially returned active no-argument root PID `3239787` (`php tools/run-tests.php`), so this audit did not start a duplicate. A later sample was empty, but the checkout remained a broad mixed handoff pile, so an audit-owned root run would still not prove one reviewable slice is safe.

3. **Critical - dashboard/status alignment is not trustworthy.**
   - Paths: `porting.html`, `porting-summary.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `porting.html` must show denominator, mapped tests, PHP pass/fail, phase, audit, blocker, and commit for a consistent accepted snapshot.
   - Evidence: `porting.html` says it is generated from source commit `6cb369fd15d0` with average progress `93.3%`, while `HEAD` is `605e62bb51dc` and rows include pending lane text and `pending` commit fields. It mixes accepted source snapshot metadata with newer unaccepted handoffs.

4. **High - manifest/status schemas still prevent reliable progress math.**
   - Paths: `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`, `porting.html`, `porting-summary.json`.
   - Goal requirement at risk: every lane needs comparable upstream denominator, mapped tests, PHP pass/fail, blocker, and commit fields.
   - Evidence: sampled lane-status files still report top-level `upstreamDenominator`, `mappedTests`, and progress fields as `null`, while manifests carry nested lane-specific denominator structures. `phpPass` means assertions, PASS-line counts, or behavior checks depending on lane, making dashboard ratios such as markerPDF `176 / 78 mapped` and Pandoc `2276 / 2276 mapped` misleading.

5. **High - support-library rows are visible routing only, not first-class completed ports.**
   - Paths: `dependency-backlog.json`, `progress.md`, `porting.html`.
   - Goal requirement at risk: support libraries require a bounded native PHP component, activation gate, dependency-specific upstream/spec denominator, mapped fixtures, PHP pass/fail evidence, malformed/corrupt cases where relevant, and as much upstream/spec-suite evidence as can honestly run.
   - Evidence: `dependency-backlog.json` has 37 `items`, but they remain candidate/deferred/blocked routing rows. The item schema has `testExpectation` text but no dependency-specific `upstreamDenominator`, `phpPass`, or `phpFail` ledgers. Candidate/deferred rows must not count as implementation progress.

6. **High - Pandoc rich-format coverage is accounted for but still not satisfied.**
   - Paths: `dependency-backlog.json`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`, `lanes/pandoc/lane-status.json`.
   - Goal requirement at risk: Pandoc must keep DOC, DOCX/OpenXML, PDF input/output handoff, EPUB, ODT/OpenDocument, citations, math, tables, templates, package containers, XML/HTML, Unicode/charset, JSON/YAML metadata, syntax highlighting, and archive/compression visible behind real gates with upstream/spec evidence.
   - Evidence: backlog rows cover the required Pandoc areas, including DOCX/OpenXML, legacy DOC/CFB, PDF handoff/text, EPUB, ODT, doctemplates, citations, math, tables, package containers, XML/HTML, Unicode/charset, JSON/YAML, syntax highlighting, and archive/compression. None is active with a dependency-specific suite attempt, malformed/corrupt ledger, bounded `sudo -n` install-attempt evidence, or PHP pass/fail counts.

7. **High - base lanes continue to advance into inactive reusable dependency territory.**
   - Paths: `dependency-backlog.json`, `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`, `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: dependency expansion must be bounded, gated, tested, shared where appropriate, and not counted as reusable support progress before activation.
   - Evidence: esbuild resolver work remains ahead of deferred `js-package-resolution-core`; rclone WebDAV/XML/gzip work remains ahead of inactive WebDAV/XML/archive rows; Dolt/libsqlite JSON and SQL expression work remain ahead of inactive JSON/SQL rows; Syncthing protocol/route work remains ahead of inactive protobuf/QR/protocol rows; Gitoxide URL/wire/hash/archive needs remain inactive rows.

8. **High - markerPDF progress still risks over-crediting PDF stream plumbing as structured extraction.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`, `lanes/markerpdf/lane-status.json`, `dependency-backlog.json`.
   - Goal requirement at risk: markerPDF must become a native PDF-to-structured-content extraction pipeline suitable for WordPress import and document conversion.
   - Evidence: current markerPDF handoffs are narrow stream/filter/font/operator handling, including Flate predictor and MacRoman/simple-font text extraction slices. Required support rows for PDF text dictionaries, page layout, OCR/layout result ingestion, table geometry, Unicode/charset, and archive/package handling remain inactive and lack dependency-specific pass/fail ledgers.

9. **Medium - broad dirty lane piles are still not reviewable units.**
   - Paths: `lanes/readability/*`, `lanes/quadrable/*`, `lanes/gitoxide/*`, `lanes/rclone/*`, `lanes/syncthing/*`.
   - Goal requirement at risk: prefer small correct slices over broad shallow ports, with committed passing tests.
   - Evidence: sampled statuses advertise many interleaved uncommitted slices in the same lane files. Rclone, Syncthing, Dolt, Gitoxide, Readability, and Quadrable all report large pending stacks whose verification belongs to lane workers or integrators, not to a frozen accepted root snapshot.

10. **Medium - missing-package/full-suite evidence remains too weak for support-row activation.**
    - Paths: `dependency-backlog.json`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`.
    - Goal requirement at risk: missing packages are not final blockers until bounded `sudo -n` installs were attempted or ruled out, and fixture-only credit is insufficient unless broader suites were attempted and honestly bounded.
    - Evidence: the support tracker still lacks install-attempt fields, upstream/spec-suite attempt fields, malformed/corrupt-case ledgers, and dependency-specific pass/fail counts. This is especially important for Pandoc package/document formats and markerPDF/rclone archive or XML-adjacent gates.

## Root Harness Decision

I did not run `php tools/run-tests.php`. The required exact process gate initially found active no-argument root PID `3239787` (`php tools/run-tests.php`), and a later empty sample did not make the tree stable enough for an audit-owned aggregate run.

## Next Intervention

Freeze writers/status publishers/dashboard regeneration/test-loop starters for two stable polls; isolate exactly one owner-free reduced batch; run focused verification and `git diff --check`; run one serialized no-argument root harness only from that same frozen snapshot with an empty exact process gate; normalize manifest/status count units before updating dashboard math; keep support rows inactive unless a real accepted base-lane gate or blocker exists; regenerate dashboard artifacts from the accepted commit; then commit or reject.
