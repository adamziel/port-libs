# Independent Audit - 2026-05-24T15:02Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json`, all 12
`lanes/*/lane-status.json`, `dependency-backlog.json`, and recent Git history
through the observed `57ce7c78 Clarify integration hold process sample` head.
I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider configs,
or auth files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24 14:59-15:02
observed HEAD movement: fee4be69 -> 3f491c42 -> fafdce01 -> 57ce7c78
recent history: 57ce7c78 Clarify integration hold process sample; fafdce01 Record root retry follow-up; 3f491c42 Record integration hold status; fee4be69 Refresh independent audit status; 7ecbef43 Record integration hold and support audit
default status rows including untracked: 18888 -> 18945
git diff --shortstat: 329 files changed, 252081 insertions(+), 31613 deletions(-) -> 329 files changed, 252431 insertions(+), 31587 deletions(-)
dashboard snapshot: porting.html and porting-summary.json still publish source 89260857cc71 generated 2026-05-24 12:29:46 UTC; current observed HEAD is 57ce7c7826e1
dependency backlog: 37 rows (0 active, 25 candidate, 1 blocked, 11 deferred), updated 2026-05-24 12:29:10 UTC
json validation by this audit: jq empty passed for all 12 lane manifests and all present lane-status files
root run by this audit: not started
```

Required pre-root process gate:

```text
15:00Z pgrep -af '^php tools/run-tests\.php( |$)' matched focused Syncthing PID 403381:
  php tools/run-tests.php lanes/syncthing/tests/ProgressEmitterSchedulerTest.php ... lanes/syncthing/tests/RequestServerTest.php
15:00Z owner sampling: PID 403381 exited before ps owner capture
15:02Z pgrep -af '^php tools/run-tests\.php( |$)': no rows
```

I did not start `php tools/run-tests.php`. The final exact process gate was
clear, but the checkout was not stable enough for an audit-owned root run:
`HEAD`, status counts, and shortstat changed during the audit window.

Current manifest/status sample versus the published dashboard:

```text
lane          current status/manifest                 dashboard
difftastic    3422 pass, 956/1169 mapped              3245 pass, 851/1077
dolt          430 pass, 613/613 mapped                425 pass, 613/613
esbuild       448 pass, 448/2567 mapped               429 pass, 429/2567
gitoxide      7331 pass, 2877/2877 mapped             7152 pass, 2877/2877
libsqlite     362 pass, 362/1589 mapped               348 pass, 349/1589
LightningCSS  4163 pass, 2846/3548 mapped             4065 pass, 2765/3548
markerPDF     499 pass, 362/411 mapped                484 pass, 347/396
pandoc        376 pass, 2039/2276 mapped              362 pass, 1891/2276
quadrable     240 pass, 55/55 mapped                  232 pass, 55/55
rclone        944 pass, 944/1601 mapped               906 pass, 906/1601
readability   3676 assertions, 1984/1984 mapped       3545 pass, 1984/1984
syncthing     8305 pass, 658/658 mapped               7902 pass, 658/658
```

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `goal.md:3`, `goal.md:35`, `progress.md:15`,
     `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`,
     `lanes/esbuild/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/libsqlite/lane-status.json:13`,
     `lanes/lightningcss/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/pandoc/lane-status.json:13`,
     `lanes/quadrable/lane-status.json:13`,
     `lanes/rclone/lane-status.json:13`,
     `lanes/readability/lane-status.json:13`,
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: the coordination system must track current
     blocker/latest-commit state, and slices should be reviewable, verified,
     and committed before they count as progress.
   - Evidence: all 12 lane status files still report `pending`,
     `uncommitted`, `not committed`, or stale ownership prose in
     `latestCommit`. The checkout moved from `fee4be69` through several
     commits to `57ce7c78` while this audit sampled it, and the dirty aggregate
     remains broad at about `18,945` status rows.

2. **Critical - root verification remains unattributable to a frozen tree.**
   - Paths: `tools/run-tests.php`, `goal.md:49`, `progress.md:49`,
     `lanes/dolt/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: repo-wide tests and static checks must run
     periodically and failures must be recorded honestly from a stable source
     snapshot.
   - Evidence: the required exact process gate briefly matched focused
     Syncthing PID `403381`, then cleared. I still did not start a no-argument
     root harness because `HEAD` and the dirty tree changed during the same
     window. Recent `Record root retry follow-up` history is moving-tree status
     evidence, not an accepted root result for this audit snapshot.

