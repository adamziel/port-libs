# Workflow Clean Worktrees - 2026-05-24T22:35:23Z

Scope: clean-worktree workflow coordination for `/home/claude/port-libs`.

## Inputs Inspected

- `scripts/run-tmux-agent.sh`
- `.tmux-team/prompts/worker-template.md`
- existing isolated audit patterns under `audits/isolate-*.md`
- existing ready marker convention under `.tmux-team/tmp/handoff-candidates/`
- current dirty shared-checkout status, without inspecting secrets or lane file
  contents beyond path-level status

The shared checkout was already heavily dirty, including lane implementations,
prompt churn, and scripts owned by other workers. This pass did not edit
`lanes/**`, `scripts/run-team-watchdog.sh`, capacity queue scripts, or
integrator group scripts/prompts.

## Added Workflow

Added `scripts/run-isolated-lane-worker.sh`.

Usage:

```bash
scripts/run-isolated-lane-worker.sh <lane> <micro-slice-label> [session-name]
```

Example supervisor launch:

```bash
scripts/run-isolated-lane-worker.sh pandoc html-br-linebreak port-pandoc-html-br-isolated
```

The launcher:

- creates a detached clean worktree from the current accepted `HEAD` under
  `.tmux-team/worktrees/`;
- renders `.tmux-team/prompts/isolated-worker-template.md` into
  `.tmux-team/tmp/isolated-worker-prompts/`;
- runs Codex fast mode in the isolated worktree with `-C <worktree>`;
- writes the worker log under `.tmux-team/logs/isolated-lane-workers/`;
- exports only `lanes/<lane>/**` changes as a binary patch under
  `.tmux-team/tmp/handoff-candidates/`;
- writes handoff metadata and a `.ready` marker in the same handoff directory
  when Codex exits cleanly and the patch is non-empty.

The launcher does not destructively remove worktrees. The generated metadata
records cleanup guidance:

```bash
git worktree remove <inactive-worktree>
git worktree prune
```

Do not remove a worktree while its worker is active.

## Prompt Contract

Added `.tmux-team/prompts/isolated-worker-template.md`.

The template keeps compatibility with the existing shared-checkout
`worker-template.md` by being additive. It gives isolated workers a narrow
micro-slice contract:

- one upstream behavior cluster;
- lane-only edits under `lanes/<lane>/**`;
- focused tests and syntax checks;
- one WordPress-relevant smoke/example when user-visible behavior is touched;
- lane manifest/status/notes delta when appropriate;
- dependency-closure note for support components;
- no no-argument root harness unless explicitly assigned.

## Verification

Commands run:

```bash
bash -n scripts/run-isolated-lane-worker.sh
```

Result: exit `0`.

```bash
git diff --check -- scripts/run-isolated-lane-worker.sh .tmux-team/prompts/isolated-worker-template.md audits/workflow-clean-worktrees-20260524T223523Z.md
```

Result: exit `0`.

No root `php tools/run-tests.php` was run, per assignment.

## Remaining Follow-Up

- Supervisors still need to choose which future lane slices should use this
  isolated launcher.
- Integrators still need to review each generated patch with
  `git apply --check <patch>` and inspect its log/evidence before applying.
- Old inactive worktrees should be pruned manually after integration or
  rejection; the launcher intentionally does not guess which worktrees are safe
  to remove.
