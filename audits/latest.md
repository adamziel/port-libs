# Independent Audit - 2026-05-25T00:30Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, `dependency-backlog.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, sampled current `lanes/*/lane-status.json`, and recent Git history through `5b0db1cad288`.

I did not edit lane implementation files, launch agents or tmux sessions, push, read secrets, inspect process environments, credential stores, provider configs, or auth files. Bridge code, generated fixtures, shell-outs, whole applications, external converter wrappers, and hidden process launchers are treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC sample: 2026-05-25T00:30Z
HEAD: 5b0db1cad288
recent history: 5b0db1ca Refresh independent audit status; 7452fe33 Record isolated queue cleanup; d35bf1db Record remaining isolated queue deferrals; 7b931c65 Record isolated patch queue deferrals; 66c8615c Integrate isolated syncthing watchdog-next-20260524T233852Z slice; 4a58c408 Integrate isolated readability watchdog-next-20260524T233749Z slice; d59ae99c Integrate isolated quadrable watchdog-next-20260524T233443Z slice; 4706a261 Integrate isolated pandoc watchdog-next-20260524T233545Z slice
status rows including untracked: 26233
tracked dirty rows: 288
dirty shortstat: 257 files changed, 195787 insertions(+), 27456 deletions(-)
targeted status: all 12 lane manifests and sampled lane-status files remain dirty/live handoff metadata
exact no-argument root process gate: empty at audit sample; final pre-finish recheck later matched PID 2844342 owned by claude (`php tools/run-tests.php`)
root run by this audit: not started; the tree is not a frozen acceptance snapshot
dependency backlog: 37 rows; statuses blocked=1, candidate=25, deferred=11; 0 active support ports; all 37 rows lack dependency-specific denominator/pass-fail fields
porting.html source snapshot: main 6cb369fd15d0, generated 2026-05-24 22:29:19 UTC, dashboard average progress 93.3%
```

## Findings

1. **Critical - aggregate acceptance is still blocked by the moving dirty checkout.**
   - Paths: `progress.md`, `tools/run-tests.php`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: small reviewable slices with passing verification and periodically run repo-wide tests from a stable snapshot.
   - Evidence: `HEAD` is `5b0db1cad288`, but the checkout has `26233` untracked-inclusive status rows, `288` tracked dirty rows, and `257 files changed` in the dirty shortstat. Every lane manifest and sampled lane-status file is dirty/live handoff metadata, so no lane output is accepted by this audit.

2. **Critical - a no-argument root run would still be unattributable.**
   - Paths: `tools/run-tests.php`, `audits/latest.md`, `progress.md`.
   - Goal requirement at risk: repo-wide verification must be serialized and tied to one frozen accepted batch.
   - Evidence: `pgrep -af '^php tools/run-tests\.php$'` returned no active root process, but the tree is a broad mixed handoff pile. I did not start `php tools/run-tests.php`; a root result from this state would not prove one reviewable slice is safe.

3. **Critical - dashboard/status alignment is not trustworthy.**
   - Paths: `porting.html`, `porting-summary.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `porting.html` must show denominator, mapped tests, PHP pass/fail, phase, audit, blocker, and commit for a consistent accepted snapshot.
   - Evidence: `porting.html` says it is generated from source commit `6cb369fd15d0` with average progress `93.3%`, while `HEAD` is `5b0db1cad288` and rows include pending newer handoff text such as markerPDF `2026-05-25`, `pending`, and truncated commit fields. This mixes accepted and unaccepted status.

4. **High - manifest/status schemas still prevent reliable progress math.**
   - Paths: `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`, `porting.html`, `porting-summary.json`.
   - Goal requirement at risk: every lane needs comparable upstream denominator, mapped tests, PHP pass/fail, blocker, and commit fields.
   - Evidence: sampled `lane-status.json` files still have top-level `upstreamDenominator`, `mappedTests`, and `benchmarkSource` as `null`, while manifests carry nested lane-specific denominator shapes. `phpPass` continues to mean assertions, behavior tests, or PASS-line counts depending on lane, which makes dashboard ratios like markerPDF `176 / 78` and Pandoc `2276 / 2276` misleading.

5. **High - support-library rows are visible routing only, not first-class completed ports.**
   - Paths: `dependency-backlog.json`, `progress.md`, `porting.html`.
   - Goal requirement at risk: support libraries require a bounded native PHP component, activation gate, dependency-specific upstream/spec denominator, mapped fixtures, PHP pass/fail evidence, malformed/corrupt cases where relevant, and as much upstream/spec-suite evidence as can honestly run.
   - Evidence: the backlog has 37 rows but no active support port. All 37 rows lack dependency-specific denominator/pass-fail fields. Candidate/deferred rows should not be counted as implementation progress.

