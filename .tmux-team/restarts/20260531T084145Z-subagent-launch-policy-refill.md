# Restart/Refill Note - 2026-05-31T08:41:45Z

- Durable launch policy checked and tightened: new subagents must start with
  `gpt-5.5`, `model_reasoning_effort="xhigh"`, and priority/fast service tier.
- Patched durable tmux launchers to propagate `AGENT_FAST_MODEL`,
  `AGENT_FAST_REASONING`, and `AGENT_FAST_SERVICE_TIER` explicitly.
- No healthy worker was restarted solely for policy churn; live Codex worker
  command lines already showed `-m gpt-5.5`,
  `model_reasoning_effort="xhigh"`, and `model_service_tier="priority"`.
- Refilled one missing Gitoxide lane. Pool after refill: 12 isolated workers,
  0 long sleepers, visible in tmux session `main`.
- Next decision: triage newest current-base Gitoxide handoffs first
  (`attrs-pathspec`, `config-include`, `sparse-checkout`, `ls-refs`,
  `pack-delta`) and integrate only patches with focused zero-fail evidence.
