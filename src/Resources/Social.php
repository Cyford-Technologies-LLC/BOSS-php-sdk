<?php
declare(strict_types=1);

namespace ZeroAI\Boss\Sdk\Resources;

/**
 * BOSS project 43 feature #125 - organic social posting across an org's own
 * already-connected Facebook/Instagram/LinkedIn/Pinterest accounts. Publish
 * methods are real, public, and irreversible - they post to the live
 * platform using the org's own OAuth token.
 *
 * Scoped to posting only, not ad campaign/"boost" spend - no such capability
 * exists in BOSS today to wrap.
 */
final class Social extends AbstractResource
{
    // ── Facebook ──────────────────────────────────────────────────────────────

    /** @param array $data target_type ('page'|'group'), target_id, message, plus optional link/image_url/scheduled_at/campaign_id/delivery_method. */
    public function facebookCreatePost(array $data): array
    {
        return $this->client->call('POST', '/social/facebook/posts', [], $data);
    }

    /** Publishes a draft/scheduled post to the live page/group. Real and irreversible. */
    public function facebookPublishPost(int $postId): array
    {
        return $this->client->call('POST', "/social/facebook/posts/{$postId}/publish");
    }

    public function facebookListPosts(array $query = []): array
    {
        return $this->client->call('GET', '/social/facebook/posts', $query);
    }

    // ── Instagram ─────────────────────────────────────────────────────────────

    /** @param array $data ig_user_id, image_url required; optional message/scheduled_at. */
    public function instagramCreatePost(array $data): array
    {
        return $this->client->call('POST', '/social/instagram/posts', [], $data);
    }

    /** Publishes via the container->publish flow. Real and irreversible. */
    public function instagramPublishPost(int $postId): array
    {
        return $this->client->call('POST', "/social/instagram/posts/{$postId}/publish");
    }

    public function instagramListPosts(array $query = []): array
    {
        return $this->client->call('GET', '/social/instagram/posts', $query);
    }

    // ── LinkedIn ──────────────────────────────────────────────────────────────

    /** @param array $data target_type ('profile'|'page'), target_id, message, plus optional link_url/media_type/image_url/scheduled_at/campaign_id. */
    public function linkedinCreatePost(array $data): array
    {
        return $this->client->call('POST', '/social/linkedin/posts', [], $data);
    }

    /** Publishes via the org's own UGC Posts API token. Real and irreversible. */
    public function linkedinPublishPost(int $postId): array
    {
        return $this->client->call('POST', "/social/linkedin/posts/{$postId}/publish");
    }

    // ── Pinterest ─────────────────────────────────────────────────────────────

    /** @param array $data board_id, description, image_url required; optional title/link_url/scheduled_at/campaign_id. */
    public function pinterestCreatePin(array $data): array
    {
        return $this->client->call('POST', '/social/pinterest/pins', [], $data);
    }

    /** Publishes via the org's own connected token. Real and irreversible. */
    public function pinterestPublishPin(int $pinId): array
    {
        return $this->client->call('POST', "/social/pinterest/pins/{$pinId}/publish");
    }
}