3. **High - `porting.html` and `porting-summary.json` are stale against every lane.**
   - Paths: `porting.html:32`, `porting.html:34`, `porting.html:35`,
     `porting.html:56`, `porting.html:62`,
     `porting-summary.json:3`, `porting-summary.json:4`, `goal.md:3`.
   - Goal requirement at risk: the dashboard must show current denominator,
     mapped tests, PHP pass/fail, WordPress scenarios, phase, audit, current
     work, blocker, and commit.
   - Evidence: dashboard artifacts still claim source `89260857cc71`
     generated at `2026-05-24 12:29:46 UTC`, while the observed current head is
     `57ce7c78`. All 12 lane rows now have newer manifest/status counts than
     the published dashboard table.

4. **High - support-library coverage is still backlog-only, not first-class lane-granular work.**
   - Paths: `dependency-backlog.json:7`, `dependency-backlog.json:25`,
     `dependency-backlog.json:81`, `dependency-backlog.json:129`,
     `dependency-backlog.json:145`, `dependency-backlog.json:163`,
     `dependency-backlog.json:179`, `dependency-backlog.json:214`,
     `dependency-backlog.json:233`, `dependency-backlog.json:256`,
     `dependency-backlog.json:272`, `dependency-backlog.json:340`,
     `dependency-backlog.json:365`, `dependency-backlog.json:413`,
     `dependency-backlog.json:629`, `progress.md:32`, `goal.md:35`.
   - Goal requirement at risk: support libraries need the same granularity as
     lanes: bounded native PHP component, activation gate,
     dependency-specific upstream/spec denominator, mapped fixtures, PHP
     pass/fail evidence, malformed/corrupt cases where relevant, and as much
     upstream/spec-suite evidence as can actually run.
   - Evidence: the backlog has 37 rows and 0 active bounded support ports.
     Pandoc's required rich-function areas are routed to gated rows: DOC
     (`legacy-doc-cfb-core`), DOCX/OpenXML (`docx-openxml-core`), PDF input and
     output handoff (`pdf-text-dictionary-core`,
     `pandoc-pdf-engine-handoff-core`), EPUB (`epub3-package-core`), ODT
     (`odf-open-document-core`), templates (`pandoc-doctemplates-core`),
     citations (`citation-bibliography-csl-core`), math
     (`math-tex-conversion-core`), tables (`table-geometry-core`), package
     containers (`shared-zip-package-core`), XML/HTML (`xml-html5-dom-core`),
     Unicode/charset (`unicode-text-repair-width`, `charset-encoding-core`),
     and archive/compression (`archive-compression-streams`). The gap is that
     none has a dependency-specific manifest, PHP ledger, malformed/corrupt
     evidence, or bounded install-attempt/ruled-out note.

