# Independent Audit - 2026-05-24T13:03Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json`, all 12
current `lanes/*/lane-status.json`, `dependency-backlog.json`, and recent Git
history through `fa6f1d51 Record integration hold status`. I did not edit lane
implementation files, launch agents or tmux sessions, push, read secrets,
inspect process environments, credential stores, provider configs, or auth
files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24 13:01-13:03
HEAD: fa6f1d51e89a
recent history: fa6f1d51 Record integration hold status; 990262b6 Refresh independent audit status; 2eb0a4a4 Record integration hold status; dd2e3f84 Record integration hold status; 40cb49ae Refresh independent audit status
branch sample: main...origin/main [ahead 879, behind 68]
tracked dirty rows: 327
default status rows including untracked: 18056
git diff --shortstat: 327 files changed, 238218 insertions(+), 30792 deletions(-)
dashboard snapshot: porting.html and porting-summary.json generated 2026-05-24 12:29:46 UTC from source 89260857cc71; sampled HEAD is fa6f1d51e89a
dependency backlog: dependency-backlog.json updated 2026-05-24 12:29:10 UTC with 37 rows (1 blocked, 25 candidate, 11 deferred, 0 active)
root run by this audit: not started
```

Required pre-root process gate:

```text
13:01Z pgrep sample: 3092995 php tools/run-tests.php lanes/syncthing/tests/ProgressEmitterSchedulerTest.php ... lanes/syncthing/tests/RequestServerTest.php
13:01Z owner evidence: PID exited before ps could sample owner/elapsed fields
13:03Z pgrep sample: no rows
```

I did not start `php tools/run-tests.php`. The exact gate was occupied by a
focused Syncthing PHP harness during sampling, then cleared, but the checkout
still failed the stability gate and is not an audit-acceptable root-run target.

`jq empty` passed for all 12 lane manifests, all 12 lane-status files,
`porting-summary.json`, and `dependency-backlog.json`.

Current manifest/status drift sample:

```text
lane          current manifest/status             dashboard or summary
difftastic    manifest 1093/874, status 3302 pass  1077/851, 3245 pass
dolt          status currentWork now LEFT/RIGHT    dashboard still says HEX WHERE
esbuild       manifest/status 434 pass             429 pass
gitoxide      status 7214 pass                     7152 pass
libsqlite     manifest/status 352 mapped/pass      349 mapped, 348 pass
LightningCSS  manifest 2773 mapped, status 4081    2765 mapped, 4065 pass
markerPDF     manifest 400/351, status 487 pass    396/347, 484 pass
pandoc        manifest 1908 mapped, status 365     1891 mapped, 362 pass
rclone        manifest 915 mapped, status 911      906 mapped/pass
readability   status 3589 pass                     3545 pass
syncthing     status 7997 pass                     7902 pass
```

## Findings

1. **Critical - the checkout is not an acceptance checkpoint.**
   - Paths: `progress.md:147` through `progress.md:162`,
     `lanes/*/lane-status.json`, `goal.md:29`, `goal.md:48`.
   - Goal requirement at risk: small reviewable slices must be committed with
     passing tests, and finished agent work must be verified, integrated,
     cleaned up, and assigned onward.
   - Evidence: the worktree has `327` tracked dirty rows, `18056` status rows
     including untracked paths, and `327 files changed, 238218 insertions(+),
     30792 deletions(-)`. Every lane-status `latestCommit` is still pending,
     uncommitted, not committed, or equivalent lane-local/shared-dirty handoff
     prose. Recent history remains audit/status hold commits, not accepted lane
     implementation commits.

2. **Critical - there is no audit-acceptable root PHP result for the current
   snapshot.**
   - Paths: `tools/run-tests.php`, `lanes/*/lane-status.json`, `goal.md:49`.
   - Goal requirement at risk: repo-wide tests and static checks must be run
     periodically and failures recorded honestly.
   - Evidence: the required `pgrep -af '^php tools/run-tests\.php( |$)'` gate
     matched focused Syncthing PID `3092995` at 13:01Z and cleared by 13:03Z.
     The process exited before owner fields could be sampled. I still did not
     run a no-argument root harness because the checkout is moving/dirty and no
     coherent lane batch has been accepted. Lane-local green results are not a
     root result for `fa6f1d51` plus the current dirty tree.

3. **High - `porting.html` and `porting-summary.json` are stale enough to
   mislead coordination.**
   - Paths: `porting.html:32` through `porting.html:38`,
     `porting.html:56` through `porting.html:67`,
     `porting-summary.json:2` through `porting-summary.json:9`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`,
     `goal.md:3`, `goal.md:45`.
   - Goal requirement at risk: the dashboard must show current denominator,
     mapped tests, PHP pass/fail, phase, audit, current work, blocker, and
     commit.
   - Evidence: the dashboard claims source snapshot `89260857cc71`, while
     current sampled `HEAD` is `fa6f1d51e89a`. Live metadata has moved beyond
     the page for Difftastic, Dolt current work, esbuild, Gitoxide, libsqlite,
     LightningCSS, markerPDF, Pandoc, rclone, Readability, and Syncthing.

