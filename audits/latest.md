# Independent Audit - 2026-05-24T14:44Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json`, all 12
`lanes/*/lane-status.json`, `dependency-backlog.json`, targeted
support-library surfaces, and recent Git history through `7ab00951 Record
integration hold status`. I did not edit lane implementation files, launch
agents or tmux sessions, push, read secrets, inspect process environments,
credential stores, provider configs, or auth files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24 14:35-14:44
HEAD moved during this audit window: fc4ddabfb7ab -> ede518e48a77 -> 7ab009516f18
recent history: 7ab00951 Record integration hold status; ede518e4 Record integration hold status; fc4ddabf Refresh independent audit status; 275cf4fb Record integration hold status; 078670a0 Record integration hold status
tracked dirty rows moved: 329 -> 332 -> 331
default status rows including untracked moved: 18743 -> 18747 -> 18746
git diff --shortstat moved during sampling: 329 files changed, 249003 insertions(+), 30512 deletions(-) -> 331 files changed, 249915 insertions(+), 31201 deletions(-)
dashboard snapshot: porting.html and porting-summary.json still publish source 89260857cc71 generated 2026-05-24 12:29:46 UTC; latest sampled HEAD is 7ab009516f18
dependency backlog: dependency-backlog.json has 37 rows (0 active, 25 candidate, 1 blocked, 11 deferred)
json validation: jq empty passed for all 12 lane manifests, all 12 lane-status files, porting-summary.json, and dependency-backlog.json
root run by this audit: not started
```

Required pre-root process gate:

```text
pgrep -af '^php tools/run-tests\.php( |$)' matched focused lane harnesses during this audit.
14:36Z owner evidence: PID 150068 user claude elapsed 01:22 cmd php tools/run-tests.php lanes/syncthing/tests
14:36Z owner evidence: PID 156274 user claude elapsed 00:27 cmd php tools/run-tests.php lanes/syncthing/tests/ProgressEmitterSchedulerTest.php ... lanes/syncthing/tests/RequestServerTest.php
14:40Z owner evidence: PID 170221 user claude elapsed 00:19 cmd php tools/run-tests.php lanes/syncthing/tests
14:43Z pgrep -af '^php tools/run-tests\.php( |$)': no rows
```

I did not start `php tools/run-tests.php`. The exact process gate was occupied
by focused harnesses during the audit; a later clear sample arrived only after
the checkout had already failed the stability gate because `HEAD`, shortstat,
status rows, and lane manifest/status counts changed while being audited.

Current dashboard drift sample:

```text
lane          current status/manifest              dashboard
difftastic    status 3379 pass, manifest 934/1148  3245 pass, 851/1077
dolt          status 428 pass/0 fail               425 pass/0 fail
esbuild       status 446 pass, manifest 446 mapped 429 pass/mapped
gitoxide      status 7313 pass                     7152 pass
libsqlite     status/manifest 360 pass/mapped      348 pass, 349 mapped
LightningCSS  status 4154 pass, manifest 2837      4065 pass, 2765 mapped
markerPDF     status 497 pass, manifest 360/409    484 pass, 347/396
pandoc        status 374 pass, manifest 2017/2276  362 pass, 1891/2276
quadrable     status 239 pass                      232 pass
rclone        status/manifest 939 pass/mapped      906 pass/mapped
readability   status 3667 pass                     3545 pass
syncthing     status 8233 pass                     7902 pass
```

## Findings

1. **Critical - the repository is still not an acceptance checkpoint.**
   - Paths: `goal.md:29`, `goal.md:48`, `progress.md:48`,
     `lanes/difftastic/lane-status.json`, `lanes/dolt/lane-status.json`,
     `lanes/esbuild/lane-status.json`, `lanes/gitoxide/lane-status.json`,
     `lanes/libsqlite/lane-status.json`,
     `lanes/lightningcss/lane-status.json`,
     `lanes/markerpdf/lane-status.json`,
     `lanes/pandoc/lane-status.json`,
     `lanes/quadrable/lane-status.json`, `lanes/rclone/lane-status.json`,
     `lanes/readability/lane-status.json`,
     `lanes/syncthing/lane-status.json`.
   - Goal requirement at risk: small reviewable slices must be committed with
     passing tests; finished agent work must be verified, committed, cleaned
     up, and assigned onward.
   - Evidence: all sampled lane statuses still say `pending`, `uncommitted`,
     `not committed`, or equivalent dirty-worktree handoff prose. `HEAD`
     advanced to another integration-hold commit while the dirty aggregate
     moved from 329 to 332 tracked files and from 18,743 to 18,747 default
     status rows.

2. **Critical - root verification is duplicate-blocked and non-attributable.**
   - Paths: `tools/run-tests.php`, `goal.md:49`, `progress.md:48`.
   - Goal requirement at risk: repo-wide tests and static checks must be run
     periodically and recorded honestly from a stable tree.
   - Evidence: the required `pgrep -af '^php tools/run-tests\.php( |$)'`
     gate matched active focused Syncthing harnesses owned by `claude`.
     During the same window `HEAD` and shortstat moved, so even a clear
     moment would not be a frozen acceptance snapshot.

3. **High - `porting.html` and `porting-summary.json` are stale against all lanes.**
   - Paths: `porting.html:32`, `porting.html:34`, `porting.html:35`,
     `porting.html:38`, `porting-summary.json`, `goal.md:3`,
     `goal.md:45`.
   - Goal requirement at risk: the dashboard must show current denominator,
     mapped tests, PHP pass/fail, WordPress scenarios, phase, audit, current
     work, blocker, and commit.
   - Evidence: dashboard artifacts still claim source `89260857cc71`
     generated at `2026-05-24 12:29:46 UTC`, while current `HEAD` is
     `7ab009516f18` and every lane has newer manifest/status counts than the
     dashboard table.

4. **High - support-library coverage remains backlog-only, not first-class lane-granular work.**
   - Paths: `dependency-backlog.json:7`, `dependency-backlog.json:25`,
     `dependency-backlog.json:81`, `dependency-backlog.json:129`,
     `dependency-backlog.json:145`, `dependency-backlog.json:163`,
     `dependency-backlog.json:179`, `dependency-backlog.json:214`,
     `dependency-backlog.json:233`, `dependency-backlog.json:256`,
     `dependency-backlog.json:272`, `dependency-backlog.json:289`,
     `dependency-backlog.json:306`, `dependency-backlog.json:322`,
     `dependency-backlog.json:340`, `dependency-backlog.json:365`,
     `dependency-backlog.json:391`, `dependency-backlog.json:413`,
     `dependency-backlog.json:629`, `goal.md:25`, `goal.md:35`.
   - Goal requirement at risk: support libraries require a bounded native PHP
     component, activation gate, dependency-specific upstream/spec
     denominator, mapped fixtures, PHP pass/fail evidence, malformed/corrupt
     cases where relevant, and as much upstream/spec-suite evidence as can
     actually run.
   - Evidence: the backlog has 37 rows and 0 active rows; only the 12 base
     lane manifests exist under `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
     Pandoc's required DOC, DOCX/OpenXML, PDF input/output handoff, EPUB,
     ODT/OpenDocument, templates, citations, math, tables, package containers,
     XML/HTML, Unicode/charset, JSON/YAML metadata, and archive/compression
     areas are represented as gated rows, but none has its own dependency
     manifest, PHP ledger, malformed/corrupt evidence, or bounded install
     attempt/ruled-out note for packages needed to run a broader suite.

