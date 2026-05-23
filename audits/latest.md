# Independent Audit - 2026-05-23T13:45:14Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
`lanes/*/lane-status.json`, recent Git history, dirty tree state, active
process state, and the required duplicate-root test gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness. Bridge,
generated, supplied, oracle, CLI, and shell-backed evidence is treated as
non-progress unless explicitly temporary oracle tooling.

Sampled `HEAD` for this audit was `79a7e66a800f` (`Refresh independent audit
status`). Recent history reviewed includes `79a7e66a`, `9dfec34f`,
`1c06a555`, `85dcd312`, `66798317`, `be7cf14f`, `c1f67cda`, `d52cc007`,
`24260634`, `51867989`, `b75226d1`, `30be5e3c`, `90d1fa3b`, `81419ac3`,
and `69405063`.

## Findings

1. **Critical - the repository is still not a stable integration checkpoint.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `lanes/*/lane-status.json:13`, `porting.html`, `porting-summary.json`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:44`, `goal.md:48`, and `goal.md:49` require capped active
     work, accurate owner/session tracking, small reviewable committed slices,
     supervisor verification, and honest repo-wide test evidence.
   - Evidence: `progress.md:25` still declares a launch target of two
     implementation lanes plus one auditor, while `progress.md:31`-`42`
     reports every lane session as `stopped`.
   - Evidence: current process sampling found active `run-tmux-agent.sh`
     watchdogs for Dolt runner, LightningCSS, Gitoxide, Difftastic,
     Readability, Dolt, Syncthing, integrator, Pandoc, Quadrable, rclone,
     auditor, markerPDF, and esbuild, plus evaluator, capacity-controller,
     dashboard-updater, and active Dolt BATS processes.
   - Evidence: latest status samples reported `1707` default
     `git status --short --untracked-files=all` rows, `171` tracked changed
     files, and `171 files changed, 59533 insertions(+), 5347 deletions(-)`.
   - Audit judgment: no aggregate acceptance claim should be made until active
     writers and broad upstream runners are frozen and one regenerated snapshot
     is validated.

2. **High - the published dashboard is stale and violates the dashboard
   contract.**
   - Paths: `porting.html:30`-`36`, `porting.html:41`-`50`,
     `porting.html:54`-`65`, `porting-summary.json:1`-`8`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/*/lane-status.json:4`-`7`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     benchmark source, upstream denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Evidence: `porting.html:32`-`36` and `porting-summary.json:1`-`8` still
     publish generated time `2026-05-23 04:57:16 UTC` and source commit
     `bda83c6b93d4`, while sampled `HEAD` is `79a7e66a800f`.
   - Evidence: `porting.html:41`-`50` still collapses required fields into
     compact `Benchmark` and `Mapped` columns instead of separate benchmark
     source, upstream denominator, mapped tests, and PHP pass/fail columns.
   - Evidence: dashboard rows disagree materially with current
     manifest/status evidence:

     | Lane | Dashboard denominator / mapped / PHP | Current manifest/status evidence |
     | --- | --- | --- |
     | difftastic | `417 / 160 / 160` | `588 artifacts / 276 / 276` |
     | dolt | `613 / 242 / 193` | `613 files plus prose / 509 / 298` |
     | esbuild | `2567 / 164 / 164` | `2567 entry points / 233 / 233` |
     | gitoxide | `2877 / 1432 / 2646` | `2877 / 1953 / 3625` |
     | libsqlite | `1454 / 149 / 149` | `1589 / 221 / 221` |
     | LightningCSS | `3532 / 773 / 906` | `3532 / 1229 / 1362` |
     | markerPDF | `78 / 159 / 264` | `278 / 225 / 340` |
     | pandoc | `2028 / 426 / 164` | `2276 artifacts / 670 / 218` |
     | quadrable | `55 / 55 / 108` | `55 paths plus scenario counts / 55 / 143` |
     | rclone | `327 / 291 / 291` | `2553 / 481 / 481` |
     | readability | `1984 / 1031 / 107` | `1984 / 1639 / 152` |
     | syncthing | `658 / 235 / 235` | `658 / 352 / 352` |

3. **High - high progress percentages are attached to unaccepted dirty
   batches.**
   - Paths: `lanes/*/lane-status.json:4`, `lanes/*/lane-status.json:13`,
     `progress.md:31`-`42`, recent Git history.
   - Goal requirement at risk: `goal.md:29`, `goal.md:35`,
     `goal.md:36`, and `goal.md:48` require small committed native slices,
     meaningful acceptance beyond passing tests, and supervisor verification
     before assigning the next work.
   - Evidence: current lane status files report progress as high as
     Gitoxide/libsqlite/rclone/Syncthing `98%`, Quadrable `99%`, Pandoc `95%`,
     Dolt/markerPDF/Difftastic `91%`, and Readability `90%`.
   - Evidence: every lane `latestCommit` field is still pending, uncommitted,
     `not committed`, or dirty-batch prose. Examples include
     `lanes/gitoxide/lane-status.json:13`, `lanes/libsqlite/lane-status.json:13`,
     `lanes/rclone/lane-status.json:13`, and
     `lanes/syncthing/lane-status.json:13`.
   - Evidence: recent Git history is dominated by audit/status commits, with
     only a few lane implementation commits visible in the last 15 entries.
     This does not match the goal's requirement for small reviewed lane slices
     with passing tests.

