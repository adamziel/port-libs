# Independent Audit - 2026-05-24T12:44Z

Scope reviewed: `goal.md`, `progress.md`, current `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
current `lanes/*/lane-status.json`, `dependency-backlog.json`,
`audits/integration-status.md`, and recent Git history through
`48a0ac98 Record integration hold status`. I did not edit lane
implementation files, launch agents or tmux sessions, push, read secrets,
inspect process environments, credential stores, provider configs, or auth
files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T12:29-12:44Z
HEAD movement observed: ac09dc6d287f -> 89260857cc71 -> 91385d1b6872 -> 48a0ac98d35b
recent history: 48a0ac98 Record integration hold status; 91385d1b Track support library reading order contract; 89260857 Record integration hold status; ac09dc6d Refresh independent audit status; a28486e1 Record integration hold status
tracked dirty rows: 331 -> 332 -> 327 -> 329
default status rows including untracked: 17709 -> 17717 -> 17713 -> 17716
git diff --shortstat: 331 files changed, 233616 insertions(+), 29284 deletions(-) -> 329 files changed, 235149 insertions(+), 30779 deletions(-)
dashboard snapshot: porting.html and porting-summary.json generated 2026-05-24 12:29:46 UTC from source 89260857cc71; current HEAD is 48a0ac98d35b
dependency backlog: dependency-backlog.json updated 2026-05-24 12:29:10 UTC with 37 rows (1 blocked, 25 candidate, 11 deferred, 0 active)
root run by this audit: not started
```

Required process-gate evidence:

```text
pgrep -af '^php tools/run-tests\.php( |$)' during final audit samples:
2612201 php tools/run-tests.php lanes/quadrable/tests

owner evidence sampled immediately after:
PID 2612201 USER claude PPID 2553886 STAT Rs ETIMES 15 COMMAND php tools/run-tests.php lanes/quadrable/tests

post-edit validation pgrep -af '^php tools/run-tests\.php( |$)':
2629092 php tools/run-tests.php
2630055 php tools/run-tests.php lanes/syncthing/tests/ProgressEmitterSchedulerTest.php ... lanes/syncthing/tests/RequestServerTest.php

owner evidence sampled immediately after:
PID 2629092 USER claude PPID 2629044 STAT R+ ETIMES 57 COMMAND php tools/run-tests.php
PID 2630055 USER claude PPID 2629883 STAT R+ ETIMES 53 COMMAND php tools/run-tests.php lanes/syncthing/tests/ProgressEmitterSchedulerTest.php ... lanes/syncthing/tests/RequestServerTest.php
```

I did not start `php tools/run-tests.php`. The exact gate matched an active
focused Quadrable lane run in final sampling, then an externally started
no-argument root harness plus a focused Syncthing lane run during post-edit
validation. The checkout was not stable enough: `HEAD`, status counts, and
shortstat moved during the run, all lanes still report pending or uncommitted
handoffs, and no coherent lane batch had been accepted from the current dirty
tree.

`jq empty` passed for all 12 lane manifests, all 12 lane-status files,
`porting-summary.json`, and `dependency-backlog.json`.

Current count sample:

```text
lane          manifest total/mapped   lane-status php   dashboard total/mapped/php
difftastic    1077 / 851              3269 / 0          1077 / 851 / 3245
dolt          613 / 613               425 / 0           613 / 613 / 425
esbuild       2567 / 429              429 / 0           2567 / 429 / 429
gitoxide      2877 / 2877             7177 / 0          2877 / 2877 / 7152
libsqlite     1589 / 349              349 / 0           1589 / 349 / 348
LightningCSS  3548 / 2767             4065 / 0          3548 / 2765 / 4065
markerPDF     396 / 347               484 / 0           396 / 347 / 484
pandoc        2276 / 1891             362 / 0           2276 / 1891 / 362
quadrable     55 / 55                 232 / 0           55 / 55 / 232
rclone        1601 / 908              908 / 0           1601 / 906 / 906
readability   1984 / 1984             3545 / 0          1984 / 1984 / 3545
syncthing     658 / 658               7902 / 0          658 / 658 / 7902
```

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md:48`, `audits/integration-status.md:16`,
     `audits/integration-status.md:20`, `audits/integration-status.md:27`,
     `lanes/*/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29` requires small reviewable slices
     with passing tests; `goal.md:48` requires finished agent work to be
     verified, committed, integrated, and cleaned up.
   - Evidence: during this audit `HEAD` moved from `ac09dc6d287f` through
     `89260857cc71` and `91385d1b6872` to `48a0ac98d35b`; tracked dirty rows
     moved `331 -> 332 -> 327 -> 329`; untracked-inclusive status rows moved
     `17709 -> 17717 -> 17713 -> 17716`; shortstat moved to `329 files
     changed, 235149 insertions(+), 30779 deletions(-)`. Every lane status still says
     `pending`, `uncommitted`, or equivalent dirty-batch prose for
     `latestCommit`.

2. **Critical - there is no audit-acceptable root PHP result for the current
   snapshot.**
   - Paths: `tools/run-tests.php`, `audits/latest.md`,
     `lanes/*/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:49` requires periodic repo-wide tests
     and static checks with failures recorded honestly.
   - Evidence: final `pgrep -af '^php tools/run-tests\.php( |$)'` first
     matched focused Quadrable PID `2612201` owned by `claude`; post-edit
     validation then matched externally started no-argument root PID `2629092`
     and focused Syncthing PID `2630055`, both owned by `claude`. No audit root
     run was started because the process gate was occupied, the checkout moved,
     and no lane batch was accepted. Lane blockers still assign aggregate root
     verification to the supervisor/integrator, so focused lane-green evidence
     and external moving-tree root evidence are not accepted root evidence for
     this audit.

3. **High - the dashboard is improved but already stale against `HEAD` and
   current lane metadata.**
   - Paths: `porting.html:34`, `porting.html:35`, `porting-summary.json:2`,
     `porting-summary.json:3`, `porting-summary.json:62`,
     `porting-summary.json:69`, `porting-summary.json:164`,
     `porting-summary.json:169`, `lanes/difftastic/lane-status.json:6`,
     `lanes/gitoxide/lane-status.json:6`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/libsqlite/lane-status.json:6`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:15`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     coordination files and a dashboard showing denominator, mapped tests, PHP
     pass/fail, phase, audit, current work, blocker, and commit.
   - Evidence: `porting.html` and `porting-summary.json` now show 37 support
     rows, but they were generated from `89260857cc71` while current `HEAD` is
     `48a0ac98d35b`. They also lag live lane metadata: Difftastic lane-status
     is `3269` PHP assertions while the dashboard says `3245`; Gitoxide
     lane-status is `7177` while the dashboard says `7152`; libsqlite
     lane-status is `349` while the dashboard says `348`; LightningCSS manifest
     maps `2767` while the dashboard says `2765`; and rclone's
     manifest/status map `908` while the dashboard still shows `906`.

4. **High - support-library coverage is still backlog-only, with zero accepted
   bounded support ports.**
   - Paths: `dependency-backlog.json:3`, `dependency-backlog.json:7`,
     `dependency-backlog.json:25`, `dependency-backlog.json:61`,
     `dependency-backlog.json:81`, `dependency-backlog.json:129`,
     `dependency-backlog.json:195`, `dependency-backlog.json:214`,
     `dependency-backlog.json:274`, `dependency-backlog.json:413`,
     `porting.html:75`, `porting.html:77`.
   - Goal requirement at risk: the 2026-05-24 11:59 UTC support-library
     directive requires a bounded native PHP component, activation gate,
     dependency-specific upstream/spec denominator, mapped fixtures, PHP
     pass/fail evidence, malformed/corrupt cases where relevant, and as much
     upstream/spec-suite evidence as can actually run.
   - Evidence: the 37-row backlog covers Pandoc's important DOC, DOCX/OpenXML,
     PDF handoff/text extraction, EPUB, ODT, templates, citations, math,
     tables, package containers, XML/HTML, Unicode/charset, and
     archive/compression needs, plus shared Git, Quadrable, WebDAV, URL,
     source-map, protobuf, checksum/hash, SQL, package-resolution, target-data,
     and tree-sitter rows. All rows are still `candidate`, `deferred`, or
     `blocked`; there are `0` active support ports and no dependency-specific
     support manifests beyond the 12 lane manifests.

5. **High - markerPDF still mixes native PDF progress with external/runtime
   orchestration plans.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:841`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1074`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1082`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1090`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1095`,
     `lanes/markerpdf/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:1` and `goal.md:30` forbid wrappers
     around JS/Rust/Go/C binaries or shell-outs from counting as the main
     deliverable; bridge/oracle tooling may only be temporary.
   - Evidence: markerPDF has real native PDF text/outline/page-label/filter
     slices, but its mapped semantics still include Pandoc/XeLaTeX proof-PDF
     command planning, chunk-convert shell lifecycle planning, Streamlit app
     command planning, top-level multiprocessing/model handoff, OCRMyPDF,
     Tesseract, Ghostscript install/readiness planning, Poetry/model-runtime
     metadata, and model loader/Texify/Nougat orchestration. Those are
     blockers, caller-supplied boundaries, or oracle metadata, not accepted
     native port progress.

6. **Medium - `progress.md` still lags current lane handoffs outside the audit
   snapshot note.**
   - Paths: `progress.md:142`, `progress.md:150`, `progress.md:151`,
     `progress.md:153`, `progress.md:157`, `lanes/*/lane-status.json:10`,
     `lanes/*/lane-status.json:11`.
   - Goal requirement at risk: `goal.md:44` requires current owner/session,
     next task per lane, blockers, and percentage estimates in `progress.md`.
   - Evidence: the Active Lanes table still names older work such as
     markerPDF benchmark file-inventory planning, libsqlite WAL FULL-sync,
     Pandoc NativeWriter figure/citation, rclone VFS Statfs/usage, and esbuild
     automatic JSX key/spread fallback while current lane-status files describe
     newer markerPDF PDF contents arrays, libsqlite JSON operator RHS,
     Pandoc LaTeX quote/hr, rclone accounting/WebDAV follow-up, Gitoxide
     gix-index state-write extension policy, and other later handoffs.

7. **Medium - near-complete percentages continue to overstate accepted parity.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/*/lane-status.json:4`, `lanes/*/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:35` through `goal.md:40` say passing
     tests are not enough, upstream tests are the source of truth where
     possible, and hard gaps must be recorded as blockers or future slices.
   - Evidence: most lanes report `98%` or `99%` while root aggregate
     verification remains pending, several full upstream runners remain
     unexecuted or bounded, and current handoffs are still pending or
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
