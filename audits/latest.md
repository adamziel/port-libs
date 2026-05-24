# Independent Audit - 2026-05-24T13:12Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json`, all 12
current `lanes/*/lane-status.json`, `dependency-backlog.json`, and recent Git
history through `861e4570 Record integration hold status`. I did not edit lane
implementation files, launch agents or tmux sessions, push, read secrets,
inspect process environments, credential stores, provider configs, or auth
files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24 13:08-13:12
HEAD: 861e45707628
recent history: 861e4570 Record integration hold status; 48b8d1a9 Refresh independent audit status; 5adfaa97 Record integration hold status; fa6f1d51 Record integration hold status; 990262b6 Refresh independent audit status
branch sample: main...origin/main [ahead 882, behind 68]
tracked dirty rows: 327
default status rows including untracked: 18114 -> 18119
git diff --shortstat: 327 files changed, 238829 insertions(+), 30792 deletions(-) -> 327 files changed, 239094 insertions(+), 30928 deletions(-)
dashboard snapshot: porting.html and porting-summary.json generated 2026-05-24 12:29:46 UTC from source 89260857cc71; sampled HEAD is 861e45707628
dependency backlog: dependency-backlog.json updated 2026-05-24 12:29:10 UTC with 37 rows (1 blocked, 25 candidate, 11 deferred, 0 active)
root run by this audit: not started
```

Required pre-root process gate:

```text
13:09Z pgrep -af '^php tools/run-tests\.php( |$)': no rows
13:12Z pgrep sample: 3168706 php tools/run-tests.php lanes/syncthing/tests
13:12Z owner evidence: 3168706 claude 3139528 00:39 Rs php tools/run-tests.php lanes/syncthing/tests
```

I did not start `php tools/run-tests.php`. The exact gate was clear at the
initial decision point, but the checkout was still changing during the audit
sample and a focused Syncthing PHP harness was active during validation. This
remains a broad dirty aggregate, not a frozen acceptance snapshot.

`jq empty` passed for all 12 lane manifests, all 12 lane-status files,
`porting-summary.json`, and `dependency-backlog.json`.

Current manifest/status drift sample:

```text
lane          current manifest/status             dashboard or summary
difftastic    manifest 1093/874, status 3302 pass  1077/851, 3245 pass
dolt          current work LEFT/RIGHT WHERE        dashboard still says HEX WHERE
esbuild       manifest 435, status 434 pass        429 pass; manifest warning says 433
gitoxide      status 7237 pass                     7152 pass
libsqlite     manifest/status 352 mapped/pass      349 mapped, 348 pass
LightningCSS  manifest 2773 mapped, status 4082    2765 mapped, 4065 pass
markerPDF     manifest 400/351, status 488 pass    396/347, 484 pass
pandoc        manifest 1908 mapped, status 365     1891 mapped, 362 pass
quadrable     status 234 pass                      232 pass
rclone        manifest/status 915 mapped/pass      906 mapped/pass
readability   status 3589 pass                     3545 pass
syncthing     status 7997 pass                     7902 pass
```

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `lanes/*/lane-status.json:13`, `progress.md:48`,
     `goal.md:29`, `goal.md:48`.
   - Goal requirement at risk: small reviewable slices must be committed with
     passing tests, and finished agent work must be verified, integrated,
     cleaned up, and assigned onward.
   - Evidence: the worktree has `327` tracked dirty rows, `18114 -> 18119`
     status rows including untracked paths, and shortstat moved during review
     from `327 files changed, 238829 insertions(+), 30792 deletions(-)` to
     `327 files changed, 239094 insertions(+), 30928 deletions(-)`. Every
     sampled lane-status `latestCommit` remains pending, uncommitted, not
     committed, or lane-local dirty-batch prose rather than an accepted commit.

2. **Critical - there is no audit-acceptable root PHP result for the current
   snapshot.**
   - Paths: `tools/run-tests.php`, `lanes/*/lane-status.json:12`,
     `goal.md:49`.
   - Goal requirement at risk: repo-wide tests and static checks must run
     periodically and failures must be recorded honestly.
   - Evidence: the required exact process gate returned no rows at 13:09Z, then
     validation matched focused Syncthing PID `3168706` owned by `claude`.
     The tree was moving and every lane handoff is still pending/uncommitted.
     Lane-local green focused runs do not establish a root result for
     `861e45707628` plus the current dirty tree.

3. **High - `porting.html` and `porting-summary.json` are stale enough to
   mislead coordination.**
   - Paths: `porting.html:32` through `porting.html:38`,
     `porting.html:56` through `porting.html:67`,
     `porting-summary.json:2` through `porting-summary.json:8`,
     `goal.md:3`, `goal.md:45`.
   - Goal requirement at risk: the dashboard must show current denominator,
     mapped tests, PHP pass/fail, phase, audit, current work, blocker, and
     commit.
   - Evidence: the dashboard still claims source snapshot `89260857cc71`, while
     sampled `HEAD` is `861e45707628`. Current manifests/statuses have advanced
     beyond the page for Difftastic, esbuild, Gitoxide, libsqlite,
     LightningCSS, markerPDF, Pandoc, Quadrable, rclone, Readability, and
     Syncthing; Dolt current work has also moved beyond the dashboard text.

4. **High - manifest/status counts remain non-normalized and internally
   contradictory.**
   - Paths: `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:17`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:383` through
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:385`,
     `lanes/esbuild/lane-status.json:5` through
     `lanes/esbuild/lane-status.json:6`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:842` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:845`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:287` through
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:291`,
     `lanes/pandoc/lane-status.json:5` through
     `lanes/pandoc/lane-status.json:6`,
     `goal.md:24`, `goal.md:25`, `goal.md:35`.
   - Goal requirement at risk: each lane needs a defensible upstream
     denominator, mapped upstream tests, PHP passing/failing counts, and honest
     blocker recording.
   - Evidence: esbuild top-level manifest now says `mapped: 435`, its warning
     still says native PHP maps `433`, and lane status says `434`. markerPDF
     top-level manifest reports `400` total and `351` mapped while embedded
     latest evidence still says `398`/`349`. Pandoc reports `mapped: 1908`,
     while its warning/latest note says `1,940` focused checks and lane status
     says `365` behavior tests. These may be distinct units, but the schema does
     not name the units consistently enough for acceptance or dashboard
     regeneration.

5. **High - support-library coverage remains planning-only under the
   2026-05-24 11:59 UTC rich-function directive.**
   - Paths: `dependency-backlog.json:3` through `dependency-backlog.json:4`,
     `dependency-backlog.json:7` through `dependency-backlog.json:22`,
     `dependency-backlog.json:81` through `dependency-backlog.json:95`,
     `porting.html:72` through `porting.html:78`, `progress.md:17` through
     `progress.md:33`.
   - Goal requirement at risk: support libraries require the same granularity as
     lanes: bounded native PHP component, activation gate,
     dependency-specific upstream/spec denominator, mapped fixtures, PHP
     pass/fail evidence, malformed/corrupt cases where relevant, and as much of
     the upstream/spec suite as can honestly run.
   - Evidence: the backlog has 37 rows and `0` active bounded support ports.
     Pandoc's DOC, DOCX/OpenXML, PDF handoff/text extraction, EPUB,
     ODT/OpenDocument, templates, citations, math, tables, package containers,
     XML/HTML, Unicode/charset, JSON/YAML, and archive/compression needs are
     routed as candidate/deferred rows, but there are no dependency-specific
     support manifests or pass/fail evidence in the tree. Routing is not
     accepted support-port progress.

6. **High - markerPDF still over-credits external runtime, shell-boundary, and
   package-planning work beside native PDF progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:546` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:547`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:582` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:584`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:782` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:790`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:797` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:808`,
     `goal.md:1`, `goal.md:30`.
   - Goal requirement at risk: wrappers around JS/Rust/Go/C binaries,
     shell-outs, bridge calls, whole applications, and external converter
     wrappers must not count as the main native deliverable.
   - Evidence: markerPDF has real native PDF text/resource/filter/outline
     progress. The same manifest also counts Pandoc/XeLaTeX command plans,
     chunk-convert shell lifecycle, Streamlit/FastAPI runtime plans, Poetry
     package and 190-package lock metadata, Tesseract/OCRMyPDF/Ghostscript
     readiness, Texify/Torch/Nougat handoffs, and subprocess command metadata
     in the mapped behavior surface. Those should remain preflight/blocker
     metadata unless split into bounded native support ports with their own
     denominator and PHP evidence.

7. **Medium - near-complete percentages overstate accepted upstream parity.**
   - Paths: `porting.html:32`, `porting.html:56` through `porting.html:67`,
     `porting-summary.json:8`, `lanes/*/lane-status.json:4`,
     `goal.md:35` through `goal.md:40`.
   - Goal requirement at risk: passing tests are not enough; upstream tests are
     the source of truth where possible, and hard gaps must be recorded as
     blockers or future slices.
   - Evidence: the dashboard reports `98.3%` average progress and most lanes at
     `98%` or `99%`, while aggregate root verification is pending, all current
     lane batches are uncommitted, support-library work has no active bounded
     ports, and full upstream runners remain static, bounded, unexecuted, or
     intentionally excluded for multiple lanes.

8. **Medium - current coordination still lags lane handoffs.**
   - Paths: `progress.md:48`, `porting-summary.json:21` through
     `porting-summary.json:24`, `porting-summary.json:72` through
     `porting-summary.json:75`, `lanes/*/lane-status.json:11`,
     `goal.md:44`.
   - Goal requirement at risk: `progress.md` must include current lane state,
     open blockers, current owner/session, next task per lane, and percentage
     estimates.
   - Evidence: the latest progress snapshot correctly says coordination is
     blocked, but the dashboard/summary still carries older current-work text
     for lanes such as Difftastic, Gitoxide, markerPDF, Pandoc, rclone,
     Readability, and Syncthing, while lane-status files describe newer slices.

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
