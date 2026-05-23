# Independent Audit - 2026-05-23T13:39:14Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
`lanes/*/lane-status.json`, recent Git history, dirty tree state, active
process state, and the required duplicate-root test gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness. Bridge,
generated, supplied, oracle, CLI, and shell-backed evidence is treated as
non-progress unless explicitly temporary oracle tooling.

Sampled `HEAD` for this audit was `9dfec34fd7e4` (`Refresh independent audit
status`). Recent history reviewed includes `9dfec34f`, `1c06a555`,
`85dcd312`, `66798317`, `be7cf14f`, `c1f67cda`, `d52cc007`, `24260634`,
`51867989`, `b75226d1`, `30be5e3c`, `90d1fa3b`, `81419ac3`, `69405063`,
and `0f1444c1`.

## Findings

1. **Critical - the repository is still not a stable integration checkpoint.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `lanes/*/lane-status.json:10`-`13`, `porting.html`,
     `porting-summary.json`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:44`, `goal.md:48`, and `goal.md:49` require capped workers,
     current owner/session tracking, small reviewable committed slices,
     supervisor verification, and honest repo-wide testing.
   - Evidence: `progress.md:25` still says the current launch target is two
     implementation lanes plus one auditor, and `progress.md:31`-`42` still
     reports every lane session as `stopped`.
   - Evidence: active process sampling found lane/watchdog work for
     Quadrable, libsqlite, esbuild, Syncthing, Dolt runner, rclone,
     LightningCSS, markerPDF, Pandoc, Gitoxide, Difftastic, auditor,
     integrator, Readability, and Dolt, plus evaluator, capacity-controller,
     dashboard-updater, and active Dolt BATS processes.
   - Evidence: latest status samples reported `1696` default
     `git status --short --untracked-files=all` rows, `171` tracked changed
     files, and `171 files changed, 58952 insertions(+), 5362 deletions(-)`.
   - Audit judgment: focused lane greens and high lane percentages are not
     acceptance evidence until active writers are frozen and one regenerated
     snapshot is validated.

2. **High - the published dashboard is stale and fails the required dashboard
   contract.**
   - Paths: `porting.html:30`-`36`, `porting.html:41`-`50`,
     `porting.html:54`-`65`, `porting-summary.json:2`-`8`,
     `porting-summary.json:15`-`18`, `lanes/*/UPSTREAM_TEST_MANIFEST.json:13`-`15`,
     `lanes/*/lane-status.json:4`-`7`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     benchmark source, upstream denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Evidence: `porting.html:32`-`36` and `porting-summary.json:2`-`8` still
     publish generated time `2026-05-23 04:57:16 UTC` and source commit
     `bda83c6b93d4`, while sampled `HEAD` is `9dfec34fd7e4`.
   - Evidence: `porting.html:41`-`50` still collapses required fields into
     compact `Benchmark` and `Mapped` cells instead of separate benchmark
     source, upstream denominator, mapped tests, and PHP pass/fail columns.
   - Evidence: dashboard rows disagree with current manifest/status evidence:

     | Lane | Dashboard denominator / mapped / PHP | Current manifest/status evidence |
     | --- | --- | --- |
     | difftastic | `417 / 160 / 160` | `588 / 274 / 274` |
     | dolt | `613 / 242 / 193` | `613 / 509 / 298` |
     | esbuild | `2567 / 164 / 164` | `2567 / 233 / 233` |
     | gitoxide | `2877 / 1432 / 2646` | `2877 / 1953 / 3625` |
     | libsqlite | `1454 / 149 / 149` | `1589 / 221 / 219` |
     | LightningCSS | `3532 / 773 / 906` | `3532 / 1224 / 1357` |
     | markerPDF | `78 / 159 / 264` | `278 / 225 / 337` |
     | pandoc | `2028 / 426 / 164` | `2276 / 668 / 218` |
     | quadrable | `55 / 55 / 108` | `55 / 55 / 143` |
     | rclone | `327 / 291 / 291` | `2553 / 481 / 478` |
     | readability | `1984 / 1031 / 107` | `1984 / 1639 / 152` |
     | syncthing | `658 / 235 / 235` | `658 / 352 / 352` |

3. **High - manifest, status, and progress schemas still cannot produce
   trustworthy portfolio math.**
   - Paths: all `lanes/*/UPSTREAM_TEST_MANIFEST.json`, all
     `lanes/*/lane-status.json`, `progress.md:31`-`42`,
     `porting-summary.json`, `porting.html`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:38`, and `goal.md:45` require real upstream denominators,
     mapped upstream tests, PHP passing/failing counts, and generated
     dashboard fields backed by those values.
   - Evidence: denominator units remain mixed. Several manifests put prose in
     `benchmarkDenominator.total` while others use numbers, so portfolio
     coverage is a blend of file counts, test entry points, behavior
     artifacts, fixtures, and text descriptions.
   - Evidence: mapped-test and PHP-pass units are not normalized:
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json:16` reports `221` mapped
     while `lanes/libsqlite/lane-status.json:6` reports `219` PHP passes;
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:15` reports `481` mapped while
     `lanes/rclone/lane-status.json:6` reports `478`; Gitoxide maps `1953`
     denominator units while `lanes/gitoxide/lane-status.json:6` reports
     `3625` PHP passes.
   - Evidence: values changed while this audit was reading them. For example,
     Syncthing moved from `350` to `352` PHP passes during sampling, and
     libsqlite manifest/status values disagreed across adjacent reads. That is
     not a valid basis for accepting an aggregate snapshot.
   - Evidence: estimates conflict across files. `progress.md:31`-`42` still
     shows `5%` to `66%`, stale `porting.html:54`-`65` shows `50%` to `96%`,
     and current lane status files report `73%` to `99%`.

