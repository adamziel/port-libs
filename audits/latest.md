# Independent Audit - 2026-05-23T13:29:43Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
`lanes/*/lane-status.json`, recent Git history, dirty tree state, active
process state, and the required duplicate-root test gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness. Bridge,
generated, supplied, oracle, CLI, and shell-backed evidence is treated as
non-progress unless explicitly temporary oracle tooling.

Sampled `HEAD` for this audit was `85dcd3122c99` (`Refresh independent audit
status`). Recent history reviewed includes `85dcd312`, `66798317`,
`be7cf14f`, `c1f67cda`, `d52cc007`, `24260634`, `51867989`,
`b75226d1`, `30be5e3c`, `90d1fa3b`, `81419ac3`, `69405063`,
`0f1444c1`, `efa4e0c2`, and `f8bd46e4`.

## Findings

1. **Critical - the repository is still not a stable integration checkpoint.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `lanes/*/lane-status.json:13`, `porting.html`, `porting-summary.json`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:44`, `goal.md:48`, and `goal.md:49` require capped workers,
     current owner/session tracking, small reviewable committed slices,
     supervisor verification, and honest repo-wide testing.
   - Evidence: `progress.md:25` still says the launch target is two
     implementation lanes plus one auditor, while `progress.md:31`-`42`
     still reports every lane session as `stopped`.
   - Evidence: active process sampling found primary lane/watchdog work for
     Dolt, esbuild, libsqlite, Gitoxide, LightningCSS, markerPDF, rclone,
     Pandoc, Readability, Difftastic, Quadrable, Syncthing, the integrator,
     and the auditor, plus evaluator, capacity-controller, dashboard-updater,
     and SQLite `testrunner.tcl --jobs 8` processes.
   - Evidence: the required duplicate-root gate matched active PHP harness
     PID `504288` (`php tools/run-tests.php lanes/syncthing/tests`), owned by
     `claude`; no root harness was started.
   - Evidence: the dirty tree widened again: latest samples reported `1665`
     default `git status --short --untracked-files=all` rows, `171` tracked
     changed files, and `171 files changed, 58720 insertions(+), 6738
     deletions(-)`.
   - Audit judgment: focused lane green results and dashboard percentages are
     not acceptance evidence until active writers are frozen and one
     regenerated snapshot is validated.

2. **High - the published dashboard is stale and fails the required dashboard
   contract.**
   - Paths: `porting.html:30`-`36`, `porting.html:41`-`50`,
     `porting.html:54`-`65`, `porting-summary.json:2`-`4`,
     `porting-summary.json:12`-`212`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     benchmark source, upstream denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Evidence: `porting.html:32`-`36` and `porting-summary.json:2`-`4` still
     publish generated time `2026-05-23 04:57:16 UTC` and source commit
     `bda83c6b93d4`, while sampled `HEAD` is `85dcd3122c99`.
   - Evidence: `porting.html:41`-`50` still collapses required fields into
     compact `Benchmark` and `Mapped` cells rather than separate benchmark
     source, upstream denominator, mapped tests, and PHP pass/fail columns.
   - Evidence: dashboard rows disagree with current manifest/status evidence:

     | Lane | Dashboard denominator / mapped / PHP | Current manifest/status evidence |
     | --- | --- | --- |
     | difftastic | `417 / 160 / 160` | `588 / 271 / 271` |
     | dolt | `613 / 242 / 193` | `613 / 505 / 297` |
     | esbuild | `2567 / 164 / 164` | `2567 / 232 / 232` |
     | gitoxide | `2877 / 1432 / 2646` | `2877 / 1943 / 3586` |
     | libsqlite | `1454 / 149 / 149` | `1589 / 219 / 219` |
     | LightningCSS | `3532 / 773 / 906` | `3532 / 1216 / 1349` |
     | markerPDF | `78 / 159 / 264` | `277 / 224 / 335` |
     | pandoc | `2028 / 426 / 164` | `2276 / 665 / 216` |
     | quadrable | `55 / 55 / 108` | `55 / 55 / 142` |
     | rclone | `327 / 291 / 291` | `2553 / 475 / 475` |
     | readability | `1984 / 1031 / 107` | `1984 / 1624 / 151` |
     | syncthing | `658 / 235 / 235` | `658 / 349 / 349` |

3. **High - manifest, status, and progress schemas still cannot produce
   trustworthy portfolio math.**
   - Paths: all `lanes/*/UPSTREAM_TEST_MANIFEST.json`, all
     `lanes/*/lane-status.json`, `progress.md:31`-`42`,
     `porting-summary.json`, `porting.html`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:38`, and `goal.md:45` require real upstream denominators,
     mapped upstream tests, PHP passing/failing counts, and generated
     dashboard fields backed by those values.
   - Evidence: denominator units remain mixed. Some manifests expose numeric
     totals (`gitoxide`, `libsqlite`, `LightningCSS`, `markerPDF`, `rclone`,
     `readability`, `syncthing`), while others expose prose totals
     (`difftastic`, `dolt`, `esbuild`, `pandoc`, `quadrable`).
   - Evidence: mapped-test units and PHP-pass units are not normalized:
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`-`15` maps `1624`
     upstream units while `lanes/readability/lane-status.json:6` reports
     `151` PHP passes; `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`-`15`
     maps `1943` while `lanes/gitoxide/lane-status.json:6` reports `3586`;
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:15` maps `505`, but
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2030` says `294` PHP behavior
     tests and `lanes/dolt/lane-status.json:6` says `297` PHP passes.
   - Evidence: some files contradict themselves. `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`-`15`
     now reports `277 / 224`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:505`
     reports `332` PHP behavior tests, `lanes/markerpdf/lane-status.json:6`
     reports `335` PHP passes, and the manifest warning at
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:498` still describes
     `274` total and `220` mapped. `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:14`-`15`
     reports `1216` mapped, but the warning at
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:1546` still says `1204`
     focused checks and `1337` assertions while status reports `1349`
     passes.
   - Evidence: progress estimates conflict across files. `progress.md:31`-`42`
     shows `5%` to `66%`, `porting.html:54`-`65` shows `50%` to `96%`, and
     current lane statuses show `72%` to `99%`.

