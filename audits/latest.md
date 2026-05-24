# Independent Audit - 2026-05-24T15:50Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json`, all 12
`lanes/*/lane-status.json`, `dependency-backlog.json`, and recent Git history
through `2849a3d1 Refresh independent audit status`. I did not edit lane
implementation files, launch agents or tmux sessions, push, read secrets,
inspect process environments, credential stores, provider configs, or auth
files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24 15:48:55, 15:49:05, 15:50:22
observed HEAD: 2849a3d1c035
recent history: 2849a3d1 Refresh independent audit status; 3a76b4ab Refresh independent audit status; 06a6a69e Refresh independent audit status; c959e048 Refresh independent audit status
tracked dirty files: 330
default status rows including untracked: 19361 -> 19361 -> 19361
git diff --shortstat: 330 files changed, 258804 insertions(+), 32461 deletions(-) -> 330 files changed, 258816 insertions(+), 32461 deletions(-) -> 330 files changed, 258980 insertions(+), 32461 deletions(-)
dashboard snapshot: porting.html and porting-summary.json still publish source 89260857cc71 generated 2026-05-24 12:29:46 UTC
dependency backlog: 37 rows (0 active, 25 candidate, 1 blocked, 11 deferred)
json validation by this audit: jq empty passed for all 12 lane manifests, all 12 lane-status files, dependency-backlog.json, and porting-summary.json
root run by this audit: not started
```

Required pre-root process gate:

```text
15:48Z pgrep -af '^php tools/run-tests\.php$': 934281 php tools/run-tests.php
15:48Z owner sample: ps -p 934281 returned no row because the process exited before owner/elapsed sampling
15:49Z pgrep -af '^php tools/run-tests\.php$': no rows
15:50Z pgrep -af '^php tools/run-tests\.php$': no rows
```

I did not start `php tools/run-tests.php`. The exact no-argument gate was
briefly occupied, then empty, but the checkout changed in the same sampling
window. A root result from this source would not be a frozen acceptance
checkpoint.

Current manifest/status sample versus the published dashboard:

```text
lane          current manifest/status                 dashboard
difftastic    manifest 1000/1203, status 3494 pass    3245 pass, 851/1077
dolt          manifest 613/613, status 434 pass       425 pass, 613/613
esbuild       manifest 454/2567, status 453 pass      429 pass, 429/2567
gitoxide      manifest 2877/2877, status 7373 pass    7152 pass, 2877/2877
libsqlite     manifest 366/1589, status 366 pass      348 pass, 349/1589
LightningCSS  manifest 2871/3548, status 4188 pass    4065 pass, 2765/3548
markerPDF     manifest 367/416, status 504 pass       484 pass, 347/396
pandoc        manifest 2065/2276, status 381 pass     362 pass, 1891/2276
quadrable     manifest 55/55, status 244 pass         232 pass, 55/55
rclone        manifest 954/1601, status 954 pass      906 pass, 906/1601
readability   manifest 1984/1984, status 3714 pass    3545 pass, 1984/1984
syncthing     manifest 658/658, status 8385 pass      7902 pass, 658/658
```

## Findings

1. **Critical - the repo is still a moving dirty aggregate, not an acceptance baseline.**
   - Paths: `progress.md:50`, `lanes/*/lane-status.json:13`,
     recent Git history through `2849a3d1`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and
     `goal.md:52` require small reviewable slices, verified/committed
     handoffs, and a visible stable baseline for every lane.
   - Evidence: tracked dirty files stayed at `330`, but shortstat moved from
     `258804` to `258980` insertions during this audit. Every lane status still
     reports `pending`, `uncommitted`, or supervisor/integrator-owned commit
     ownership, and recent history is almost entirely audit/integration status
     churn rather than accepted implementation slices.

2. **Critical - `porting.html` and `porting-summary.json` are stale against every lane.**
   - Paths: `porting.html:34-38`, `porting.html:56-67`,
     `porting-summary.json:2-8`, `porting-summary.json:27-42`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:45`, and `goal.md:52`
     require current denominator, mapped tests, PHP pass/fail, phase, audit,
     blocker, and commit in the public dashboard.
   - Evidence: the dashboard still publishes source `89260857cc71` generated
     `2026-05-24 12:29:46 UTC`, while current `HEAD` is `2849a3d1c035` and
     every lane has newer manifest/status counts. It still reports, for
     example, Dolt `425 pass` while lane status reports `434`, markerPDF
     `484` while status reports `504`, rclone `906` while status reports
     `954`, and Syncthing `7902` while status reports `8385`.

3. **High - there is still no valid no-argument root acceptance run.**
   - Paths: `tools/run-tests.php`, `progress.md:50`,
     `lanes/*/lane-status.json:12-13`.
   - Goal requirement at risk: `goal.md:49` requires periodic repo-wide tests
     and honest failure recording; the user also forbids duplicate root
     harnesses.
   - Evidence: the exact pre-root gate briefly matched PID `934281`
     (`php tools/run-tests.php`) at 15:48 UTC and then cleared before owner
     sampling; later exact gates returned no rows. I still did not start a
     duplicate or a fresh root run because the checkout was changing during the
     same sample and no owner-free lane batch had been accepted.

4. **High - support-library coverage is still backlog-only, not first-class lane-granular work.**
   - Paths: `dependency-backlog.json:1-4`, `dependency-backlog.json:7-22`,
     `dependency-backlog.json:25-42`, `dependency-backlog.json:81-94`,
     `dependency-backlog.json:129-176`, `dependency-backlog.json:179-230`,
     `dependency-backlog.json:233-286`, `dependency-backlog.json:340-410`,
     `dependency-backlog.json:413-426`, `dependency-backlog.json:629-646`,
     `porting.html:72-78`.
   - Goal requirement at risk: the latest support-library directives require a
     bounded native PHP component, activation gate, dependency-specific
     upstream/spec denominator, mapped fixtures, PHP pass/fail evidence,
     malformed/corrupt cases where relevant, bounded install attempts where
     relevant, and as much upstream/spec-suite evidence as can honestly run.
   - Evidence: the tracker has 37 rows and 0 active support ports. Pandoc's
     DOC, DOCX/OpenXML, PDF input/output handoff, EPUB, ODT/OpenDocument,
     templates, citations, math, tables, package containers, XML/HTML,
     Unicode/charset, JSON/YAML metadata, syntax highlighting, and
     archive/compression categories are visible as gated rows, but none has a
     support-library manifest, PHP ledger, malformed/corrupt evidence,
     accepted activation record, or bounded install-attempt/ruled-out note.

5. **High - Pandoc's ledger is internally inconsistent and still misses the original rich-kernel bar.**
   - Paths: `goal.md:12`, `lanes/pandoc/lane-status.json:5-14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:342-346`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:1306-1309`,
     `lanes/pandoc/tests/MarkdownReaderTest.php:2157`.
   - Goal requirement at risk: Pandoc must be a document conversion kernel
     with a shared AST plus readers/writers for Markdown, HTML, WXR,
     EPUB/PDF-oriented intermediate forms, and WordPress block output.
   - Evidence: lane status says `2,080` focused checks and `381` PHP behavior
     tests, while the manifest canonical `benchmarkDenominator.mapped` remains
     `2065`. The manifest latest note says the iframe slice is already mapped,
     but manifest `nextTask` still proposes that same iframe slice. `rg` finds
     `WXR` only in a Markdown-reader test string, not a visible WXR
     reader/writer capability. Full Haskell runner parity remains unexecuted,
     and PDF/package/citation/math support remains gated and inactive.

