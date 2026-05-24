# Independent Audit - 2026-05-24T12:57Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
current `lanes/*/lane-status.json`, `dependency-backlog.json`, and recent Git
history through `2eb0a4a4 Record integration hold status`. I did not edit lane
implementation files, launch agents or tmux sessions, push, read secrets,
inspect process environments, credential stores, provider configs, or auth
files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T12:56:47Z and 2026-05-24T12:57:39Z
HEAD moved during this audit: 40cb49aebf75 -> 2eb0a4a48f83
recent history: 2eb0a4a4 Record integration hold status; dd2e3f84 Record integration hold status; 40cb49ae Refresh independent audit status; c9d0e607 Record integration hold status; 34f56465 Refresh independent audit status
branch sample: main...origin/main [ahead 877, behind 68]
tracked dirty rows: 327
default status rows including untracked: 17982 -> 17990
git diff --shortstat: 327 files changed, 237562 insertions(+), 30713 deletions(-) -> 327 files changed, 237604 insertions(+), 30713 deletions(-)
dashboard snapshot: porting.html and porting-summary.json generated 2026-05-24 12:29:46 UTC from source 89260857cc71; sampled HEAD is 2eb0a4a48f83
dependency backlog: dependency-backlog.json updated 2026-05-24 12:29:10 UTC with 37 rows (1 blocked, 25 candidate, 11 deferred, 0 active)
root run by this audit: not started
```

Required pre-root process gate:

```text
initial pgrep sample: 2983499 php tools/run-tests.php lanes/readability/tests/ArticleExtractorTest.php
12:56Z pgrep sample: 2994892 php tools/run-tests.php
12:56Z owner evidence: 2994892 claude 2994808 R+ 00:02:11 php tools/run-tests.php
12:57Z pgrep sample: 3037673 php tools/run-tests.php lanes/syncthing/tests
12:57Z owner evidence: 3037673 claude 2980802 Rs 00:00:06 php tools/run-tests.php lanes/syncthing/tests
```

I did not start `php tools/run-tests.php`. The exact gate was occupied by
focused and no-argument PHP harnesses during the audit, and the checkout was
also moving.

`jq empty` passed for all 12 lane manifests, all 12 lane-status files,
`porting-summary.json`, and `dependency-backlog.json`.

Current manifest/status drift sample:

```text
lane          current manifest/status             dashboard or summary
difftastic    manifest 1082/863, status 3288 pass  1077/851, 3245 pass
esbuild       manifest/status 434 pass             429 pass
gitoxide      status 7214 pass                     7152 pass
libsqlite     manifest/status 351 mapped/pass      349 mapped, 348 pass
LightningCSS  manifest 2769 mapped, status 4075    2765 mapped, 4065 pass
markerPDF     manifest 399/350, status 487 pass    396/347, 484 pass
pandoc        manifest 1908 mapped, status 364     1891 mapped, 362 pass
rclone        manifest/status 911 mapped/pass      906 mapped/pass
readability   status 3575 pass                     3545 pass
syncthing     status 7972 pass                     7902 pass
```

## Findings

1. **Critical - the checkout is not an acceptance checkpoint.**
   - Paths: `progress.md:144` through `progress.md:163`,
     `lanes/*/lane-status.json:13`, `goal.md:29`, `goal.md:48`.
   - Goal requirement at risk: small reviewable slices must be committed with
     passing tests, and finished agent work must be verified, integrated,
     cleaned up, and assigned onward.
   - Evidence: `HEAD` moved from `40cb49aebf75` to `2eb0a4a48f83` while this
     audit was sampling. The dirty tree still has `327` tracked dirty rows,
     nearly `18k` total status rows including untracked paths, and shortstat
     moved from `327 files changed, 237562 insertions(+), 30713 deletions(-)`
     to `327 files changed, 237604 insertions(+), 30713 deletions(-)`. Every
     lane-status `latestCommit` remains pending, uncommitted, not committed, or
     equivalent lane-local/shared-dirty handoff prose.

2. **Critical - there is no audit-acceptable root PHP result for the current
   snapshot, and duplicate root/focused harnesses are already active.**
   - Paths: `tools/run-tests.php`, `lanes/*/lane-status.json`, `goal.md:49`.
   - Goal requirement at risk: repo-wide tests and static checks must be run
     periodically and failures recorded honestly.
   - Evidence: the required `pgrep -af '^php tools/run-tests\.php( |$)'` gate
     matched PID `2983499` for Readability at the start, no-argument root PID
     `2994892` owned by `claude` at 12:56Z, and focused Syncthing PID
     `3037673` owned by `claude` at 12:57Z. Starting another root harness would
     duplicate active work. Existing lane-local green results are not a coherent
     aggregate result for moving `HEAD` plus the current dirty tree.

3. **High - `porting.html` and `porting-summary.json` are stale enough to
   mislead coordination.**
   - Paths: `porting.html:34` through `porting.html:38`,
     `porting.html:56` through `porting.html:67`,
     `porting-summary.json:2` through `porting-summary.json:8`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`,
     `goal.md:3`, `goal.md:45`.
   - Goal requirement at risk: the dashboard must show current denominator,
     mapped tests, PHP pass/fail, phase, audit, current work, blocker, and
     commit.
   - Evidence: the dashboard claims source snapshot `89260857cc71` while current
     sampled `HEAD` is `2eb0a4a48f83`. Live metadata has moved beyond the page
     for Difftastic, esbuild, Gitoxide, libsqlite, LightningCSS, markerPDF,
     Pandoc, rclone, Readability, and Syncthing. The compact summary repeats the
     stale source commit and older lane counts.

