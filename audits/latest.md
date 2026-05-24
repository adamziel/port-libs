# Independent Audit - 2026-05-24T13:40Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json`, all 12
current `lanes/*/lane-status.json`, `dependency-backlog.json`, and recent Git
history through `90c38ff9 Record integration hold status`. I did not edit lane
implementation files, launch agents or tmux sessions, push, read secrets,
inspect process environments, credential stores, provider configs, or auth
files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24 13:36-13:40
HEAD moved during audit: 5ffb29126fc4 -> 90c38ff9649a
recent history: 90c38ff9 Record integration hold status; 5ffb2912 Refresh independent audit status; 35121fcf Record integration hold status; f8b79dd0 Record integration hold status; 7e93a807 Refresh independent audit status
branch sample: main...origin/main [ahead 892, behind 68]
tracked dirty rows: 330 -> 329
default status rows including untracked: 18412 -> 18412
git diff --shortstat: 330 files changed, 242720 insertions(+), 30995 deletions(-) -> 329 files changed, 242813 insertions(+), 31122 deletions(-)
dashboard snapshot: porting.html and porting-summary.json still publish source 89260857cc71; sampled HEAD is 90c38ff9649a
dependency backlog: dependency-backlog.json has 37 rows (1 blocked, 25 candidate, 11 deferred, 0 active)
root run by this audit: not started
json validation: jq empty passed for all 12 lane manifests, all 12 lane-status files, porting-summary.json, and dependency-backlog.json
```

Required pre-root process gate:

```text
13:36Z pgrep -af '^php tools/run-tests\.php( |$)':
3505180 php tools/run-tests.php

owner evidence:
PID 3505180, USER claude, PPID 3505119, elapsed 01:33, state R+, command php tools/run-tests.php

13:40Z validation pgrep -af '^php tools/run-tests\.php( |$)':
3519395 php tools/run-tests.php lanes/syncthing/tests

