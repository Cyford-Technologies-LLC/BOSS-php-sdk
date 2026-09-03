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

    /**
     * BOSS project 43 feature #124. Starts an async talking-avatar video
     * generation (SadTalker by default) - image_url + audio_url in,
     * prediction_id back immediately (does not generate TTS itself, bring
     * your own audio_url). Poll getAvatarStatus() for the result.
     */
    public function generateAvatar(string $imageUrl, string $audioUrl, string $model = 'cjwbw/sadtalker', array $options = []): array
    {
        return $this->client->call('POST', '/media/video/generate-avatar', [], [
            'image_url' => $imageUrl,
            'audio_url' => $audioUrl,
            'model' => $model,
            'options' => $options,
        ]);
    }

    /** Poll a prediction_id returned by generateAvatar(). Throws a 404 ApiException if it wasn't started by this org. */
    public function getAvatarStatus(string $predictionId): array
    {
        return $this->client->call('GET', '/media/video/generate-avatar/status', ['prediction_id' => $predictionId]);
    }

    /**
     * BOSS project 43 feature #134. Read-only list of the org's Media Manager
     * library (images/video/audio/documents auto-stored from Replicate, TTS,
     * uploads, etc - distinct from generateImage()/generateAvatar() above,
     * which only create new files). Rows never include the server-local
     * file_path, only public_url.
     *
     * @param array $query Optional: media_type (image|video|audio|document),
     *   project_id (lists that project's files instead, ignoring limit),
     *   search (shortname prefix match only, not filename), limit (default
     *   200, max 500, ignored when project_id is set).
     */
    public function listFiles(array $query = []): array
    {
        return $this->client->call('GET', '/media/files', $query);
    }

    /** Get a single media library file by id, scoped to the caller's organization. */
    public function getFile(int $id): array
    {
        return $this->client->call('GET', "/media/files/{$id}");
    }

    /**
     * BOSS project 43 feature #134 follow-up. Folder tree (with a file_count per
     * folder, plus unfiled_count/total_count) for rendering a picker sidebar.
     */
    public function listFolders(): array
    {
        return $this->client->call('GET', '/media/folders');
    }

    /**
     * Upload a local file into the org's Media Manager library, as a real
     * multipart request via Client::callMultipart() - the normal JSON call()
     * path caps request bodies at 1MB server-side, nowhere near enough for a
     * real video/audio file. Same size/extension/MIME validation server-side
     * as the CRM's own browser upload path; throws ValidationException (422)
     * on any violation.
     *
     * @throws \RuntimeException if $filePath can't be read
     */
    public function uploadFile(string $filePath, string $mediaType, ?int $folderId = null): array
    {
        $meta = ['filename' => basename($filePath), 'media_type' => $mediaType];
        if ($folderId !== null) {
            $meta['folder_id'] = $folderId;
        }

        return $this->client->callMultipart('/media/upload', '/api/v2_media_upload.php', $meta, $filePath);
    }
}