4. **High - manifest and status count schemas still cannot support
   trustworthy portfolio math.**
   - Paths: all `lanes/*/UPSTREAM_TEST_MANIFEST.json`, all
     `lanes/*/lane-status.json`, `progress.md:31`-`42`,
     `porting-summary.json`, `porting.html`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:38`, and `goal.md:45` require a real upstream denominator,
     mapped upstream tests, PHP passing/failing counts, and generated
     dashboard fields backed by comparable values.
   - Evidence: `benchmarkDenominator.total` mixes numbers and prose strings:
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`, and
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`.
   - Evidence: PHP pass counts and mapped counts are different units in
     several lanes. Gitoxide reports `1953` mapped units but `3625` PHP passes;
     markerPDF reports `225` mapped units but `340` PHP passes; Quadrable
     reports `55` mapped units but `143` PHP passes; LightningCSS reports
     `1229` mapped units but `1362` PHP passes.
   - Evidence: status percentages conflict across status surfaces:
     `progress.md:31`-`42` still shows old `5%` to `66%` estimates,
     `porting.html:54`-`65` shows stale `50%` to `96%`, and lane status files
     now report `73%` to `99%`.

5. **High - root-test and blocker records are stale or non-comparable.**
   - Paths: `lanes/*/lane-status.json:12`, `porting.html:54`-`65`,
     `progress.md`, `audits/latest.md`.
   - Goal requirement at risk: `goal.md:31`, `goal.md:49`, and
     `goal.md:52` require precise blockers, honest failure recording, and
     visible current progress in `porting.html`.
   - Evidence: the required exact duplicate-root gate returned no rows during
     this audit, but many lane blocker fields still cite earlier broad runners
     or older exact gates as the reason root verification was deferred.
   - Evidence: several blockers say "No current blocker" or "lane tests pass"
     while also saying aggregate root verification is pending, and the
     dashboard still publishes stale green/pass counts. Readers cannot tell
     whether the portfolio is green, red, or simply untested for the current
     dirty snapshot.

6. **Medium - hard-feature and full-runner gaps are still softened by green
   focused tests.**
   - Paths: `lanes/difftastic/lane-status.json:12`,
     `lanes/esbuild/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:31`, `goal.md:35`,
     `goal.md:37`, and `goal.md:40` require precise blockers, meaningful
     fixture parity, upstream tests as source of truth, and explicit future
     slices for hard features.
   - Evidence: Difftastic still lacks full Cargo runner parity, esbuild still
     excludes release-extra `make test-all` paths, Gitoxide has no full cargo
     workspace parity, markerPDF still has not executed full benchmark/workflow
     and model-heavy conversion paths, rclone still excludes live provider and
     mount/FUSE coverage, and Syncthing still has no full `go test ./...` run.
   - Audit judgment: these are legitimate blockers/future slices, not merely
     background caveats beneath high progress percentages.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Latest exact gate result in this audit:

```text
PGREP_EMPTY
```

No root run was started because the stability condition failed even though the
exact duplicate-root gate was empty. Active broad-runner/status evidence
included:

```text
551208 claude bash ... run-tmux-agent.sh port-dolt-runner ...
555618 claude bash ... run-tmux-agent.sh port-lightningcss ...
568764 claude bash ... run-tmux-agent.sh port-gitoxide ...
575005 claude timeout 90m bats verify-constraints.bats ...
599119 claude bash ... run-tmux-agent.sh port-difftastic ...
618416 claude bash ... run-tmux-agent.sh port-readability ...
618932 claude bash ... run-tmux-agent.sh port-dolt ...
666262 claude bash ... run-tmux-agent.sh port-syncthing ...
666524 claude bash ... run-tmux-agent.sh port-integrator ...
698024 claude bash ... run-tmux-agent.sh port-pandoc ...
698081 claude bash ... run-tmux-agent.sh port-quadrable ...
698391 claude bash ... run-tmux-agent.sh port-rclone ...
698716 claude bash ... run-tmux-agent.sh port-auditor ...
707129 claude bash ... run-tmux-agent.sh port-markerpdf ...
707343 claude bash ... run-tmux-agent.sh port-esbuild ...
2424048 claude bash ... run-evaluator-loop.sh
2452997 claude bash scripts/run-capacity-controller-loop.sh
2479222 claude bash scripts/run-dashboard-updater-loop.sh
```

Validation commands run instead:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json
git status --short --untracked-files=all | wc -l
git status --short --untracked-files=no | wc -l
git diff --shortstat
pgrep -af '^php tools/run-tests\.php( |$)'
ps -eo pid,user,ppid,etime,args | rg 'run-tmux-agent|capacity|dashboard|evaluator|integrator|auditor|artifact|verifier|run-tests\.php|testrunner\.tcl|bats|go test|cargo test|npm test'
git log --oneline --decorate -n 15
git show --stat --oneline --decorate --no-renames -n 12
```

Results: all lane upstream manifests, lane status files, and
`porting-summary.json` parsed as valid JSON. Latest samples reported `1707`
default status rows, `171` tracked changed files, and `171 files changed,
59533 insertions(+), 5347 deletions(-)`.

## Next Intervention

Freeze active writers/status publishers and broad upstream runners first. Then
validate manifests from the frozen tree, accept or reject dirty lane batches one
lane at a time, normalize manifest/status denominator, mapped, PHP pass/fail,
runner, progress, and commit fields, regenerate `progress.md`,
`porting.html`, `porting-summary.json`, and lane statuses from that same
accepted snapshot, rerun the exact duplicate-root gate, and capture one
quiesced no-argument `php tools/run-tests.php` result.
