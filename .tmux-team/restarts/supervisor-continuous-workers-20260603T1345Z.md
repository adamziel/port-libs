# Supervisor Goal: Continuous markerPDF/Pandoc worker progress

## Outcome
- Keep the active isolated worker pool replenished after completed waves.
- Target 8 markerPDF workers and 3 Pandoc workers from `origin/main`.
- Leave integration, duplicate rejection, status updates, and dashboard publication under supervised serial review.

## Intensity
- Level: high
- Starting workers: 11
- Scaling rule: refill only when live lane worker counts drop below target and resource checks pass.

## Non-Goals
- Do not merge handoffs automatically.
- Do not touch unrelated dirty root checkout changes.
- Do not bypass focused/full verification gates before publishing integrated work.

## Ground Truth
- Current clean integration base: `origin/main`.
- Worker launchers:
  - `scripts/refill-markerpdf-workers.sh`
  - `scripts/refill-pandoc-workers.sh`
- Continuous refill guard:
  - `scripts/run-markerpdf-pandoc-refill-watchdog.sh`

## Worker Topology
- supervisor: monitors live windows, handoff queue, resource limits, integration order, and verification.
- markerPDF workers: isolated worktrees named `port-dev-markerpdf-*`.
- Pandoc workers: isolated worktrees named `port-dev-pandoc-*`.
- refill watchdog: starts missing markerPDF/Pandoc workers only; it does not integrate patches.

## Workflow
1. Keep the refill watchdog running in tmux.
2. Workers produce `.ready` handoffs under `.tmux-team/tmp/handoff-candidates/`.
3. Supervisor triages ready handoffs for duplicates, conflicts, and useful deltas.
4. Integrate accepted patches in a clean detached worktree.
5. Run lane-focused and full-lane tests before status/dashboard commits.
6. Push verified commits to `origin/main`.

## Quality Gates
- PHP lint for changed PHP files.
- Focused tests covering changed lane behavior.
- Full lane tests before publishing lane status.
- Smoke examples when examples or operational boundaries change.

## Rejected Distractions
- Restarting workers without defined lane/domain ownership.
- Accepting duplicate handoffs just to reduce queue size.
- Editing broad dirty-root files unrelated to markerPDF/Pandoc capacity.

## Final Acceptance Criteria
- Tmux shows the refill watchdog plus the target worker pool when resources allow.
- The watchdog log records each check and refill decision.
- The handoff integration queue continues under serial review without blocking worker production.
