<?php
declare(strict_types=1);

namespace ZeroAI\Boss\Sdk\Resources;

/**
 * BOSS project 43 feature #119. Wraps crm.agents.* - create/configure AI
 * agents and chat with them server-side.
 *
 * Disambiguation (BOSS project 43 feature #132, resolved 2026-08-31):
 * dynamic/chat.threads.* looked like a possible duplicate of crm.agents.chat
 * in the original platform scan. It is NOT - chat.threads.* is a generic
 * threaded messaging system (rider/driver/human-to-human, with an optional
 * agent_id to invite an AI agent into the same room), while crm.agents.chat
 * is a one-shot "send a message, get this agent's reply" call - the same
 * primitive the JS SDK's chat widget already uses successfully
 * (www/web/widget/chat.php). No consolidation needed; wrapping crm.agents.*
 * here is correct as-is.
 */
final class Agents extends AbstractResource
{
    public function list(array $query = []): array
    {
        return $this->client->call('GET', '/crm/agents', $query);
    }

    /** @param array $data Required: name. Optional: role, goal/instructions, backstory, llm_model, status, tools[]. Returns agent.id for use in chat(). */
    public function create(array $data): array
    {
        return $this->client->call('POST', '/crm/agents', [], $data);
    }

    public function get(int $agentId): array
    {
        return $this->client->call('GET', "/crm/agents/{$agentId}");
    }

    public function update(int $agentId, array $data): array
    {
        return $this->client->call('PUT', "/crm/agents/{$agentId}", [], $data);
    }

    /** @param array $data Required: message and an agent identifier. Optional: thread_id, or entity_type+entity_id for a shared rider/driver/agent room. */
    public function chat(array $data): array
    {
        return $this->client->call('POST', '/crm/agents/chat', [], $data);
    }

    public function listTools(): array
    {
        return $this->client->call('GET', '/crm/agent-tools');
    }
}