5. **High - dependency-adjacent code is spreading lane-locally before shared support gates are active.**
   - Paths: `dependency-backlog.json:7`, `dependency-backlog.json:629`,
     `lanes/markerpdf/src/BenchmarkArchiveInspector.php:9`,
     `lanes/markerpdf/src/BenchmarkArchiveInspector.php:20`,
     `lanes/markerpdf/src/BenchmarkArchiveInspector.php:191`,
     `lanes/rclone/src/VfsZipArchive.php:7`,
     `lanes/rclone/src/GzipReader.php:7`,
     `lanes/rclone/src/VfsWebDavCompression.php:7`.
   - Goal requirement at risk: optional support libraries must be bounded,
     activation-gated, tested against dependency-specific denominators, and
     shared across lanes where they are common needs.
   - Evidence: markerPDF has lane-local ZIP archive inspection via
     `ZipArchive`, and rclone carries lane-local ZIP/gzip/WebDAV compression
     helpers. These can be useful lane slices, but they cannot count as
     `shared-zip-package-core` or `archive-compression-streams` progress until
     those support rows are activated with their own manifests, malformed
     archive cases, PHP pass/fail ledgers, and cross-lane reuse contracts.

6. **High - markerPDF still mixes native PDF progress with external runtime and shell-boundary plans.**
   - Paths: `goal.md:1`, `goal.md:30`,
     `lanes/markerpdf/lane-status.json:5`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:877`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:967`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1007`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1029`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1042`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1121`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1131`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1137`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1142`.
   - Goal requirement at risk: wrappers around JS/Rust/Go/C binaries,
     shell-outs, bridge calls, whole applications, and external converter
     wrappers must not count as native deliverables.
   - Evidence: markerPDF has real native PDF extraction movement, now
     360/409 mapped with 497 local behavior tests. The same denominator/status
     surface still includes Streamlit/FastAPI/Uvicorn plans,
     OCRMyPDF/Tesseract/Ghostscript setup planning, Pandoc/XeLaTeX helper
     planning, Poetry/package planning, model stack boundaries, and
     `chunk_convert` shell lifecycle planning. Those must stay explicit
     blockers, supplied-runner contracts, or non-goals, not accepted native
     progress.

7. **Medium - Pandoc rich-function support is routed but not proven.**
   - Paths: `goal.md:12`, `lanes/pandoc/lane-status.json:5`,
     `lanes/pandoc/lane-status.json:10`,
     `lanes/pandoc/lane-status.json:12`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:322`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:323`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:1167`,
     `dependency-backlog.json:81`, `dependency-backlog.json:129`,
     `dependency-backlog.json:145`, `dependency-backlog.json:163`,
     `dependency-backlog.json:179`, `dependency-backlog.json:214`,
     `dependency-backlog.json:233`, `dependency-backlog.json:256`,
     `dependency-backlog.json:322`, `dependency-backlog.json:340`,
     `dependency-backlog.json:365`, `dependency-backlog.json:629`.
   - Goal requirement at risk: Pandoc must account for essential rich document
     and PDF conversion libraries with real upstream/spec-suite evidence
     rather than fixture-only credit unless broader suites were attempted and
     honestly bounded.
   - Evidence: Pandoc reports 99% progress, 2,017/2,276 mapped, and 374 PHP
     behavior tests, but the full Haskell runner remains unexecuted. The
     latest support rows cover the requested rich-function areas, but they are
     inactive backlog entries without support manifests or dependency-specific
     ledgers.

