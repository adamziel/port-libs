# Independent Audit - 2026-05-24T08:17Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every root `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
current `lanes/*/lane-status.json`, `dependency-backlog.json`, recent Git
history, and the required PHP harness process gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider configs,
or auth files. Bridge code, generated fixtures, shell-outs, whole applications,
external converter wrappers, and hidden process launchers are treated as
non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T08:07Z through 2026-05-24T08:17Z
sampled pre-audit HEAD after latest hold commit: 1cdadbf954cd
recent history before this audit edit: 1cdadbf9 Record integration hold status; 650a455b Record integration hold status; afab86c1 Record integration hold status
branch divergence: main...origin/main [ahead 772, behind 68]
tracked dirty rows: 322
default status rows: 15984
untracked-inclusive status rows: 16072
git diff --shortstat latest sample: 322 files changed, 201433 insertions(+), 30140 deletions(-)
manifest/status JSON validation: jq reads succeeded for all 12 root lane manifests, all 12 lane-status files, porting-summary.json, and dependency-backlog.json
dependency backlog: 24 items; candidate 13, deferred 11, active 0
dashboard snapshot: porting.html and porting-summary.json still generated 2026-05-23 23:43:54 UTC from source 79768df0c427
root run by this audit: not started
```

Required root-run gate evidence:

```text
2026-05-24T08:07Z pgrep -af '^php tools/run-tests\.php( |$)': no rows
2026-05-24T08:10Z pgrep matched active no-argument root PID 3698751 plus focused Quadrable/Readability shards and a transient rclone shard
2026-05-24T08:10Z owner sample: 3698751 claude ... R+ php tools/run-tests.php; 3699335 claude ... php tools/run-tests.php lanes/quadrable/tests; 3699713 claude ... php tools/run-tests.php lanes/readability/tests
2026-05-24T08:14Z pgrep -af '^php tools/run-tests\.php( |$)': no rows
2026-05-24T08:16Z pgrep matched active no-argument root PID 3770064 plus focused Syncthing PID 3781646
2026-05-24T08:16Z owner sample: 3770064 claude ... R+ php tools/run-tests.php; 3781646 claude ... Rs php tools/run-tests.php lanes/syncthing/tests
2026-05-24T08:17Z pgrep -af '^php tools/run-tests\.php( |$)': no rows
```

I did not start `php tools/run-tests.php`. During the audit an exact
no-argument root harness was active, so a duplicate root run was forbidden. The
gate briefly cleared, then pre-commit validation matched another active
no-argument root harness; the checkout also remained a broad moving dirty
aggregate rather than a frozen acceptance snapshot.

Additional checks run by this audit:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json
jq empty lanes/*/lane-status.json porting-summary.json dependency-backlog.json
passed
```

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md:39`, `audits/integration-status.md`,
     `lanes/*/lane-status.json:13`, recent Git history.
   - Requirement at risk: `goal.md:29` requires small, reviewable slices with
     passing tests; `goal.md:48` requires finished agent output to be verified,
     committed, and integrated cleanly.
   - Evidence: recent history is still audit/hold dominated
     (`1cdadbf9`, `650a455b`, `afab86c1`, `02c6cb98`, `0500f313`). The
     worktree sample shows `322` tracked dirty rows, `15984` default status
     rows, `16072` untracked-inclusive rows, and `322 files changed, 201433
     insertions(+), 30140 deletions(-)`. Lane statuses still describe pending,
     uncommitted, or supervisor-owned handoffs rather than accepted commits.

2. **Critical - there is no acceptable root-harness result for the current
   snapshot.**
   - Paths: `tools/run-tests.php`, `progress.md:39`,
     `lanes/*/lane-status.json:12`.
   - Requirement at risk: `goal.md:49` requires repo-wide tests and static
     checks to be run periodically with failures recorded honestly.
   - Evidence: the required exact gate matched active no-argument root PID
     `3698751` owned by `claude` during the audit, plus focused lane shards, so
     this audit correctly did not start a duplicate. The later gate cleared
     only after the dirty snapshot had changed again. Lane blockers still say
     aggregate root verification is pending or supervisor/integrator owned.

3. **Critical - `porting.html` and `porting-summary.json` remain stale
   publication artifacts.**
   - Paths: `porting.html:32`, `porting.html:34`, `porting.html:35`,
     `porting.html:75`, `porting-summary.json:2`,
     `porting-summary.json:3`, `porting-summary.json:8`,
     `porting-summary.json:218`, `dependency-backlog.json:3`.
   - Requirement at risk: `goal.md:45` requires the dashboard to show current
     average progress, denominator, mapped tests, PHP pass/fail, WordPress
     scenarios, phase, audit, current work, blocker, and commit.
   - Evidence: the dashboard still reports average progress `97.7%`,
     generated `2026-05-23 23:43:54 UTC`, source snapshot `79768df0c427`, and
     `22` dependency items. Sampled pre-audit `HEAD` is `1cdadbf954cd`, and
     `dependency-backlog.json` is newer (`2026-05-24 08:10:35 UTC`) with `24`
     items.

4. **High - dashboard, manifest, and lane-status counts disagree across the
   portfolio.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json:11` through `porting-summary.json:213`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md:3` and `goal.md:45` require comparable
     upstream denominators, mapped-test counts, PHP pass/fail counts, and
     commit status.
   - Evidence: current manifest/status values versus dashboard include
     Difftastic `905 / 628 / 2955` vs `735 / 374 / 374`; Dolt prose total /
     `613 / 409` vs `inventory / 613 / 356`; esbuild `2567 / 387 / 387` vs
     `2567 / 311 / 311`; Gitoxide `2877 / 2877 / 6611` vs
     `2877 / 2751 / 5634`; libsqlite `1589 / 330 / 329` vs
     `1589 / 286 / 286`; LightningCSS `3532 / 2656 / 3836` vs
     `3532 / 1732 / 2197`; markerPDF `370 / 321 / 458` vs
     `330 / 280 / 416`; Pandoc `2276 / 1707 / 333` vs `2276 / 1061 / 278`;
     Quadrable `55 / 55 / 218` vs `55 / 55 / 190`; rclone
     `1601 / 847 / 847` vs `1601 / 698 / 698`; Readability
     `1984 / 1984 / 237` vs `1984 / 1984 / 204`; Syncthing
     `658 / 658 / 7048` vs `658 / 658 / 4579`.

5. **High - manifest/status schema remains non-normalized, with Dolt still
   internally inconsistent.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:17`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2478`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2484`,
     `lanes/dolt/lane-status.json:5`, `lanes/dolt/lane-status.json:6`,
     `lanes/dolt/lane-status.json:11`.
   - Requirement at risk: `goal.md:25` requires a real upstream benchmark
     denominator; `goal.md:3` requires durable coordination fields.
   - Evidence: Dolt's canonical `benchmarkDenominator.total` is still a long
     FIND_IN_SET prose string, while the current latest slice and status are
     STRCMP. The manifest reports `phpBehaviorTests = 398`, while lane status
     reports `phpPass = 409`. This is not a machine-readable denominator.

