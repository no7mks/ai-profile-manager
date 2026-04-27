<?php

declare(strict_types=1);

namespace AiProfileManager\Capture;

final class CaptureEventSchema
{
    /**
     * @param array<mixed> $payload
     * @return array{valid: bool, errors: array<int, string>}
     */
    public function validate(array $payload): array
    {
        $errors = [];

        if (($payload['schema_version'] ?? null) !== 1) {
            $errors[] = 'schema_version must be 1.';
        }

        foreach (['event_id', 'source_repo', 'source_commit', 'captured_at', 'target'] as $field) {
            if (!isset($payload[$field]) || !is_string($payload[$field]) || trim($payload[$field]) === '') {
                $errors[] = sprintf('%s must be a non-empty string.', $field);
            }
        }

        if (!isset($payload['items']) || !is_array($payload['items'])) {
            $errors[] = 'items must be an array.';
        } else {
            foreach ($payload['items'] as $index => $item) {
                if (!is_array($item)) {
                    $errors[] = sprintf('items[%d] must be an object.', $index);
                    continue;
                }

                foreach (['type', 'name', 'status', 'content_hash'] as $field) {
                    if (!isset($item[$field]) || !is_string($item[$field]) || trim($item[$field]) === '') {
                        $errors[] = sprintf('items[%d].%s must be a non-empty string.', $index, $field);
                    }
                }
            }
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
        ];
    }
}
