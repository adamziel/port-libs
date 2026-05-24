# Independent Audit - 2026-05-24T13:24Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json`, all 12
current `lanes/*/lane-status.json`, `dependency-backlog.json`, and recent Git
history through `fb2b1c2d Record integration hold status`. I did not edit lane
implementation files, launch agents or tmux sessions, push, read secrets,
inspect process environments, credential stores, provider configs, or auth
files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24 13:21-13:24
HEAD moved during audit: 3772968af952 -> fb2b1c2d1aa1
recent history: fb2b1c2d Record integration hold status; 3772968a Record integration hold status; dca5f795 Refresh independent audit status; ac591e31 Refresh independent audit status; be0958c5 Record integration hold status
branch: main...origin/main [ahead 887, behind 68]
default status rows including untracked: 18207 -> 18279
git diff --shortstat: 329 files changed, 239931 insertions(+), 30636 deletions(-) -> 331 files changed, 240815 insertions(+), 30862 deletions(-)
dashboard snapshot: porting.html and porting-summary.json still publish source 89260857cc71; sampled HEAD is fb2b1c2d1aa1
dependency backlog: dependency-backlog.json has 37 rows (1 blocked, 25 candidate, 11 deferred, 0 active)
root run by this audit: not started
```

Required pre-root process gate:

```text
13:21Z pgrep -af '^php tools/run-tests\.php( |$)': no rows

later pgrep -af '^php tools/run-tests\.php( |$)':
3301214 php tools/run-tests.php lanes/syncthing/tests/ProgressEmitterSchedulerTest.php ... lanes/syncthing/tests/RequestServerTest.php
3306289 php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
3318189 php tools/run-tests.php lanes/syncthing/tests

owner evidence:
PID 3301214, USER claude, PPID 3301124, elapsed 00:30, state R+, command php tools/run-tests.php lanes/syncthing/tests/...
PID 3318189, USER claude, PPID 3251165, elapsed 01:17, state Rs, command php tools/run-tests.php lanes/syncthing/tests
PID 3306289 exited before owner sampling

