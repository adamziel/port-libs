# Independent Audit - 2026-05-24T06:45Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every root `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
current `lanes/*/lane-status.json`, `dependency-backlog.json`, recent Git
history, and the required PHP harness process gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, shell-outs, whole
applications, external converter wrappers, and hidden process launchers are
treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T06:40:54Z through 2026-05-24T06:45:36Z
HEAD movement during audit: ec9ad5f90ece -> ba65ef3dcf8c
recent commits: ba65ef3d Record integration hold status; ec9ad5f9 Refresh independent audit status; e9b7bf58 Record integration hold status
branch divergence: main...origin/main [ahead 740, behind 68]
tracked dirty rows: 314 -> 315
default status rows including untracked: 14678 -> 14694
git diff --shortstat: 314 files changed, 186625 insertions(+), 28459 deletions(-) -> 315 files changed, 187292 insertions(+), 28910 deletions(-)
manifest JSON validation: jq reads succeeded for all 12 root lane manifests
lane status JSON validation: jq reads succeeded for all 12 lane-status files
dependency backlog: 23 items; no active support-library port
dashboard snapshot: porting.html and porting-summary.json generated 2026-05-23 23:43:54 UTC from source 79768df0c427
root run by this audit: not started
```

Required root-run gate evidence:

```text
pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T06:40:54Z:
2382080 php tools/run-tests.php lanes/syncthing/tests/PullFinisherTest.php ... lanes/syncthing/tests/ServiceMapTest.php
2383655 php tools/run-tests.php lanes/syncthing/tests

pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T06:41:14Z:
2383655 php tools/run-tests.php lanes/syncthing/tests
2404842 php tools/run-tests.php lanes/readability/tests

pgrep -af '^php tools/run-tests\.php( |$)' at the later root gate:
2414278 php tools/run-tests.php lanes/markerpdf/tests
2415153 php tools/run-tests.php

ps -o pid,user,ppid,etime,stat,command -p 2414278,2415153:
2414278 claude 2308216 00:03 Rs php tools/run-tests.php lanes/markerpdf/tests
2415153 claude 2415112 00:01 R  php tools/run-tests.php

final pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T06:42:42Z:
2445072 php tools/run-tests.php lanes/quadrable/tests/QuadbStoreTest.php

handoff pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T06:45:36Z:
2488219 php tools/run-tests.php
2490514 php tools/run-tests.php lanes/syncthing/tests/PullFinisherTest.php ... lanes/syncthing/tests/ServiceMapTest.php

ps -o pid,user,ppid,etime,stat,command -p 2488219,2490514:
2488219 claude 2488132 01:20 R+ php tools/run-tests.php
```

I did not start `php tools/run-tests.php`. The checkout moved during the audit
window, and the later exact process gate matched an active no-argument root
harness owned by `claude`, followed by more focused lane harness churn. The
handoff check again matched active no-argument root PID `2488219` owned by
`claude`. A root run from this source state would not be an acceptance
checkpoint.

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md:37`, `progress.md:39`, current Git status and recent
     Git history.
   - Requirement at risk: `goal.md:29`, `goal.md:48`, and `goal.md:49` require
     small reviewable slices, verification before integration, and honest
     repo-wide checks.
   - Evidence: `HEAD` advanced from `ec9ad5f90ece` to `ba65ef3dcf8c` during
     this audit. Tracked dirty rows changed `314 -> 315`, total status rows
     changed `14678 -> 14694`, and shortstat changed from `314 files changed,
     186625 insertions(+), 28459 deletions(-)` to `315 files changed, 187292
     insertions(+), 28910 deletions(-)`. The branch is also
     `main...origin/main [ahead 740, behind 68]`.

2. **Critical - no coherent no-argument root-harness result can be accepted for
   this snapshot.**
   - Paths: `tools/run-tests.php`, `progress.md:39`, `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md:49` requires periodic repo-wide tests and
     static checks with failures recorded honestly.
   - Evidence: the required process gate was occupied by focused Syncthing,
     Readability, markerPDF, and Quadrable runs, later by active no-argument
     root PID `2415153` owned by `claude`, and at handoff by active no-argument
     root PID `2488219` owned by `claude`. Because `HEAD` and the dirty tree
     moved while those harnesses were active, any root result from this window
     would be diagnostic only, not integration evidence.