4. **High - manifest/status counts remain non-normalized, including internal
   contradictions inside current lane evidence.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:841` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:843`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:278`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:280`,
     `goal.md:24`, `goal.md:25`, `goal.md:35`.
   - Goal requirement at risk: each lane needs a defensible upstream denominator,
     mapped upstream tests, PHP passing/failing counts, and honest blocker
     recording.
   - Evidence: markerPDF's top-level manifest reports `399` total and `350`
     mapped, while its latest evidence/warning/description still says `398` or
     `349`. Pandoc reports `mapped: 1908`, while the warning text says native
     PHP maps `1,922` focused checks. These may be explainable as local behavior
     checks versus denominator entries, but the schema does not distinguish them
     consistently enough for acceptance or dashboard regeneration.

5. **High - support-library coverage remains planning-only under the
   2026-05-24 11:59 UTC rich-function directive.**
   - Paths: `dependency-backlog.json:3` through `dependency-backlog.json:4`,
     `dependency-backlog.json:7` through `dependency-backlog.json:22`,
     `dependency-backlog.json:81` through `dependency-backlog.json:94`,
     `porting.html:93` through `porting.html:127`, `progress.md:17` through
     `progress.md:33`.
   - Goal requirement at risk: support libraries require the same granularity as
     lanes: bounded native PHP component, activation gate, dependency-specific
     upstream/spec denominator, mapped fixtures, PHP pass/fail evidence,
     malformed/corrupt cases where relevant, and as much upstream/spec-suite
     evidence as can honestly run.
   - Evidence: Pandoc's DOC, DOCX/OpenXML, PDF handoff/text extraction, EPUB,
     ODT/OpenDocument, templates, citations, math, tables, package containers,
     XML/HTML, Unicode/charset, JSON/YAML, and archive/compression needs are
     accounted for as backlog rows, but there are still `0` active support rows
     and no support-library-specific manifests or pass/fail evidence. This is
     routing, not accepted support-port progress.

6. **High - markerPDF still over-credits external runtime and shell-boundary
   planning beside native PDF work.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:545`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:580` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:582`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:780` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:790`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:842`,
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
   - Paths: `progress.md:148` through `progress.md:161`,
     `lanes/*/lane-status.json:11`, `goal.md:44`.
   - Goal requirement at risk: `progress.md` must include current owner/session,
     next task per lane, blockers, and percentage estimates.
   - Evidence: the Active Lanes table still names older handoffs such as
     gitoxide SSH config-options, markerPDF benchmark file-inventory planning,
     Pandoc NativeWriter figure/citation, rclone VFS Statfs/usage, Syncthing
     system log route, and esbuild JSX key/spread fallback. Current lane-status
     files instead describe gitoxide conflict access, markerPDF page-tree `/Kids`
     traversal, Pandoc HTML writer lists, rclone transferMap sections, Syncthing
     GUI static single-range handling, and esbuild DataURL handling.

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
