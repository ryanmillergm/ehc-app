<?php

namespace App\Models\Concerns;

trait HasStageMetadata
{
    public function stage(): ?string
    {
        return data_get($this->metadata, 'stage');
    }

    public function setStage(string $stage, bool $save = false): static
    {
        $this->metadata = $this->mergeMetadata($this->metadata, ['stage' => $stage]);

        if ($save) {
            $this->save();
        }

        return $this;
    }

    public function mergeMetadata($existing, array $extra): array
    {
        $base = [];

        if (is_array($existing)) {
            $base = $existing;
        } elseif (is_string($existing) && $existing !== '') {
            $decoded = json_decode($existing, true);
            if (is_array($decoded)) {
                $base = $decoded;
            }
        } elseif (is_object($existing)) {
            $base = (array) $existing;
        }

        // extra wins
        return array_merge($base, $extra);
    }
}