5. **High - markerPDF still mixes native PDF progress with plan-only external/runtime boundaries.**
   - Paths: `goal.md:30`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:10`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/lane-status.json:5`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/markerpdf/src/ChunkConversionPlanner.php:17`,
     `lanes/markerpdf/src/ModelPipelinePlanner.php:31`,
     `lanes/markerpdf/src/MarkerServerAdapter.php:112`.
   - Goal requirement at risk: wrappers around JS/Rust/Go/C binaries,
     shell-outs, bridge calls, whole applications, and external converter
     wrappers must not count as native deliverables.
   - Evidence: markerPDF now has real native PDF extraction movement
     (`362/411` mapped, `499` local pass). The same denominator/status surface
     still carries marker server/app, Streamlit/FastAPI/Uvicorn, chunk shell
     lifecycle, OCR install, Tesseract/OCRMyPDF/Ghostscript, Poetry/model
     runtime, and Pandoc/XeLaTeX planning. These must stay blockers,
     supplied-runner contracts, or non-goals until replaced by bounded native
     support components.

6. **High - lane-local dependency helpers are expanding before shared support gates are active.**
   - Paths: `dependency-backlog.json:7`, `dependency-backlog.json:629`,
     `lanes/markerpdf/src/BenchmarkArchiveInspector.php:9`,
     `lanes/markerpdf/src/BenchmarkArchiveInspector.php:36`,
     `lanes/rclone/src/VfsZipArchive.php:10`,
     `lanes/rclone/src/GzipReader.php:7`,
     `lanes/rclone/src/VfsWebDavCompression.php:8`,
     `lanes/rclone/src/VfsVirtualTree.php:653`.
   - Goal requirement at risk: dependency expansion should be bounded,
     activation-gated, dependency-specific, tested, and shared where the same
     rich function is needed across lanes.
   - Evidence: markerPDF uses lane-local `ZipArchive` benchmark inspection,
     while rclone carries lane-local ZIP, gzip, and WebDAV compression helpers.
     These may be valid lane scaffolding, but they cannot count as
     `shared-zip-package-core` or `archive-compression-streams` progress until
     those rows have their own manifests, malformed archive cases, PHP ledgers,
     and reuse contracts.

7. **Medium - Pandoc rich-function coverage is routed, but not proven.**
   - Paths: `goal.md:12`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:328`,
     `lanes/pandoc/lane-status.json:6`,
     `lanes/pandoc/lane-status.json:12`, `dependency-backlog.json:81`,
     `dependency-backlog.json:129`, `dependency-backlog.json:145`,
     `dependency-backlog.json:163`, `dependency-backlog.json:179`,
     `dependency-backlog.json:214`, `dependency-backlog.json:233`,
     `dependency-backlog.json:256`, `dependency-backlog.json:272`,
     `dependency-backlog.json:340`, `dependency-backlog.json:365`,
     `dependency-backlog.json:629`.
   - Goal requirement at risk: Pandoc needs real upstream/spec-suite evidence
     for essential rich document/PDF conversion rather than fixture-only credit
     unless broader suites were attempted and honestly bounded.
   - Evidence: Pandoc reports `2039/2276` mapped and `376` local behavior
     tests, but the full upstream Haskell runner remains unexecuted. The latest
     support rows cover the required categories; they just remain inactive and
     unmanifested.

8. **Medium - near-complete percentages overstate accepted parity.**
   - Paths: `porting.html:32`, `porting.html:56`, `porting.html:62`,
     `porting-summary.json:8`, `lanes/*/lane-status.json`, `goal.md:35`,
     `goal.md:37`.
   - Goal requirement at risk: passing tests are not enough; upstream tests are
     the source of truth where possible, and hard gaps must be blockers or
     future slices.
   - Evidence: the dashboard still reports `98.3%` average progress and most
     lanes show 98-99%, but every lane handoff is pending or uncommitted, no
     frozen root result is attached to the current aggregate, multiple upstream
     runners are static/bounded/unexecuted, and no support-library row is
     active.

9. **Medium - manifest/status schema remains too free-form for durable coordination.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/dolt/lane-status.json:4`, `lanes/dolt/lane-status.json:13`,
     `lanes/esbuild/lane-status.json:13`, `porting-summary.json:25`,
     `porting-summary.json:42`, `goal.md:3`.
   - Goal requirement at risk: progress must be durable and machine-checkable
     across upstream denominator, mapped tests, PHP pass/fail counts, phase,
     audit status, blocker, and latest commit.
   - Evidence: several `benchmarkDenominator.status` values are concatenated
     slice histories rather than bounded status enums, and dashboard commit
     fields contain truncated prose such as `pending`, `not com`, and
     `uncommi`. That makes the published state look precise while ownership
     and acceptance remain ambiguous.

## Next Intervention

Freeze lane writers, focused/root runners, dashboard/status publishers,
support-library scouts, capacity rows, and integration-hold writers. Require
two stable polls of `HEAD`, tracked/default status counts, shortstat,
`pgrep -af '^php tools/run-tests\.php( |$)'`, dashboard/dependency counts,
lane status timestamps, and relevant log mtimes. Accept exactly one owner-free
lane batch at a time, normalizing manifest/status schema and commit fields
before claiming progress. Promote support libraries only behind an accepted
base-lane gate or true component blocker, each with its own manifest and
malformed-case evidence. Regenerate `progress.md`, `porting.html`, and
`porting-summary.json` from the accepted commit, then run one serialized
no-argument root harness only if the exact process gate remains empty on that
frozen snapshot.