3. **Critical - `porting.html` and `porting-summary.json` fail the current
   dashboard contract.**
   - Paths: `porting.html:32`, `porting.html:34`, `porting.html:35`,
     `porting.html:56` through `porting.html:67`, `porting.html:75` through
     `porting.html:78`, `porting-summary.json:2` through
     `porting-summary.json:8`.
   - Requirement at risk: `goal.md:3` and `goal.md:45` require a current
     dashboard with per-lane benchmark source, upstream denominator, mapped
     tests, PHP pass/fail, WordPress scenarios, phase, audit, current work,
     blocker, and commit.
   - Evidence: the dashboard still advertises average progress `97.7%`,
     generated time `2026-05-23 23:43:54 UTC`, and source snapshot
     `79768df0c427`, while current `HEAD` is `ba65ef3dcf8c`. The dashboard
     dependency section still shows 22 items, but `dependency-backlog.json` now
     has 23.

4. **High - manifest denominator schema is still not mechanically reliable.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2456`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`.
   - Requirement at risk: `goal.md:24`, `goal.md:25`, `goal.md:38`, and
     `goal.md:45` require real upstream denominators and comparable dashboard
     fields.
   - Evidence: `benchmarkDenominator.total` is prose in four manifests. Dolt's
     value is the latest FIND_IN_SET slice narrative rather than a denominator.
     Some manifests add numeric side fields, but the canonical `total` field is
     still unsafe for durable counts and percentages.

5. **High - dashboard, manifest, and lane-status counts disagree across most
   lanes.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json:10` through `porting-summary.json:77`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md:25`, `goal.md:44`, and `goal.md:45` require
     reliable upstream denominators, mapped test counts, PHP pass/fail counts,
     blockers, and current status.
   - Evidence: current manifests/statuses versus dashboard include Difftastic
     `875 total / 550 mapped / 2861 PHP pass` vs dashboard `735 / 374 / 374`;
     Dolt `397 PHP pass` vs `356`; esbuild `371 mapped / 369 pass` vs
     `311 / 311`; Gitoxide `6416 pass` vs `5634`; libsqlite `321 pass` vs
     `286`; LightningCSS `2399 mapped / 3394 pass` vs `1732 / 2197`;
     markerPDF `363 total / 314 mapped / 450 pass` vs `330 / 280 / 416`;
     Pandoc `1570 mapped / 324 pass` vs `1061 / 278`; Quadrable `211 pass` vs
     `190`; rclone `827` vs `698`; Readability `236` vs `204`; and Syncthing
     `6636 assertions/pass count` vs dashboard `4579`.

