# Independent Audit - 2026-05-24T12:40Z

Scope reviewed: `goal.md`, `progress.md`, current `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
current `lanes/*/lane-status.json`, `dependency-backlog.json`, and recent Git
history through `46a07e64 Record integration hold status`. I did not edit lane
implementation files, launch agents or tmux sessions, push, read secrets,
inspect process environments, credential stores, provider configs, or auth
files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T12:38Z and 2026-05-24T12:40Z
HEAD: 46a07e644a6d
recent history: 46a07e64 Record integration hold status; a7c70e3d Refresh independent audit status; 48a0ac98 Record integration hold status; 91385d1b Track support library reading order contract; 89260857 Record integration hold status
tracked dirty rows: 327 -> 327
default status rows including untracked: 17784 -> 17789
git diff --shortstat: 327 files changed, 235708 insertions(+), 30638 deletions(-) -> 327 files changed, 235913 insertions(+), 30683 deletions(-)
dashboard snapshot: porting.html and porting-summary.json generated 2026-05-24 12:29:46 UTC from source 89260857cc71; current HEAD is 46a07e644a6d
dependency backlog: dependency-backlog.json updated 2026-05-24 12:29:10 UTC with 37 rows (1 blocked, 25 candidate, 11 deferred, 0 active)
root run by this audit: not started
```

Required pre-root process gate:

```text
pgrep -af '^php tools/run-tests\.php( |$)' at 12:38Z: no rows
pgrep -af '^php tools/run-tests\.php( |$)' at 12:40Z: no rows
```

I did not start `php tools/run-tests.php`. The exact process gate was clear at
the audit samples, but the checkout was not stable enough: the shortstat and
untracked-inclusive status count moved between samples, every lane still
reports pending or uncommitted handoff state, and no coherent lane batch had
been accepted from the current dirty tree.

`jq empty` passed for all 12 lane manifests, all 12 lane-status files,
`porting-summary.json`, and `dependency-backlog.json`.

Current dashboard drift sample:

```text
lane          current manifest/status        dashboard
difftastic    status 3269 php pass           3245 php pass
esbuild       manifest/status 430 php pass   429 php pass
gitoxide      status 7177 php pass           7152 php pass
libsqlite     status 349 php pass            348 php pass
LightningCSS  manifest 2767 mapped, 4073 php 2765 mapped, 4065 php
markerPDF     manifest 397/348, status 485   396/347, 484 php
pandoc        manifest 1908 mapped, status 363 php 1891 mapped, 362 php
rclone        manifest/status 908 mapped/php 906 mapped/php
```

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md:48`, `lanes/*/lane-status.json:13`,
     `goal.md:29`, `goal.md:48`.
   - Goal requirement at risk: small reviewable slices must be committed with
     passing tests, and finished agent work must be verified, integrated,
     cleaned up, and assigned onward.
   - Evidence: `HEAD` is now `46a07e644a6d` after another audit/status pair of
     commits; the tree still has `327` tracked dirty files; total status rows
     moved `17784 -> 17789`; shortstat moved to `327 files changed, 235913
     insertions(+), 30683 deletions(-)`. All 12 lane-status files still record
     `latestCommit` as pending, uncommitted, or equivalent shared-dirty
     handoff prose.

2. **Critical - there is still no audit-acceptable root PHP result for the
   current snapshot.**
   - Paths: `tools/run-tests.php`, `lanes/*/lane-status.json:12`,
     `goal.md:49`.
   - Goal requirement at risk: repo-wide tests and static checks must be run
     periodically and failures recorded honestly.
   - Evidence: the exact root gate was clear at both samples, but the tree was
     moving and unaccepted. Starting a no-argument root run from this state
     would not produce useful acceptance evidence. Focused lane-green results
     in lane-status files remain lane-local evidence, not root evidence for
     `46a07e644a6d` plus the current dirty tree.

3. **High - the dashboard and compact summary are stale against current source
   metadata.**
   - Paths: `porting.html:34`, `porting.html:35`, `porting.html:38`,
     `porting.html:56` through `porting.html:67`, `porting-summary.json`,
     `lanes/difftastic/lane-status.json:6`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:16`,
     `lanes/gitoxide/lane-status.json:6`,
     `lanes/libsqlite/lane-status.json:6`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/lightningcss/lane-status.json:6`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/markerpdf/lane-status.json:6`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:273`,
     `lanes/pandoc/lane-status.json:6`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:15`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     coordination files and a dashboard showing denominator, mapped tests, PHP
     pass/fail, phase, audit, current work, blocker, and commit.
   - Evidence: `porting.html` says it is generated from `89260857cc71`, while
     `HEAD` is `46a07e644a6d`. Current manifests/statuses have already moved
     beyond the dashboard for Difftastic, esbuild, Gitoxide, libsqlite,
     LightningCSS, markerPDF, Pandoc, and rclone.

4. **High - support-library coverage is still planning-only, including the
   latest Pandoc rich-function directive.**
   - Paths: `dependency-backlog.json:1`, `dependency-backlog.json:4`,
     `dependency-backlog.json:7`, `dependency-backlog.json:18`,
     `dependency-backlog.json:81`, `dependency-backlog.json:90`,
     `dependency-backlog.json:129`, `dependency-backlog.json:138`,
     `dependency-backlog.json:145`, `dependency-backlog.json:156`,
     `dependency-backlog.json:163`, `dependency-backlog.json:172`,
     `dependency-backlog.json:179`, `dependency-backlog.json:188`,
     `dependency-backlog.json:195`, `dependency-backlog.json:207`,
     `dependency-backlog.json:214`, `dependency-backlog.json:226`,
     `dependency-backlog.json:233`, `dependency-backlog.json:249`,
     `porting.html:71` through `porting.html:78`.
   - Goal requirement at risk: the 2026-05-24 11:59 UTC support-library
     directive requires bounded native PHP components, activation gates,
     dependency-specific upstream/spec denominators, mapped fixtures, PHP
     pass/fail evidence, malformed/corrupt cases where relevant, and as much
     upstream/spec-suite evidence as can honestly run.
   - Evidence: the backlog accounts on paper for Pandoc DOC, DOCX/OpenXML, PDF
     handoff/text extraction, EPUB, ODT/OpenDocument, templates, citations,
     math, tables, package containers, XML/HTML, Unicode/charset, JSON/YAML,
     and archive/compression, plus shared non-Pandoc dependencies. But there
     are `0` active support rows and no support-library-specific manifests or
     pass/fail evidence. These rows are candidate/deferred/blocker routing, not
     accepted support-port progress.

5. **High - markerPDF still mixes native PDF progress with external/runtime
   orchestration plans.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:841`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:843`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1077`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1085`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1087`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1093`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1098`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1107`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1112`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1127`,
     `lanes/markerpdf/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:1` and `goal.md:30` forbid wrappers
     around JS/Rust/Go/C binaries or shell-outs from counting as the main
     deliverable; bridge/oracle tooling may only be temporary.
   - Evidence: markerPDF has real native PDF text/outline/filter/resource
     progress. It also counts Pandoc/XeLaTeX command planning, multiprocessing
     and shell launcher planning, Streamlit/FastAPI runtime planning,
     OCRMyPDF/Tesseract/Ghostscript install/readiness plans, Poetry/model
     dependency graphs, Texify/Torch/Nougat/model handoff plans, and archive
     inventory work in the same mapped semantics surface. Those are preflight,
     supplied-boundary, or blocker records, not native port progress.

6. **Medium - `progress.md` active-lane handoffs remain stale outside the
   audit snapshot note.**
   - Paths: `progress.md:147` through `progress.md:160`,
     `lanes/*/lane-status.json:11`.
   - Goal requirement at risk: `goal.md:44` requires current owner/session,
     next task per lane, blockers, and percentage estimates in `progress.md`.
   - Evidence: the Active Lanes table still names older handoffs such as
     markerPDF benchmark file-inventory planning, libsqlite WAL FULL-sync,
     Pandoc NativeWriter figure/citation, rclone VFS Statfs/usage, and
     esbuild automatic JSX key/spread fallback, while current lane-status files
     describe later markerPDF escaped PDF resource names, libsqlite JSON
     operator planner-hint RHS, Pandoc LaTeX quote/hr, rclone accounting
     server-side transfer, esbuild legal-comment grouping, and other newer
     handoffs.

7. **Medium - near-complete percentages continue to overstate accepted parity.**
   - Paths: `porting.html:32`, `porting.html:56` through `porting.html:67`,
     `lanes/*/lane-status.json:12`, `lanes/*/lane-status.json:13`,
     `goal.md:35` through `goal.md:40`.
   - Goal requirement at risk: passing tests are not enough; upstream tests are
     the source of truth where possible, and hard gaps must be recorded as
     blockers or future slices.
   - Evidence: the dashboard reports `98.3%` average progress and most lanes at
     `98%` or `99%`, while aggregate root verification is pending, full
     upstream runners are often bounded or unexecuted, support-library ports
     are backlog-only, and every current lane handoff is still pending or
     uncommitted in a shared dirty tree.

## Next Intervention

Freeze lane writers, dashboard/status publishers, support-library scouts,
focused runners, root runners, capacity executors, Dolt, and the Dolt runner.
Require two stable polls of `HEAD`, tracked rows, untracked-inclusive rows,
shortstat, exact process gates, dependency/dashboard counts, and relevant log
mtimes. Then accept exactly one coherent lane batch with manifest/status schema
normalization, run focused lane verification plus `git diff --check`, activate
only support-library rows whose base-lane gate is accepted or truly blocked,
add dependency-specific support manifests before counting support progress,
regenerate `porting.html` and `porting-summary.json` from the accepted commit,
and run one serialized no-argument `php tools/run-tests.php` only if the exact
process gate remains empty on that frozen snapshot.