6. **High - Pandoc rich-format coverage is accounted for but still not satisfied.**
   - Paths: `dependency-backlog.json`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`, `lanes/pandoc/lane-status.json`.
   - Goal requirement at risk: Pandoc must keep DOC, DOCX/OpenXML, PDF input/output handoff, EPUB, ODT/OpenDocument, citations, math, tables, templates, package containers, XML/HTML, Unicode/charset, JSON/YAML metadata, syntax highlighting, and archive/compression visible behind real gates with upstream/spec evidence.
   - Evidence: Pandoc-related backlog rows exist for DOCX/OpenXML, legacy DOC/CFB, PDF handoff/text, EPUB, ODT, doctemplates, citations, math, tables, package containers, XML/HTML, Unicode/charset, JSON/YAML, syntax highlighting, and archive/compression. None is active with dependency-specific suite attempts, malformed/corrupt ledgers, bounded `sudo -n` install-attempt evidence, or PHP pass/fail counts.

7. **High - base lanes continue to advance into inactive reusable dependency territory.**
   - Paths: `dependency-backlog.json`, `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`, `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: dependency expansion must be bounded, gated, tested, shared where appropriate, and not counted as reusable support progress before activation.
   - Evidence: esbuild resolver work remains ahead of deferred `js-package-resolution-core`; rclone WebDAV/XML/gzip work remains ahead of inactive WebDAV/XML/archive rows; Dolt/libsqlite JSON and SQL expression work remain ahead of inactive JSON/SQL rows; Syncthing protocol/route work remains ahead of inactive protobuf/QR/protocol rows; Gitoxide URL/wire/hash/archive needs remain inactive rows.

8. **High - markerPDF progress still risks over-crediting PDF stream plumbing as structured extraction.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`, `lanes/markerpdf/lane-status.json`, `dependency-backlog.json`.
   - Goal requirement at risk: markerPDF must become a native PDF-to-structured-content extraction pipeline suitable for WordPress import and document conversion.
   - Evidence: current markerPDF handoffs are narrow stream/filter/font/operator handling. Required support rows for PDF text dictionaries, page layout, OCR/layout result ingestion, table geometry, Unicode/charset, and archive/package handling remain inactive and lack dependency-specific pass/fail ledgers.

9. **Medium - broad dirty lane piles are still not reviewable units.**
   - Paths: `lanes/readability/*`, `lanes/quadrable/*`, `lanes/gitoxide/*`, `lanes/rclone/*`, `lanes/syncthing/*`.
   - Goal requirement at risk: prefer small correct slices over broad shallow ports, with committed passing tests.
   - Evidence: sampled statuses advertise many interleaved uncommitted slices in the same lane files. Readability alone reports eighteen interleaved fixture slices; Quadrable reports a long mixed docopt/sync/LMDB/proof stack; Gitoxide says prior stacks cannot be independently isolated from the dirty worktree.

10. **Medium - missing-package/full-suite evidence remains too weak for support-row activation.**
    - Paths: `dependency-backlog.json`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`.
    - Goal requirement at risk: missing packages are not final blockers until bounded `sudo -n` installs were attempted or ruled out, and fixture-only credit is insufficient unless broader suites were attempted and honestly bounded.
    - Evidence: the support tracker still lacks install-attempt fields, upstream/spec-suite attempt fields, malformed/corrupt-case ledgers, and dependency-specific pass/fail counts.

## Root Harness Decision

I did not run `php tools/run-tests.php`. The required exact process gate was empty at the audit sample, but the checkout is not stable enough for a meaningful audit-owned aggregate run because all lane manifests/status files remain dirty/live and the dashboard is not generated from the current accepted state. A final pre-finish recheck later found an active no-argument root harness, PID 2844342 owned by `claude` (`php tools/run-tests.php`); no duplicate was started.

## Next Intervention

Freeze writers/status publishers/dashboard regeneration/test-loop starters for two stable polls; isolate exactly one owner-free reduced batch; run focused verification and `git diff --check`; run one serialized no-argument root harness only from that same frozen snapshot with an empty exact process gate; normalize manifest/status count units before updating dashboard math; keep support rows inactive unless a real accepted base-lane gate or blocker exists; regenerate dashboard artifacts from the accepted commit; then commit or reject.