6. **High - near-complete percentages overstate accepted upstream parity.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/*/lane-status.json`, runner/status fields in
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Requirement at risk: `goal.md:33` through `goal.md:40` require real
     denominator parity, fixture parity, edge/error behavior, and honest
     blockers.
   - Evidence: most lane statuses report `98-99%`, but lane-status blockers
     repeatedly say root aggregate verification is pending for the
     supervisor/integrator, full Cargo/Go/BATS/Haskell/upstream runner parity is
     unexecuted or bounded, and many new batches remain `pending` or
     `uncommitted` in the shared dirty worktree.

7. **High - essential optional-library coverage remains backlog-only, not
   accepted support-library ports.**
   - Paths: `dependency-backlog.json:7`, `dependency-backlog.json:25`,
     `dependency-backlog.json:45`, `dependency-backlog.json:61`,
     `dependency-backlog.json:77`, `dependency-backlog.json:111`,
     `porting.html:75`, `porting-summary.json:2`.
   - Requirement at risk: this audit run requires support libraries to have a
     bounded native PHP component, activation gate, dependency-specific
     upstream/spec denominator, mapped fixtures, PHP pass/fail evidence, and
     malformed/corrupt cases where relevant.
   - Evidence: `dependency-backlog.json` has 23 candidate/deferred items and no
     active support-library port. Rich-function gaps remain for ZIP/package,
     XML/HTML5, DOCX/OpenXML, legacy DOC/CFB, EPUB, ODT, doctemplates,
     citations/CSL, math/TeX, PDF text, PDF render planning, OCR/layout, table
     geometry, Unicode, charset, source maps, protobuf, checksum/hash, storage
     codecs, archive streams, glob/pathspec, and provider metadata.

8. **High - rclone dependency expansion is happening lane-locally instead of
   through bounded shared gates.**
   - Paths: `lanes/rclone/lane-status.json:5`,
     `lanes/rclone/lane-status.json:9` through `lanes/rclone/lane-status.json:12`,
     `dependency-backlog.json:25`, `dependency-backlog.json:35`.
   - Requirement at risk: this audit run requires dependency expansion to be
     bounded, gated, tested, and shared where appropriate.
   - Evidence: rclone now carries lane-local WebDAV XML/PROPFIND/PROPPATCH/LOCK,
     gzip, auth-proxy, custom directory-template, OneDrive/provider metadata,
     VFS, response, and reader helpers. These can be legitimate rclone slices,
     but they cannot count as shared support-library progress without
     dependency-specific denominators, malformed/corrupt cases, activation
     gates, and reusable ownership.

9. **High - markerPDF still mixes native progress with external/runtime
   orchestration plans.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:10`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:473`.
   - Requirement at risk: `goal.md:1` and `goal.md:30` forbid counting
     wrappers, bridge calls, or shell-outs to upstream binaries as native
     implementation progress.
   - Evidence: markerPDF has real native PDF stream/filter/text work, but the
     manifest still foregrounds Pandoc/XeLaTeX helper plans, Streamlit,
     FastAPI/Uvicorn, OCR/Tesseract/Ghostscript install planning, Poetry/package
     metadata, pdftext/pypdfium/model stacks, benchmark shell planning, and
     other runtime orchestration. Those may be preflight or oracle evidence, but
     they are not native port progress.

10. **Medium - Readability's full-fixture claim still hides known parity gaps.**
    - Paths: `lanes/readability/lane-status.json:5`,
      `lanes/readability/lane-status.json:10`,
      `lanes/readability/lane-status.json:12`, `porting.html:66`.
    - Requirement at risk: `goal.md:35` and `goal.md:37` require meaningful
      fixture parity and upstream tests as source of truth.
    - Evidence: the dashboard says Readability is `99.0%` with `1984 / 1984`
      mapped, but the current lane status records four remaining copied-fixture
      normalized text mismatches: `firefox-nightly-blog`, `nytimes-5`,
      `wikipedia-2`, and `wikipedia-3`. Focused tests are useful, but the
      full-fixture parity gap should remain visible in the dashboard and
      progress narrative.

11. **Medium - test-time shell-outs must remain oracle tooling, not native
    progress.**
    - Paths: `lanes/gitoxide/tests/FetchResponseTest.php:16`,
      `lanes/gitoxide/tests/FetchResponseTest.php:18`,
      `lanes/gitoxide/tests/FetchV2SessionTest.php:11`,
      `lanes/gitoxide/tests/FetchV2SessionTest.php:13`,
      `lanes/gitoxide/tests/GitUrlTest.php:69`,
      `lanes/gitoxide/tests/GitUrlTest.php:70`,
      `lanes/gitoxide/tests/GitUrlTest.php:102`,
      `lanes/gitoxide/tests/GitUrlTest.php:104`,
      `tools/generate-dashboard.php:197`.
    - Requirement at risk: `goal.md:1`, `goal.md:30`, and `goal.md:39` require
      native ports and reproducible generated artifacts, with bridge code
      counted only as temporary oracle tooling.
    - Evidence: Gitoxide tests invoke `/usr/bin/git` through `proc_open`, and
      the dashboard generator uses `shell_exec` for Git metadata. That can be
      acceptable as explicit fixture/oracle or coordination tooling only; it
      must not become implementation progress.

## Next Intervention

Keep the hard writer/runner/status freeze as the next gate. Stop active
writers/status publishers and PHP shards; take two stable polls of `HEAD`,
tracked status count, total status count, shortstat, exact PHP runner state,
capacity/disk state, and relevant log mtimes; accept or reject one lane-scoped
batch; normalize schema/count fields for that batch; run focused verification
plus `git diff --check`; run exactly one serialized no-argument
`php tools/run-tests.php` from that same frozen snapshot if the exact process
gate remains empty; regenerate `progress.md`, `porting.html`,
`porting-summary.json`, and lane statuses from the accepted commit; then commit
or reject.
