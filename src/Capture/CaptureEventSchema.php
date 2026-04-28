<?php

declare(strict_types=1);

namespace AiProfileManager\Capture;

/**
 * Validates CaptureEvent schema version 2 only.
 */
final class CaptureEventSchema
{
    /**
     * @param array<mixed> $payload
     * @return array{valid: bool, errors: array<int, string>}
     */
    public function validate(array $payload): array
    {
        if (($payload['schema_version'] ?? null) !== 2) {
            return [
                'valid' => false,
                'errors' => ['schema_version must be 2.'],
            ];
        }

        return $this->validateV2($payload);
    }

    /**
     * @param array<mixed> $payload
     * @return array{valid: bool, errors: array<int, string>}
     */
    private function validateV2(array $payload): array
    {
        $errors = [];

        foreach (['event_id', 'source_repo', 'source_commit', 'captured_at', 'target'] as $field) {
            if (!isset($payload[$field]) || !is_string($payload[$field]) || trim($payload[$field]) === '') {
                $errors[] = sprintf('%s must be a non-empty string.', $field);
            }
        }

        if (!isset($payload['base_ref']) || !is_string($payload['base_ref'])) {
            $errors[] = 'base_ref must be a string (may be empty).';
        }

        $baseline = $payload['baseline'] ?? null;
        if (!is_array($baseline)) {
            $errors[] = 'baseline must be an object.';
        } else {
            foreach (['package', 'version', 'install_path'] as $b) {
                if (!isset($baseline[$b]) || !is_string($baseline[$b]) || trim((string) $baseline[$b]) === '') {
                    $errors[] = sprintf('baseline.%s must be a non-empty string.', $b);
                }
            }

            if (isset($baseline['reference']) && !is_string($baseline['reference'])) {
                $errors[] = 'baseline.reference must be a string when present.';
            }
        }

        if (!isset($payload['items']) || !is_array($payload['items'])) {
            $errors[] = 'items must be an array.';
        } else {
            foreach ($payload['items'] as $index => $item) {
                $errors = array_merge($errors, $this->validateItem($item, $index));
            }
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
        ];
    }

    /**
     * @param mixed $item
     * @return array<int, string>
     */
    private function validateItem(mixed $item, string|int $index): array
    {
        $errors = [];
        if (!is_array($item)) {
            return [sprintf('items[%s] must be an object.', (string) $index)];
        }

        foreach (['type', 'name', 'status', 'content_hash'] as $field) {
            if (!isset($item[$field]) || !is_string($item[$field]) || trim($item[$field]) === '') {
                $errors[] = sprintf('items[%s].%s must be a non-empty string.', (string) $index, $field);
            }
        }

        if (!isset($item['files']) || !is_array($item['files']) || $item['files'] === []) {
            $errors[] = sprintf('items[%s].files must be a non-empty array.', (string) $index);

            return $errors;
        }

        foreach ($item['files'] as $fileIndex => $file) {
            $errors = array_merge($errors, $this->validateFile($file, $index, $fileIndex));
        }

        return $errors;
    }

    /**
     * @param mixed $file
     * @return array<int, string>
     */
    private function validateFile(mixed $file, string|int $itemIndex, string|int $fileIndex): array
    {
        if (!is_array($file)) {
            return [sprintf('items[%s].files[%s] must be an object.', (string) $itemIndex, (string) $fileIndex)];
        }

        $errors = [];
        foreach (['path', 'patch'] as $field) {
            if (!isset($file[$field]) || !is_string($file[$field])) {
                $errors[] = sprintf('items[%s].files[%s].%s must be a string.', (string) $itemIndex, (string) $fileIndex, $field);
            }
        }

        if (!array_key_exists('content', $file) || !is_string($file['content'])) {
            $errors[] = sprintf('items[%s].files[%s].content must be a string.', (string) $itemIndex, (string) $fileIndex);
        }

        if (isset($file['deleted']) && !is_bool($file['deleted'])) {
            $errors[] = sprintf('items[%s].files[%s].deleted must be a boolean when present.', (string) $itemIndex, (string) $fileIndex);
        }

        return $errors;
    }
}