4. **High - high progress claims are attached to unaccepted dirty batches.**
   - Paths: `lanes/*/lane-status.json:4`, `lanes/*/lane-status.json:13`,
     `progress.md:31`-`42`, recent Git history.
   - Goal requirement at risk: `goal.md:29`, `goal.md:30`,
     `goal.md:36`, and `goal.md:48` require small committed native slices,
     no bridge/generated progress credit, and supervisor acceptance before
     assigning the next work.
   - Evidence: every current lane status reports high progress (`72%` to
     `99%`), but latest commit fields are mostly `pending`, `uncommitted`,
     `not committed`, or prose dirty-batch descriptions rather than accepted
     commit IDs. Examples include `lanes/dolt/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`, `lanes/markerpdf/lane-status.json:13`,
     `lanes/quadrable/lane-status.json:13`, and `lanes/rclone/lane-status.json:13`.
   - Evidence: recent history is dominated by audit/status commits around a
     few lane commits, while the working tree still has `171` tracked changed
     files plus extensive untracked artifacts.

5. **Medium - hard-feature blockers are still buried behind green focused
   tests and optimistic blocker text.**
   - Paths: `porting.html:57`, `porting.html:60`, `porting.html:63`-`65`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:21`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:498`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:17`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:20`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:827`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:544`.
   - Goal requirement at risk: `goal.md:31`, `goal.md:35`,
     `goal.md:37`, and `goal.md:40` require precise blockers, meaningful
     fixture parity, upstream tests as source of truth, and explicit future
     slices for hard features.
   - Evidence: markerPDF status says no PHP blocker, but the manifest warning
     still admits no live upstream conversion parity and stops at supplied
     model/output/PDF boundaries that need Poetry, Torch, Surya, Texify,
     OCRmyPDF/Tesseract, Ghostscript, Pandoc/XeLaTeX, and model downloads.
   - Evidence: rclone status says no lane-local blocker, but the manifest
     warning is still a bounded static/provider-free inventory with live
     provider integration, mount/FUSE, OAuth remotes, Docker-backed serve
     coverage, and provider TestIntegration excluded.
   - Evidence: Gitoxide, Pandoc, Quadrable, and Readability similarly retain
     major unexecuted upstream or expensive parity paths while dashboard/status
     summaries emphasize green focused tests.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Latest gate result in this audit:

```text
504288 php tools/run-tests.php lanes/syncthing/tests
```

Owner evidence:

```text
PID     USER   PPID   ELAPSED STAT COMMAND
504288  claude 494699 00:15   Rs   php tools/run-tests.php lanes/syncthing/tests
```

No root run was started because the required gate was not empty and the
stability condition also failed. Active broad-runner/status evidence included:

```text
230698 bash ... run-tmux-agent.sh port-dolt-runner ...
368419 bash ... run-tmux-agent.sh port-esbuild ...
381396 bash ... run-tmux-agent.sh port-libsqlite ...
433149 bash ... run-tmux-agent.sh port-gitoxide ...
450498 bash ... run-tmux-agent.sh port-lightningcss ...
450681 bash ... run-tmux-agent.sh port-markerpdf ...
451959 bash ... run-tmux-agent.sh port-rclone ...
477082 bash ... run-tmux-agent.sh port-pandoc ...
482011 bash ... run-tmux-agent.sh port-readability ...
482195 bash ... run-tmux-agent.sh port-difftastic ...
494592 bash ... run-tmux-agent.sh port-quadrable ...
494637 bash ... run-tmux-agent.sh port-syncthing ...
495047 bash ... run-tmux-agent.sh port-integrator ...
499137 bash ... run-tmux-agent.sh port-auditor ...
500039 bash ... run-tmux-agent.sh port-dolt ...
2424048 bash ... run-evaluator-loop.sh
2452997 bash ... run-capacity-controller-loop.sh
2479222 bash ... run-dashboard-updater-loop.sh
3854382 timeout 3600 ./testfixture ../src/test/testrunner.tcl --jobs 8 --stop-on-error all
3854383 ./testfixture ../src/test/testrunner.tcl --jobs 8 --stop-on-error all
```

Validation commands run instead:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json
git status --short --untracked-files=all | wc -l
git status --short --untracked-files=no | wc -l
git diff --shortstat
pgrep -af '^php tools/run-tests\.php( |$)'
pgrep -af 'run-tmux-agent|capacity|dashboard|evaluator|integrator|auditor|artifact|verifier|run-tests\.php|testrunner\.tcl|bats|go test|cargo test|npm test'
git log --oneline --decorate --stat --no-renames -n 25
git show --stat --oneline --decorate --no-renames HEAD
```

Results: all lane upstream manifests, lane status files, and
`porting-summary.json` parsed as valid JSON. Latest samples reported `1665`
default status rows, `171` tracked changed files, and `171 files changed,
58720 insertions(+), 6738 deletions(-)`.

## Next Intervention

Freeze active writers/status publishers and duplicate root/focused PHP loops
first. Then validate manifests from the frozen tree, accept or reject dirty
lane batches one lane at a time, normalize manifest/status denominator,
mapped, PHP pass/fail, runner, progress, and commit fields, regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from
that same accepted snapshot, rerun the exact duplicate-root gate, and capture
one quiesced no-argument `php tools/run-tests.php` result.