6. **High - lane handoffs are broad, pending, and uncommitted rather than
   small accepted slices.**
   - Paths: `lanes/*/lane-status.json:13`, `progress.md:39`, recent Git
     history.
   - Requirement at risk: `goal.md:29` requires small, reviewable commits with
     passing tests; `goal.md:48` requires integration of finished agent output.
   - Evidence: each lane still reports pending, not committed, uncommitted, or
     supervisor/integrator-owned latest-commit text. The current dirty surface
     spans all lanes plus dashboard and coordination files, so no lane batch can
     be accepted without a freeze and lane-scoped review.

7. **High - near-complete percentages overstate accepted upstream parity.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/*/lane-status.json:12`, `lanes/*/lane-status.json:13`.
   - Requirement at risk: `goal.md:35` says passing tests are not enough; each
     lane needs real upstream denominators, fixture parity, edge cases, error
     behavior, docs/examples, and honest blockers.
   - Evidence: dashboard rows still show `92%` to `99%`, but blockers still
     record unexecuted full upstream runners, pending root aggregate
     verification, and missing accepted commits. Focused green lane anecdotes
     do not establish repository-level parity for the frozen snapshot.

8. **High - essential optional-library coverage is still backlog-only.**
   - Paths: `dependency-backlog.json:3`, `dependency-backlog.json:7`,
     `dependency-backlog.json:25`, `dependency-backlog.json:45`,
     `dependency-backlog.json:111`, `dependency-backlog.json:302`,
     `dependency-backlog.json:398`, `dependency-backlog.json:438`,
     `porting.html:75`.
   - Requirement at risk: this audit requires each support library to have a
     bounded native PHP component, activation gate, dependency-specific
     upstream/spec denominator, mapped fixtures, PHP pass/fail evidence,
     malformed/corrupt cases where relevant, and as much executable upstream or
     spec-suite evidence as can actually run.
   - Evidence: the backlog now has `24` items and zero active ports. Rich gaps
     remain for ZIP/package, XML/HTML5, DOCX/OpenXML, CFB/DOC, EPUB, ODT,
     doctemplates, CSL/citations, math/TeX, PDF engine handoff, PDF text,
     render planning, OCR/layout, table geometry, Unicode, charset, source
     maps, tree-sitter subsets, protobuf, checksums, SQL/storage, archive and
     compression streams, glob/pathspec, and provider metadata.

9. **High - rclone dependency expansion is broad and lane-local, so it should
   not count as shared optional-library progress.**
   - Paths: `lanes/rclone/lane-status.json:5`,
     `lanes/rclone/lane-status.json:8`, `lanes/rclone/lane-status.json:11`,
     `lanes/rclone/lane-status.json:12`, `lanes/rclone/lane-status.json:13`,
     `dependency-backlog.json:25`, `dependency-backlog.json:398`,
     `dependency-backlog.json:438`.
   - Requirement at risk: support-library expansion must be bounded, gated,
     tested, shared where appropriate, and backed by a dependency-specific
     denominator.
   - Evidence: rclone now carries WebDAV XML, PROPFIND/PROPPATCH, LOCK/If,
     COPY/MOVE, gzip, serve middleware, auth-proxy, custom directory-template,
     OneDrive/provider metadata, and x/net copyProps behavior in lane-local
     slices. Those are useful rclone behaviors, but not shared XML/WebDAV,
     compression, provider, checksum, or pathspec support-library progress
     until separate gates, manifests, malformed cases, and pass/fail evidence
     exist.

10. **High - markerPDF still mixes native PDF work with external/runtime
    orchestration plans.**
    - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:10`,
      `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
      `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
      `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:487`,
      `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:619`,
      `lanes/markerpdf/lane-status.json:5`,
      `lanes/markerpdf/lane-status.json:12`.
    - Requirement at risk: `goal.md:1` and `goal.md:30` forbid counting
      wrappers, bridge calls, shell-outs, or external converter/runtime
      execution as native port progress.
    - Evidence: markerPDF has real native PDF text/font/filter work, but its
      manifest/status still carry marker_server, marker_app, convert.py,
      chunk_convert.sh, pdftext dictionary output, Tesseract, Ghostscript,
      OCRMyPDF, Pandoc/XeLaTeX, Poetry, Streamlit, FastAPI/Uvicorn, Torch,
      Surya, Texify, Nougat, and multiprocessing lifecycle plans. These must
      remain preflight/oracle metadata unless bounded PHP components own the
      behavior.

