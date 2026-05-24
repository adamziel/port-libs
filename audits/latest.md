# Independent Audit - 2026-05-24T13:33Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json`, all 12
current `lanes/*/lane-status.json`, `dependency-backlog.json`, and recent Git
history through `35121fcf Record integration hold status`. I did not edit lane
implementation files, launch agents or tmux sessions, push, read secrets,
inspect process environments, credential stores, provider configs, or auth
files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24 13:28-13:32
HEAD moved during audit: 7e93a807e9a2 -> f8b79dd0b177 -> 35121fcf7039
recent history: 35121fcf Record integration hold status; f8b79dd0 Record integration hold status; 7e93a807 Refresh independent audit status; fb2b1c2d Record integration hold status; 3772968a Record integration hold status
branch sample: main...origin/main [ahead 888, behind 68]
tracked dirty rows: 330 -> 331
default status rows including untracked: 18281 -> 18348
git diff --shortstat: 330 files changed, 241773 insertions(+), 31011 deletions(-) -> 331 files changed, 242272 insertions(+), 31111 deletions(-)
dashboard snapshot: porting.html and porting-summary.json still publish source 89260857cc71; sampled HEAD is 35121fcf7039
dependency backlog: dependency-backlog.json has 37 rows (1 blocked, 25 candidate, 11 deferred, 0 active)
root run by this audit: not started
```

Required pre-root process gate:

```text
13:28Z pgrep -af '^php tools/run-tests\.php( |$)': no rows

13:29Z pgrep -af '^php tools/run-tests\.php( |$)':
3417294 php tools/run-tests.php lanes/syncthing/tests

owner evidence:
PID 3417294, USER claude, PPID 3251165, elapsed 00:39, state Rs, command php tools/run-tests.php lanes/syncthing/tests

13:30Z validation pgrep -af '^php tools/run-tests\.php( |$)':
3417294 php tools/run-tests.php lanes/syncthing/tests

13:31Z validation pgrep -af '^php tools/run-tests\.php( |$)':
3484115 php tools/run-tests.php

owner evidence:
PID 3484115 exited before owner sampling