4. **High - manifest/status counts remain non-normalized, including internal
   contradictions inside current lane evidence.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:376`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:844` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:845`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:282` through
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:286`,
     `goal.md:24`, `goal.md:25`, `goal.md:35`.
   - Goal requirement at risk: each lane needs a defensible upstream
     denominator, mapped upstream tests, PHP passing/failing counts, and honest
     blocker recording.
   - Evidence: markerPDF's top-level manifest reports `400` total and `351`
     mapped, while inventory and warning text still say `399`, `398`, and `349`.
     Pandoc reports `mapped: 1908`, while warning/latest-note text says native
     PHP maps `1,922` focused checks. rclone's manifest reports `915` mapped
     while lane status reports `911` PHP pass and the dashboard reports `906`.
     These may mix behavior checks, denominator entries, and assertion counts,
     but the schema does not distinguish them consistently enough for acceptance
     or dashboard regeneration.

5. **High - support-library coverage remains planning-only under the
   2026-05-24 11:59 UTC rich-function directive.**
   - Paths: `dependency-backlog.json:3` through `dependency-backlog.json:4`,
     `dependency-backlog.json:7` through `dependency-backlog.json:22`,
     `dependency-backlog.json:81` through `dependency-backlog.json:95`,
     `porting.html:72` through `porting.html:127`, `progress.md:17` through
     `progress.md:33`.
   - Goal requirement at risk: support libraries require the same granularity as
     lanes: bounded native PHP component, activation gate, dependency-specific
     upstream/spec denominator, mapped fixtures, PHP pass/fail evidence,
     malformed/corrupt cases where relevant, and as much upstream/spec-suite
     evidence as can honestly run.
   - Evidence: Pandoc's DOC, DOCX/OpenXML, PDF handoff/text extraction, EPUB,
     ODT/OpenDocument, templates, citations, math, tables, package containers,
     XML/HTML, Unicode/charset, JSON/YAML, and archive/compression needs are
     routed as backlog rows, but there are still `0` active support rows and no
     support-library-specific manifests or pass/fail evidence. This is routing,
     not accepted support-port progress.

6. **High - markerPDF still over-credits external runtime and shell-boundary
   planning beside native PDF work.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:547`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:582` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:584`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:782` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:790`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:808`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:844`,
     `goal.md:1`, `goal.md:30`.
   - Goal requirement at risk: wrappers around JS/Rust/Go/C binaries,
     shell-outs, bridge calls, whole applications, and external converter
     wrappers must not count as the main native deliverable.
   - Evidence: markerPDF has useful native PDF text/filter/outline/resource
     progress. The same manifest also counts command plans for Pandoc/XeLaTeX,
     chunk-convert shell lifecycle, Streamlit/FastAPI runtime boundaries,
     Poetry/model-runtime metadata, Tesseract/OCRMyPDF/Ghostscript readiness,
     Texify/Torch/Nougat handoffs, and subprocess command metadata in the mapped
     behavior surface. These should remain explicit preflight/blocker metadata
     unless split into bounded native support ports with their own evidence.

7. **Medium - `progress.md` active-lane handoffs are stale relative to current
   lane-status files.**
   - Paths: `progress.md:149` through `progress.md:162`,
     `lanes/*/lane-status.json`, `goal.md:44`.
   - Goal requirement at risk: `progress.md` must include current owner/session,
     next task per lane, blockers, and percentage estimates.
   - Evidence: the Active Lanes table still names older handoffs such as
     gitoxide SSH config-options, markerPDF benchmark file-inventory planning,
     Pandoc NativeWriter figure/citation, rclone VFS Statfs/usage, Syncthing
     system log route, and esbuild JSX key/spread fallback. Current lane-status
     files instead describe gitoxide conflict access, markerPDF page-resource
     inheritance, Pandoc HTML writer code blocks, rclone accounting transferMap
     sections, Syncthing GUI static multipart ranges, and esbuild DataURL
     handling.

8. **Medium - near-complete percentages overstate accepted upstream parity.**
   - Paths: `porting.html:32`, `porting.html:56` through `porting.html:67`,
     `porting-summary.json:8`, `goal.md:35` through `goal.md:40`.
   - Goal requirement at risk: passing tests are not enough; upstream tests are
     the source of truth where possible, and hard gaps must be recorded as
     blockers or future slices.
   - Evidence: the dashboard reports `98.3%` average progress and most lanes at
     `98%` or `99%`, while aggregate root verification is pending, all current
     lane batches are uncommitted, support-library work has no active bounded
     ports, and full upstream runners remain static, bounded, unexecuted, or
     intentionally excluded for multiple lanes including Difftastic, Gitoxide,
     markerPDF, Pandoc, rclone provider/mount suites, Syncthing, and esbuild
     release-extra targets.

## Next Intervention

Freeze lane writers, dashboard/status publishers, support-library scouts,
focused runners, root runners, capacity executors, Dolt, and the Dolt runner.
Require two stable polls of `HEAD`, tracked rows, untracked-inclusive rows,
shortstat, exact process gates, dependency/dashboard counts, status timestamps,
and relevant log mtimes. Then accept exactly one coherent lane batch with
manifest/status schema and count normalization, run focused lane verification
plus `git diff --check`, activate only support-library rows whose base-lane gate
is accepted or truly blocked, add dependency-specific support manifests before
counting support progress, regenerate `porting.html` and `porting-summary.json`
from the accepted commit, and run one serialized no-argument
`php tools/run-tests.php` only if the exact process gate remains empty on that
frozen snapshot.