11. **Medium - Gitoxide shell-outs in tests must remain oracle tooling, not
    progress evidence.**
    - Paths: `lanes/gitoxide/tests/GitUrlTest.php:70`,
      `lanes/gitoxide/tests/GitUrlTest.php:104`,
      `lanes/gitoxide/tests/FetchV2SessionTest.php:13`,
      `lanes/gitoxide/tests/FetchResponseTest.php:18`,
      `lanes/gitoxide/lane-status.json:12`.
    - Requirement at risk: `goal.md:30` says generated fixtures, bridge calls,
      and shell-outs must not count as native implementation progress.
    - Evidence: Gitoxide tests still use `proc_open` around git-backed helpers.
      That is acceptable only as explicit oracle or fixture tooling; accepted
      progress must be the native PHP parser/transport behavior and its mapped
      pass/fail evidence.

12. **Medium - focused green anecdotes dominate over frozen acceptance
    evidence.**
    - Paths: `lanes/*/lane-status.json:5` through
      `lanes/*/lane-status.json:13`, `progress.md:39`.
    - Requirement at risk: `goal.md:37` says upstream tests are the source of
      truth whenever possible, and `goal.md:49` requires repo-wide tests and
      static checks.
    - Evidence: lane statuses report many focused PHP and bounded upstream
      passes, but the repository lacks one accepted snapshot tying manifests,
      statuses, dashboard output, focused tests, `git diff --check`, and one
      serialized no-argument root harness result together.

## Next Intervention

Freeze writers, runners, status refreshers, and dashboard updates. Wait for any
root or focused harness to finish, then take two stable polls of `HEAD`, tracked
status, untracked-inclusive status, `git diff --shortstat`, and the exact
`pgrep -af '^php tools/run-tests\.php( |$)'` gate. Accept or reject one
lane-scoped batch at a time, normalize manifest/status count fields, run focused
lane tests plus `git diff --check`, run exactly one no-argument root harness
only if the gate is empty, regenerate `porting.html` and `porting-summary.json`
from the accepted commit, then commit or reject the lane batch.