13:32Z validation pgrep -af '^php tools/run-tests\.php( |$)': no rows
```

I did not start `php tools/run-tests.php`. No audit-owned no-argument root
harness was started because the checkout moved during sampling and the exact
process gate later matched an active focused Syncthing PHP harness plus a
transient no-argument root harness that exited before owner sampling.

`jq empty` passed for all 12 lane manifests, all 12 lane-status files,
`porting-summary.json`, and `dependency-backlog.json`.

Current manifest/status drift sample:

```text
lane          current manifest/status             dashboard or summary
difftastic    manifest 1108/894, status 3323      1077/851, 3245 pass
dolt          status has 13:34 LPAD/RPAD slice    dashboard still says older HEX/query-diff work
esbuild       manifest/status 436; warning 433    429 mapped/pass
gitoxide      status 7247 pass                    7152 pass
libsqlite     manifest/status 354                 349 mapped, 348 pass
LightningCSS  manifest 2777, status 4089          2765 mapped, 4065 pass
markerPDF     manifest 402/353, status 490        396/347, 484 pass
pandoc        manifest 1908, status note 1953     1891 mapped, 362 pass
quadrable     status 235 pass                     232 pass
rclone        manifest 921 mapped, 915 php tests; status 921 pass; warning 869
readability   status 3601 pass                    3545 pass
syncthing     status 8058 pass                    7902 pass
```

## Findings

1. **Critical - the repository is still not an acceptance checkpoint.**
   - Paths: `progress.md:15`, `progress.md:48`,
     `lanes/*/lane-status.json:13`, `goal.md:29`, `goal.md:48`.
   - Goal requirement at risk: small reviewable slices must be committed with
     passing tests, and finished agent work must be verified, committed, cleaned
     up, and assigned onward.
   - Evidence: `HEAD` moved from `7e93a807e9a2` through `f8b79dd0b177` to
     `35121fcf7039` while this audit was sampling. The dirty tree also moved
     from `18281` to `18348` untracked-inclusive status rows and from `330 files
     changed, 241773 insertions(+), 31011 deletions(-)` to `331 files changed,
     242272 insertions(+), 31111 deletions(-)`. Current lane `latestCommit`
     fields remain pending, uncommitted, or broad shared-dirty-worktree prose.

2. **Critical - a root run would be invalid on this snapshot.**
   - Paths: `tools/run-tests.php`, `progress.md:48`, `progress.md:608`,
     `goal.md:49`.
   - Goal requirement at risk: repo-wide tests and static checks must be
     recorded honestly from a stable snapshot and must not duplicate active
     harness work.
   - Evidence: the required exact process gate was clear at 13:28Z but later
     matched active focused Syncthing PID `3417294` owned by `claude`, then
     transient no-argument root PID `3484115` before it exited. The tree was
     also moving and integration-hold commits landed during the audit. I did not
     start a no-argument root run.

3. **High - `porting.html` and `porting-summary.json` are stale enough to
   mislead coordination.**
   - Paths: `porting.html:32`, `porting.html:34`, `porting.html:35`,
     `porting.html:56`, `porting.html:67`, `porting-summary.json:2`,
     `porting-summary.json:3`, `goal.md:3`, `goal.md:45`.
   - Goal requirement at risk: the dashboard must show current denominator,
     mapped tests, PHP pass/fail, phase, audit, current work, blocker, and
     commit.
   - Evidence: both generated artifacts still publish source commit
     `89260857cc71`, while sampled `HEAD` is `35121fcf7039`. Current
     manifests/statuses exceed the dashboard for Difftastic, esbuild, Gitoxide,
     libsqlite, LightningCSS, markerPDF, Pandoc, Quadrable, rclone,
     Readability, and Syncthing; Dolt current-work/audit text also moved beyond
     the dashboard.

4. **High - manifest/status counts and timestamps remain non-normalized.**
   - Paths: `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:16`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:387`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:393`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:1314`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:1450`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:291`,
     `lanes/pandoc/lane-status.json:5`,
     `lanes/dolt/lane-status.json:5`, `goal.md:24`, `goal.md:35`.
   - Goal requirement at risk: every lane needs a defensible denominator,
     mapped upstream tests, PHP passing/failing counts, and precise blocker
     recording.
   - Evidence: esbuild has `mapped: 436` and `phpBehaviorTests: 436` but its
     warning still says native PHP maps `433` focused tests. rclone has
     `mapped: 921`, `phpBehaviorTests: 915`, lane status `phpPass: 921`, and a
     stale warning that says `869`. Pandoc manifest says `mapped: 1908`, while
     lane status says `1,953` focused checks and `367` PHP passes. Dolt status
     recorded a `2026-05-24 13:34 UTC` slice while the shell clock was
     `2026-05-24 13:30 UTC`, and it cites an exact root PID that was no longer
     present when sampled.

5. **High - support-library coverage remains planning-only under the latest
   rich-function directive.**
   - Paths: `dependency-backlog.json:7`, `dependency-backlog.json:18`,
     `dependency-backlog.json:19`, `dependency-backlog.json:81`,
     `dependency-backlog.json:91`, `dependency-backlog.json:129`,
     `dependency-backlog.json:139`, `dependency-backlog.json:145`,
     `dependency-backlog.json:157`, `dependency-backlog.json:163`,
     `dependency-backlog.json:173`, `dependency-backlog.json:179`,
     `dependency-backlog.json:190`, `dependency-backlog.json:214`,
     `dependency-backlog.json:228`, `dependency-backlog.json:233`,
     `dependency-backlog.json:251`, `dependency-backlog.json:256`,
     `dependency-backlog.json:267`, `dependency-backlog.json:272`,
     `dependency-backlog.json:283`, `dependency-backlog.json:322`,
     `dependency-backlog.json:334`, `dependency-backlog.json:340`,
     `dependency-backlog.json:359`, `dependency-backlog.json:365`,
     `dependency-backlog.json:385`, `dependency-backlog.json:413`,
     `dependency-backlog.json:423`, `dependency-backlog.json:629`,
     `dependency-backlog.json:643`, `porting.html:75`, `porting.html:77`,
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
     routed as candidate/deferred rows, but there are no dependency-specific
     support manifests or pass/fail results. `porting-summary.json:217` says
     missing build/test packages require bounded `sudo -n` install attempts
     before final blocker status; no active support-library suite attempt or
     bounded install evidence exists yet.

6. **High - markerPDF still over-credits external runtime, shell-boundary, and
   package-planning work beside real native PDF progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:812`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:816`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:817`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:822`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:850`,
     `lanes/markerpdf/lane-status.json:12`, `goal.md:1`, `goal.md:30`.
   - Goal requirement at risk: wrappers around JS/Rust/Go/C binaries,
     shell-outs, bridge calls, whole applications, and external converter
     wrappers must not count as the native deliverable.
   - Evidence: markerPDF has useful native PDF text/resource/filter progress.
     The same mapped surface still includes Nougat subprocess command planning,
     benchmark archive/package inventory, Pandoc/XeLaTeX helper plans,
     Streamlit/FastAPI/PIL/pypdfium server/app boundaries, Poetry/package
     metadata, OCRMyPDF/Tesseract/Ghostscript readiness, Texify/Torch/model
     gates, and shell lifecycle plans. Those should remain blocker/preflight
     metadata or be split into bounded support-library rows before receiving
     progress credit.

7. **Medium - near-complete percentages overstate accepted upstream parity.**
   - Paths: `porting.html:32`, `porting.html:56`, `porting.html:67`,
     `porting-summary.json:8`, `lanes/*/lane-status.json:12`, `goal.md:35`,
     `goal.md:40`.
   - Goal requirement at risk: passing tests are not enough; upstream tests are
     the source of truth where possible, and hard gaps must be recorded as
     blockers or future slices.
   - Evidence: the public dashboard reports `98.3%` average progress and most
     lanes at `98%` or `99%`, while current lane batches remain uncommitted or
     pending, root aggregate verification is absent for the moving tree,
     support-library work has no active bounded ports, and full upstream
     runners remain static, bounded, unexecuted, or intentionally excluded for
     multiple lanes.

8. **Medium - recent history is dominated by status/audit commits rather than
   accepted implementation integration.**
   - Paths: `audits/latest.md`, `audits/integration-status.md:1`,
     `progress.md:48`, `lanes/*/lane-status.json:13`, `goal.md:20`,
     `goal.md:48`.
   - Goal requirement at risk: the supervisor must integrate useful work,
     enforce standards, keep the roadmap honest, and assign the next
     highest-value slice after verification.
   - Evidence: the latest sampled commits are `35121fcf Record integration hold
     status`, `f8b79dd0 Record integration hold status`, `7e93a807 Refresh
     independent audit status`, `fb2b1c2d Record integration hold status`, and
     `3772968a Record integration hold status`. That preserves the hold but
     does not convert lane-local evidence into accepted implementation
     checkpoints.

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