4. **High - root-test records and blocker text are stale or non-comparable.**
   - Paths: `lanes/libsqlite/lane-status.json:10`-`13`,
     `lanes/syncthing/lane-status.json:10`-`13`,
     `lanes/rclone/lane-status.json:10`-`13`, `progress.md`, `porting.html`.
   - Goal requirement at risk: `goal.md:31`, `goal.md:48`, and
     `goal.md:49` require precise blockers, supervisor verification, and
     honest failure recording.
   - Evidence: current exact duplicate-root sampling returned no active
     no-argument root harness, but some lane status records still cite older
     exact root PID `504969` and old SQLite `testrunner.tcl` PIDs as the reason
     root verification was deferred.
   - Evidence: other lane statuses say "no current blocker" or "no
     lane-local PHP blocker" while also leaving aggregate root verification
     pending. The dashboard and `progress.md` do not expose one current root
     result, so readers cannot tell whether the portfolio is green, red, or
     simply untested from the present snapshot.

5. **High - high progress claims are attached to unaccepted dirty batches.**
   - Paths: `lanes/*/lane-status.json:13`, `progress.md:31`-`42`,
     recent Git history.
   - Goal requirement at risk: `goal.md:29`, `goal.md:30`,
     `goal.md:36`, and `goal.md:48` require small committed native slices,
     no bridge/generated progress credit, and supervisor acceptance before
     assigning the next work.
   - Evidence: current lane statuses report high progress (`90%`-`99%` for
     most lanes), but latest commit fields are mostly `pending`,
     `uncommitted`, `not committed`, or dirty-batch prose.
   - Evidence: recent Git history is dominated by audit/status commits around
     a few lane commits, while the working tree still has `171` tracked
     changed files and extensive untracked artifacts.

6. **Medium - hard-feature blockers are still buried behind green focused
   tests and optimistic blocker text.**
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
     excludes release-extra `make test-all` paths, rclone still excludes live
     provider, mount/FUSE, Docker-backed serve, and provider integration
     coverage, and Syncthing still has no full `go test ./...` run. These
     should remain visible blockers even when focused PHP tests are green.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Latest gate result in this audit:

```text
PGREP_EMPTY
```

No root run was started because the stability condition failed even though the
exact duplicate-root gate was empty. Active broad-runner/status evidence
included:

```text
494592 bash ... run-tmux-agent.sh port-quadrable ...
529331 bash ... run-tmux-agent.sh port-libsqlite ...
530110 bash ... run-tmux-agent.sh port-esbuild ...
551025 bash ... run-tmux-agent.sh port-syncthing ...
551208 bash ... run-tmux-agent.sh port-dolt-runner ...
553056 bash ... run-tmux-agent.sh port-rclone ...
555618 bash ... run-tmux-agent.sh port-lightningcss ...
555662 bash ... run-tmux-agent.sh port-markerpdf ...
555916 bash ... run-tmux-agent.sh port-pandoc ...
568764 bash ... run-tmux-agent.sh port-gitoxide ...
575005 timeout 90m bats verify-constraints.bats ...
599119 bash ... run-tmux-agent.sh port-difftastic ...
599637 bash ... run-tmux-agent.sh port-auditor ...
599757 bash ... run-tmux-agent.sh port-integrator ...
618416 bash ... run-tmux-agent.sh port-readability ...
618932 bash ... run-tmux-agent.sh port-dolt ...
2424048 bash ... run-evaluator-loop.sh
2452997 bash scripts/run-capacity-controller-loop.sh
2479222 bash scripts/run-dashboard-updater-loop.sh
```

Validation commands run instead:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json
git status --short --untracked-files=all | wc -l
git status --short --untracked-files=no | wc -l
git diff --shortstat
pgrep -af '^php tools/run-tests\.php( |$)'
pgrep -af 'run-tmux-agent|capacity|dashboard|evaluator|integrator|auditor|artifact|verifier|run-tests\.php|testrunner\.tcl|bats|go test|cargo test|npm test'
git log --oneline --decorate -n 15
git show --stat --oneline --decorate --no-renames -n 8
```

Results: all lane upstream manifests, lane status files, and
`porting-summary.json` parsed as valid JSON. Latest samples reported `1696`
default status rows, `171` tracked changed files, and `171 files changed,
58952 insertions(+), 5362 deletions(-)`.

## Next Intervention

Freeze active writers/status publishers and broad upstream runners first. Then
validate manifests from the frozen tree, accept or reject dirty lane batches one
lane at a time, normalize manifest/status denominator, mapped, PHP pass/fail,
runner, progress, and commit fields, regenerate `progress.md`, `porting.html`,
`porting-summary.json`, and lane statuses from that same accepted snapshot,
rerun the exact duplicate-root gate, and capture one quiesced no-argument
`php tools/run-tests.php` result.