owner evidence:
PID 3519395, USER claude, PPID 3476522, elapsed 01:04, state Rs, command php tools/run-tests.php lanes/syncthing/tests
```

I did not start `php tools/run-tests.php`. A no-argument root harness was active
during the required gate sample, and after it exited the checkout still failed
the stability gate while a focused Syncthing PHP harness was active.

Current manifest/status drift sample:

```text
lane          current manifest/status                 dashboard
difftastic    manifest 1108/894, status 3323 pass     1077/851, 3245 pass
esbuild       manifest 436, status 440 pass           429 mapped/pass
gitoxide      status 7254 pass                        7152 pass
libsqlite     manifest/status 355                     349 mapped, 348 pass
LightningCSS  manifest 2800, status 4112 pass         2765 mapped, 4065 pass
markerPDF     manifest 403/354, status 491 pass       396/347, 484 pass
pandoc        manifest 1908, status 1961 checks       1891 mapped, 362 pass
quadrable     status 236 pass                         232 pass
rclone        manifest/status 923                     906 mapped/pass
readability   status 3607 pass                        3545 pass
syncthing     status 8058 pass                        7902 pass
```

## Findings

1. **Critical - the repository is still not an acceptance checkpoint.**
   - Paths: `progress.md:15`, `progress.md:48`,
     `lanes/*/lane-status.json:13`, `goal.md:29`, `goal.md:48`.
   - Goal requirement at risk: small reviewable slices must be committed with
     passing tests, and finished agent work must be verified, committed, cleaned
     up, and assigned onward.
   - Evidence: `HEAD` moved from `5ffb29126fc4` to `90c38ff9649a` while this
     audit was sampling. The tracked dirty row count and shortstat also moved.
     All 12 lane statuses still report `pending`, `uncommitted`, `not
     committed`, or shared dirty-worktree commit ownership rather than accepted
     implementation commits.

2. **Critical - a no-argument root run would be invalid from this audit.**
   - Paths: `tools/run-tests.php`, `progress.md:48`, `goal.md:49`.
   - Goal requirement at risk: repo-wide tests and static checks must be
     recorded honestly from a stable snapshot and must not duplicate active
     harness work.
   - Evidence: the required exact process gate matched active no-argument root
     PID `3505180` owned by `claude` (`php tools/run-tests.php`). Later
     validation matched focused Syncthing PID `3519395`. The tree also moved
     during the audit, so I did not start another root run.

3. **High - `porting.html` and `porting-summary.json` are materially stale.**
   - Paths: `porting.html:32`, `porting.html:34`, `porting.html:35`,
     `porting.html:56`, `porting.html:67`, `porting-summary.json:3`,
     `porting-summary.json:4`, `porting-summary.json:8`, `goal.md:3`,
     `goal.md:45`.
   - Goal requirement at risk: the dashboard must show current denominator,
     mapped tests, PHP pass/fail, phase, audit, current work, blocker, and
     commit.
   - Evidence: both generated artifacts still publish source commit
     `89260857cc71`, while sampled `HEAD` is `90c38ff9649a`. Current lane
     manifests/statuses now exceed the dashboard for Difftastic, esbuild,
     Gitoxide, libsqlite, LightningCSS, markerPDF, Pandoc, Quadrable, rclone,
     Readability, and Syncthing.

4. **High - manifest/status counts remain non-normalized.**
   - Paths: `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:16`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:387`,
     `lanes/esbuild/lane-status.json:5`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:1315`,
     `lanes/rclone/lane-status.json:5`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:296`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:298`,
     `lanes/pandoc/lane-status.json:5`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:861`,
     `lanes/markerpdf/lane-status.json:5`, `goal.md:24`, `goal.md:35`.
   - Goal requirement at risk: every lane needs a defensible denominator,
     mapped upstream tests, PHP passing/failing counts, and precise blocker
     recording.
   - Evidence: esbuild has manifest `mapped: 436` / `phpBehaviorTests: 436`,
     a warning that still says `433`, and lane status `440` pass. rclone has
     manifest/status `923`, while the manifest warning still says `869`.
     Pandoc has manifest `mapped: 1908`, warning text saying `1,940`, and lane
     status saying `1,961` focused checks with `368` PHP passes. markerPDF has
     manifest `mapped: 354`, `phpBehaviorTests: 491`, and status `491` pass.

5. **High - support-library coverage remains planning-only under the latest
   rich-function directive.**
   - Paths: `dependency-backlog.json:3`, `dependency-backlog.json:4`,
     `dependency-backlog.json:7`, `dependency-backlog.json:18`,
     `dependency-backlog.json:81`, `dependency-backlog.json:129`,
     `dependency-backlog.json:146`, `dependency-backlog.json:164`,
     `dependency-backlog.json:179`, `dependency-backlog.json:214`,
     `dependency-backlog.json:233`, `dependency-backlog.json:256`,
     `dependency-backlog.json:272`, `dependency-backlog.json:322`,
     `dependency-backlog.json:341`, `dependency-backlog.json:365`,
     `dependency-backlog.json:476`, `porting.html:75`, `porting.html:93`,
     `progress.md:32`.
   - Goal requirement at risk: support libraries require the same granularity as
     lanes: bounded native PHP component, activation gate,
     dependency-specific upstream/spec denominator, mapped fixtures, PHP
     pass/fail evidence, malformed/corrupt cases where relevant, and as much of
     the upstream/spec suite as the environment can run.
   - Evidence: the backlog has 37 rows and `0` active bounded support ports.
     Pandoc's required DOC, DOCX/OpenXML, PDF handoff/text extraction, EPUB,
     ODT/OpenDocument, templates, citations, math, tables, package containers,
     XML/HTML, Unicode/charset, JSON/YAML, and archive/compression needs are
     routed, but there are no dependency-specific support manifests or
     pass/fail results. rclone WebDAV, markerPDF PDF/text/layout/table, and
     Syncthing QR work also remain lane-local or blocked rather than accepted
     support-library progress.

6. **High - markerPDF still over-credits external runtime and shell-boundary
   plans beside real native PDF work.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:553`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:789`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:797`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:799`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:815`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:854`,
     `lanes/markerpdf/lane-status.json:12`, `goal.md:1`, `goal.md:30`.
   - Goal requirement at risk: wrappers around JS/Rust/Go/C binaries,
     shell-outs, bridge calls, whole applications, and external converter
     wrappers must not count as the native deliverable.
   - Evidence: markerPDF has useful native PDF filter/resource/metadata
     progress. The same mapped surface still includes Nougat subprocess
     planning, benchmark archive/package planning, Pandoc/XeLaTeX helper plans,
     Streamlit/FastAPI/PIL/pypdfium server/app boundaries,
     OCRMyPDF/Tesseract/Ghostscript readiness, Texify/Torch/model gates, Poetry
     metadata, and chunk-convert shell lifecycle plans. Those should stay
     blocker/preflight metadata or be split into activation-gated support rows
     before receiving progress credit.

7. **Medium - near-complete percentages overstate accepted upstream parity.**
   - Paths: `porting.html:32`, `porting.html:56`, `porting.html:67`,
     `porting-summary.json:8`, `lanes/*/lane-status.json:5`, `goal.md:35`,
     `goal.md:40`.
   - Goal requirement at risk: passing tests are not enough; upstream tests are
     the source of truth where possible, and hard gaps must be recorded as
     blockers or future slices.
   - Evidence: the public dashboard reports `98.3%` average progress and most
     lanes at `98%` or `99%`, while lane batches remain uncommitted, the moving
     tree lacks one serialized root result, support-library work has no active
     bounded ports, and full upstream runners remain static, bounded,
     unexecuted, or intentionally excluded for multiple lanes.

8. **Medium - recent history remains status/hold dominated rather than
   accepted implementation integration.**
   - Paths: `audits/latest.md`, `audits/integration-status.md:1`,
     `progress.md:48`, `lanes/*/lane-status.json:13`, `goal.md:20`,
     `goal.md:48`.
   - Goal requirement at risk: the supervisor must integrate useful work,
     enforce standards, keep the roadmap honest, and assign the next
     highest-value slice after verification.
   - Evidence: the latest sampled commits are `90c38ff9 Record integration
     hold status`, `5ffb2912 Refresh independent audit status`, `35121fcf
     Record integration hold status`, `f8b79dd0 Record integration hold
     status`, and `7e93a807 Refresh independent audit status`. That preserves
     the hold but does not convert lane-local evidence into accepted
     implementation checkpoints.

## Next Intervention

Freeze lane writers, dashboard/status publishers, support-library scouts,
focused runners, root runners, capacity executors, Dolt, and the Dolt runner.
Require two stable polls of `HEAD`, tracked rows, untracked-inclusive rows,
shortstat, exact process gates, dashboard/dependency counts, status timestamps,
and relevant log mtimes. Then accept exactly one coherent lane batch with
manifest/status schema and count normalization, run focused lane verification
plus `git diff --check`, activate only support-library rows whose base-lane
gate is accepted or truly blocked, add dependency-specific support manifests
before counting support progress, regenerate `porting.html` and
`porting-summary.json` from the accepted commit, and run one serialized
no-argument `php tools/run-tests.php` only if the exact process gate is empty on
that frozen snapshot.