8. **Medium - near-complete percentages overstate accepted parity.**
   - Paths: `porting.html:32`, `porting.html:56`,
     `porting.html:67`, `lanes/*/lane-status.json`, `goal.md:35`,
     `goal.md:37`.
   - Goal requirement at risk: passing tests are not enough; upstream tests
     are the source of truth where possible, and hard gaps must be blockers or
     future slices.
   - Evidence: the public dashboard still reports `98.3%` average progress
     and most lanes report 98 or 99 percent, while every lane handoff remains
     pending/uncommitted, root verification is not attributable to a frozen
     snapshot, several upstream runners remain static/bounded/unexecuted, and
     support-library work has no active bounded port.

9. **Medium - manifest/status fields are not normalized enough for reliable coordination.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`, `goal.md:3`, `goal.md:44`.
   - Goal requirement at risk: progress must track upstream denominator,
     mapped tests, PHP pass/fail counts, phase, audit status, blocker, and
     latest commit in a durable coordination system.
   - Evidence: several `benchmarkDenominator.status` values are long
     concatenated slice slugs rather than reviewable statuses, while
     `latestCommit`/`commit` fields in lane-status files often contain
     prose such as `pending`, `not committed`, `uncommitted`, or a stale HEAD
     reference instead of an accepted implementation commit.

## Next Intervention

Freeze lane writers, focused/root runners, dashboard/status publishers,
support-library scouts, capacity rows, and integration-hold writers. Require
two stable polls of `HEAD`, default/tracked status counts, shortstat,
`pgrep -af '^php tools/run-tests\.php( |$)'`, dashboard/dependency counts,
lane status timestamps, and relevant log mtimes. Accept exactly one
owner-free lane batch at a time, normalizing manifest/status schema and commit
fields before claiming progress. Promote support libraries only behind an
accepted base-lane gate or true component blocker, each with its own manifest
and malformed-case evidence. Regenerate `progress.md`, `porting.html`, and
`porting-summary.json` from the accepted commit, then run one serialized
no-argument root harness only if the exact process gate remains empty on that
frozen snapshot.
