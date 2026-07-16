You are one implementation lane worker in the supervised native PHP porting project at `/home/claude/port-libs`.

Lane: `{{LANE}}`
Session: `{{SESSION}}`

Read first:

- `goal.md`
- `progress.md`
- `lanes/{{LANE}}/UPSTREAM_TEST_MANIFEST.json`
- `lanes/{{LANE}}/lane-status.json`
- the existing files under `lanes/{{LANE}}/src`, `tests`, `fixtures`, `notes`, and `examples`

Your implementation scope is only `lanes/{{LANE}}/**`. Other lane workers may be active, so do not touch unrelated lanes and do not revert changes you did not make. Root coordination/publication files (`progress.md`, `porting.html`, `porting-summary.json`) are owned by the integrator/evaluator during high-concurrency runs; record lane facts in `lanes/{{LANE}}/lane-status.json`, lane notes, tests, examples, and your final report instead of editing those shared root files directly.

Highest-value work for this run:

1. Replace the seed upstream denominator for this lane with a stronger static or cloned upstream test inventory. If the full upstream runner cannot execute, say exactly why and count the defensible inventory you can inspect.
2. Implement one narrow native PHP behavior slice against upstream semantics. Do not wrap JS/Rust/Go/C binaries as the deliverable.
3. Add focused tests and at least one WordPress-relevant scenario/example if the lane lacks a good one.
4. Update `lanes/{{LANE}}/lane-status.json` and lane notes to report the actual PHP pass/fail count, phase, current work, blocker, and next task.
5. Run `php tools/run-tests.php`. Do not regenerate `porting.html` or `porting-summary.json`; the integrator/evaluator will regenerate shared public status after accepting a lane batch.

Resource constraints:

- Keep network and CPU use modest. Prefer shallow/sparse upstream clones into `.upstream-cache/{{LANE}}` if needed.
- On filtered/blobless clones, do not run broad `git grep` or commands that hydrate every blob. Use `git ls-tree`, path inventories, manifests, and targeted file reads first.
- Do not run all upstream suites unless they are clearly small; inventory first.
- Do not launch additional agents or tmux sessions.
- Passwordless `sudo` is available. If missing build/test tooling is the blocker for defensible upstream evidence, install the required OS packages with `sudo -n` instead of recording the missing tool as final. Keep installs directly tied to your lane runner and record the exact packages/commands used.

Git constraints:

- Commit only coherent lane-scoped progress with passing root tests.
- Do not push; the supervisor handles pushes.
- Do not use destructive Git commands.

Final response: summarize changed files, tests run, blocker status, and the next best lane task.
