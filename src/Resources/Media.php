<?php
declare(strict_types=1);

namespace ZeroAI\Boss\Sdk\Resources;

/**
 * BOSS project 43 feature #123. Wraps media.images.generate - the tenant's
 * own configured Replicate integration generates the image and the tenant's
 * own Replicate account is billed, never BOSS's. Throws a ValidationException
 * (422 replicate_not_configured) if the org hasn't set up Replicate yet.
 *
 * Blocking - typically single-digit seconds for the default fast model, but
 * can take up to 15 minutes for a slower one. Set a generous HTTP timeout in
 * Config if you pass a non-default model.
 */
final class Media extends AbstractResource
{
    /**
     * @param array $options width/height/aspect_ratio/output_format/output_quality/safety_filter_level - all optional, model-dependent.
     */
    public function generateImage(string $prompt, string $model = 'black-forest-labs/flux-schnell', array $options = []): array
    {
        $body = array_merge(['prompt' => $prompt, 'model' => $model], $options);
        return $this->client->call('POST', '/media/images/generate', [], $body);
    }
}