6. **High - markerPDF still mixes native PDF slices with plan-only external/runtime evidence.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13-19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1180-1221`,
     `dependency-backlog.json:272-286`, `dependency-backlog.json:289-339`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`, and the latest
     support-library directives say whole applications, converter wrappers,
     model/runtime stacks, and hidden shell-outs are non-progress unless they
     are explicit temporary oracle tooling.
   - Evidence: the manifest status/denominator still folds in many
     `*-plan`, server/app/upload/runtime/model/workflow entries beside native
     PDF extraction behavior. The current native xref-stream PDF slice may be
     useful, but richer PDF/OCR/table work should move behind bounded
     `pdf-text-dictionary-core`, `layout-ocr-result-core`, and
     `table-geometry-core` support rows before it is credited as reusable
     support-library progress.

7. **Medium - 98-99 percent dashboard progress overstates accepted native parity.**
   - Paths: `porting.html:32`, `porting.html:56-67`,
     `lanes/*/lane-status.json:12-14`.
   - Goal requirement at risk: `goal.md:35-40` requires meaningful fixture
     parity, edge-case coverage, error behavior, upstream tests as source of
     truth, and explicit blockers for hard features.
   - Evidence: the dashboard reports `98.3%` average and most lanes at
     `98-99%`, but all current handoffs are unaccepted in a dirty moving
     worktree, the no-argument root harness is pending/non-attributable, many
     upstream runners are static-only or bounded, and no support-library row is
     active.

## Next Intervention

Freeze lane writers, focused/root runners, dashboard/status publishers,
support-library scouts, capacity rows, and integration-hold writers long enough
to get two stable polls of `HEAD`, tracked/default status counts, shortstat,
exact root gate `pgrep -af '^php tools/run-tests\.php$'`,
dashboard/dependency counts, lane status timestamps, and relevant log mtimes.
Then accept or reject exactly one owner-free lane batch at a time. The first
batch should normalize manifest/status counts and stale next-task metadata
before dashboard regeneration; Pandoc's `2065` versus `2080` mapped count and
the stale 12:29 dashboard are the clearest metadata fixes. After focused
verification plus `git diff --check`, regenerate dashboard artifacts from the
accepted commit and run one serialized no-argument root result only on that
frozen snapshot.