post-edit validation pgrep -af '^php tools/run-tests\.php( |$)': no rows
```

I did not start `php tools/run-tests.php`. No no-argument root harness was
present in any sampled gate, but the checkout was actively changing and focused
PHP harnesses were active during the audit.

`jq empty` passed for all 12 lane manifests, all 12 lane-status files,
`porting-summary.json`, and `dependency-backlog.json`.

Current manifest/status drift sample:

```text
lane          current manifest/status             dashboard or summary
difftastic    manifest 1099/885, status 3315      1077/851, 3245 pass
dolt          status 425, current REPEAT/REPLACE  dashboard still says older query-diff work
esbuild       manifest 436, status 435            429 pass / mapped; manifest warning still says 433
gitoxide      status 7247 pass                    7152 pass
libsqlite     manifest/status 353                 349 mapped, 348 pass
LightningCSS  manifest 2777, status 4089          2765 mapped, 4065 pass
markerPDF     manifest 402/353, status 401/352    396/347, 484 pass
pandoc        manifest 1908, status note 1940     1891 mapped, 362 pass
quadrable     status 235 pass                     232 pass
rclone        manifest/status 917                 906 mapped/pass
readability   status 3601 pass                    3545 pass
syncthing     status 8028 pass                    7902 pass
```

## Findings

1. **Critical - the repository is still not an acceptance checkpoint.**
   - Paths: `progress.md:46`, `progress.md:48`,
     `lanes/*/lane-status.json:13`, `goal.md:29`, `goal.md:48`.
   - Goal requirement at risk: small reviewable slices must be committed with
     passing tests, and finished agent work must be verified, committed, cleaned
     up, and assigned onward.
   - Evidence: `HEAD` moved from `3772968af952` to `fb2b1c2d1aa1`, and the
     dirty tree moved during this run from `18207` to `18279`
     untracked-inclusive status rows and from `329 files changed, 239931
     insertions(+), 30636 deletions(-)` to `331 files changed, 240815
     insertions(+), 30862 deletions(-)`. Lane `latestCommit` fields remain
     pending, uncommitted, or supervisor-owned across sampled statuses.

2. **Critical - a root run would be invalid even though no no-argument root
   harness was active at the final sample.**
   - Paths: `tools/run-tests.php`, `progress.md:48`, `goal.md:49`.
   - Goal requirement at risk: repo-wide tests and static checks must be
     recorded honestly from a stable snapshot and not duplicated on a moving
     tree.
   - Evidence: the required exact process gate was clear at 13:21Z but later
     matched focused Syncthing PIDs `3301214` and `3318189` plus transient
     libsqlite PID `3306289`. The tree also changed during the audit, including
     an integration-status commit and markerPDF/LightningCSS metadata updates.
     I did not start a root run because this was not a frozen acceptance
     snapshot.

3. **High - `porting.html` and `porting-summary.json` are stale enough to
   mislead coordination.**
   - Paths: `porting.html:32`, `porting.html:35`, `porting.html:56`,
     `porting.html:67`, `porting-summary.json:3`, `goal.md:3`, `goal.md:45`.
   - Goal requirement at risk: the dashboard must show current denominator,
     mapped tests, PHP pass/fail, phase, audit, current work, blocker, and
     commit.
   - Evidence: the dashboard still publishes source snapshot `89260857cc71`
     while sampled `HEAD` is `fb2b1c2d1aa1`. Current manifests/statuses exceed
     the dashboard for Difftastic, esbuild, Gitoxide, libsqlite, LightningCSS,
     markerPDF, Pandoc, Quadrable, rclone, Readability, and Syncthing; Dolt
     current-work text also moved beyond the dashboard.

4. **High - manifest/status counts are actively non-normalized and sometimes
   internally contradictory.**
   - Paths: `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:16`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:387`,
     `lanes/esbuild/lane-status.json:6`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/markerpdf/lane-status.json:5`,
     `lanes/markerpdf/lane-status.json:10`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:291`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:293`,
     `lanes/pandoc/lane-status.json:5`, `goal.md:24`, `goal.md:35`.
   - Goal requirement at risk: each lane needs a defensible upstream
     denominator, mapped upstream tests, PHP passing/failing counts, and honest
     blocker recording.
   - Evidence: esbuild manifest says `mapped: 436`, lane status says `435`
     PHP pass, and the manifest warning still says native PHP maps `433`
     focused tests. markerPDF manifest moved to `402/353` while lane status
     still says `401/352` and the dashboard says `396/347`. Pandoc manifest
     says `mapped: 1908`, while its warning/status prose says `1,940` focused
     checks and lane status says `366` PHP behavior tests.

5. **High - support-library coverage remains planning-only under the
   2026-05-24 11:59 UTC rich-function directive.**
   - Paths: `dependency-backlog.json:3`, `dependency-backlog.json:7`,
     `dependency-backlog.json:19`, `dependency-backlog.json:82`,
     `dependency-backlog.json:91`, `porting.html:72`, `porting.html:77`,
     `progress.md:17`.
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
     support manifests or pass/fail evidence. Routing is not support-port
     progress.

6. **High - markerPDF still over-credits external runtime, shell-boundary, and
   package-planning work beside real native PDF progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:786`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:794`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:812`,
     `lanes/markerpdf/lane-status.json:12`, `goal.md:1`, `goal.md:30`.
   - Goal requirement at risk: wrappers around JS/Rust/Go/C binaries,
     shell-outs, bridge calls, whole applications, and external converter
     wrappers must not count as the main native deliverable.
   - Evidence: markerPDF has useful native PDF text/resource/filter/outline
     progress. The same manifest also counts Pandoc/XeLaTeX helper plans,
     chunk-convert shell lifecycle, Streamlit/FastAPI runtime plans, Poetry
     metadata, Tesseract/OCRMyPDF/Ghostscript readiness, Texify/Torch/Nougat
     handoffs, and subprocess command metadata in the mapped behavior surface.
     Those should remain preflight/blocker metadata unless split into bounded
     native support ports with their own denominator and PHP evidence.

7. **Medium - near-complete percentages overstate accepted upstream parity.**
   - Paths: `porting.html:32`, `porting.html:56`, `porting.html:67`,
     `lanes/*/lane-status.json:4`, `goal.md:35`, `goal.md:40`.
   - Goal requirement at risk: passing tests are not enough; upstream tests are
     the source of truth where possible, and hard gaps must be recorded as
     blockers or future slices.
   - Evidence: the dashboard reports `98.3%` average progress and most lanes at
     `98%` or `99%`, while aggregate root verification is absent for the
     current moving tree, all sampled lane batches are uncommitted or pending,
     support-library work has no active bounded ports, and full upstream runners
     remain static, bounded, unexecuted, or intentionally excluded for multiple
     lanes.

8. **Medium - recent history is dominated by audit/status commits rather than
   accepted implementation integration.**
   - Paths: `progress.md:48`, `audits/latest.md`,
     `audits/integration-status.md:1`, `lanes/*/lane-status.json:13`,
     `goal.md:20`, `goal.md:48`.
   - Goal requirement at risk: the supervisor must integrate useful work,
     enforce standards, keep the roadmap honest, and assign the next
     highest-value slice after verification.
   - Evidence: the latest sampled commits are `fb2b1c2d Record integration
     hold status`, `3772968a Record integration hold status`, `dca5f795 Refresh
     independent audit status`, `ac591e31 Refresh independent audit status`,
     and `be0958c5 Record integration hold status`. That preserves the hold but
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
