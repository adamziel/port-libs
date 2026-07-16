#!/usr/bin/env bash
# Durable user-mandated profile for every newly started Codex subagent.
# Keep this as a hard launcher policy, not an ambient-environment default.
AGENT_FAST_MODEL="gpt-5.5"
AGENT_FAST_REASONING="xhigh"
AGENT_FAST_SERVICE_TIER="priority"
export AGENT_FAST_MODEL AGENT_FAST_REASONING AGENT_FAST_SERVICE_TIER
