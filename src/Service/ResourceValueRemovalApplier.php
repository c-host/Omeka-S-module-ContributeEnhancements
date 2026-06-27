<?php declare(strict_types=1);

namespace ContributeEnhancements\Service;

use Contribute\Api\Representation\ContributionRepresentation;
use Omeka\Api\Manager as ApiManager;

/**
 * Applies value removals that Contribute may miss when duplicate values exist.
 */
class ResourceValueRemovalApplier
{
    protected ApiManager $api;

    protected ProposalDiffService $diffService;

    public function __construct(ApiManager $api, ProposalDiffService $diffService)
    {
        $this->api = $api;
        $this->diffService = $diffService;
    }

    public function applyValidatedRemovals(ContributionRepresentation $contribution): void
    {
        if (!$contribution->isPatch() || $contribution->isValidated() !== true) {
            return;
        }

        $resource = $contribution->resource();
        if (!$resource) {
            return;
        }

        $editableTerms = $this->diffService->editableTermsFromTemplate($contribution->resourceTemplate());
        if (!$editableTerms) {
            return;
        }

        $proposal = $contribution->proposalNormalizeForValidation();
        $updates = [];

        foreach ($proposal as $term => $propositions) {
            if ($term === 'template' || $term === 'media' || $term === 'file' || !isset($editableTerms[$term])) {
                continue;
            }

            $removals = [];
            foreach ($propositions as $proposition) {
                if (($proposition['process'] ?? '') !== 'remove') {
                    continue;
                }
                $removals[] = $proposition;
            }

            if (!$removals) {
                continue;
            }

            $currentValues = $this->serializedValues($resource, $term);
            $newValues = $this->removeValuesFromList($currentValues, $removals);
            if ($newValues !== $currentValues) {
                $updates[$term] = $newValues;
            }
        }

        if (!$updates) {
            return;
        }

        $this->updateResourceValues($resource, $updates);
    }

    /**
     * Remove a single value from a resource, using value index when provided.
     *
     * @param array<string, mixed> $original
     */
    public function removeSingleValue(
        \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource,
        string $term,
        array $original,
        ?int $valueIndex = null
    ): void {
        $currentValues = $this->serializedValues($resource, $term);
        $newValues = $this->removeOneValue($currentValues, $original, $valueIndex);
        if ($newValues === $currentValues) {
            throw new \InvalidArgumentException('Value not found on resource.');
        }

        $this->updateResourceValues($resource, [$term => $newValues]);
    }

    /**
     * Re-add a single value to a resource.
     *
     * @param array<string, mixed> $original
     */
    public function addSingleValue(
        \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource,
        string $term,
        array $original
    ): void {
        $currentValues = $this->serializedValues($resource, $term);
        $currentValues[] = $this->serializeOriginal($original, $currentValues);

        $this->updateResourceValues($resource, [$term => $currentValues]);
    }

    /**
     * Replace a single value on a resource, matching by original or value index.
     *
     * @param array<string, mixed> $from
     * @param array<string, mixed> $to
     */
    public function updateSingleValue(
        \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource,
        string $term,
        array $from,
        array $to,
        ?int $valueIndex = null
    ): void {
        $currentValues = $this->serializedValues($resource, $term);

        if ($valueIndex !== null && isset($currentValues[$valueIndex])) {
            $index = $valueIndex;
        } else {
            $index = $this->findSerializedValueIndex($currentValues, $from);
        }

        if ($index === null) {
            throw new \InvalidArgumentException('Value not found on resource.');
        }

        $currentValues[$index] = $this->mergeSerializedValue(
            $currentValues[$index],
            $to
        );

        $this->updateResourceValues($resource, [$term => array_values($currentValues)]);
    }

    /**
     * @param array<string, mixed> $current
     * @param array<string, mixed> $replacement
     * @return array<string, mixed>
     */
    protected function mergeSerializedValue(array $current, array $replacement): array
    {
        if (array_key_exists('@uri', $replacement)) {
            $current['@id'] = $replacement['@uri'] ?? '';
            $current['o:label'] = $replacement['@label'] ?? '';

            return $current;
        }

        if (array_key_exists('@resource', $replacement)) {
            $current['value_resource_id'] = (int) ($replacement['@resource'] ?? 0);

            return $current;
        }

        $current['@value'] = $replacement['@value'] ?? '';
        if (array_key_exists('@language', $replacement)) {
            $current['o:lang'] = $replacement['@language'];
        }

        return $current;
    }

    /**
     * Omeka partial updates reuse value entities globally; always send all terms.
     *
     * @param array<string, array<int, array<string, mixed>>> $termUpdates
     */
    protected function updateResourceValues(
        \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource,
        array $termUpdates
    ): void {
        $data = $this->serializedAllValues($resource);
        foreach ($termUpdates as $term => $values) {
            $data[$term] = $values;
        }

        $this->api->update(
            $resource->resourceName(),
            $resource->id(),
            $data,
            [],
            ['isPartial' => true]
        );
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected function serializedAllValues(
        \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource
    ): array {
        $data = [];
        foreach ($resource->values() as $term => $propertyData) {
            $data[$term] = array_map(
                static fn ($value) => $value->jsonSerialize(),
                $propertyData['values'] ?? []
            );
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $original
     * @param array<int, array<string, mixed>> $siblings
     * @return array<string, mixed>
     */
    protected function serializeOriginal(array $original, array $siblings = []): array
    {
        if (array_key_exists('@uri', $original)) {
            $value = [
                'type' => 'uri',
                '@id' => $original['@uri'] ?? '',
                'o:label' => $original['@label'] ?? '',
            ];
        } elseif (array_key_exists('@resource', $original)) {
            $value = [
                'type' => 'resource',
                'value_resource_id' => (int) ($original['@resource'] ?? 0),
            ];
        } else {
            $value = [
                'type' => 'literal',
                '@value' => $original['@value'] ?? '',
            ];

            if (array_key_exists('@language', $original)) {
                $value['o:lang'] = $original['@language'];
            }
        }

        if ($siblings) {
            $value['property_id'] = $siblings[0]['property_id'] ?? null;
            if (array_key_exists('is_public', $siblings[0])) {
                $value['is_public'] = $siblings[0]['is_public'];
            }
        }

        return $value;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function serializedValues(
        \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource,
        string $term
    ): array {
        return array_map(
            static fn ($value) => $value->jsonSerialize(),
            $resource->value($term, ['all' => true]) ?: []
        );
    }

    /**
     * @param array<int, array<string, mixed>> $values
     * @param array<string, mixed> $original
     * @return array<int, array<string, mixed>>
     */
    protected function removeOneValue(array $values, array $original, ?int $valueIndex = null): array
    {
        if ($valueIndex !== null
            && isset($values[$valueIndex])
            && $this->serializedValueMatchesOriginal($values[$valueIndex], $original)
        ) {
            unset($values[$valueIndex]);

            return array_values($values);
        }

        return $this->removeValuesFromList($values, [['original' => $original]]);
    }

    /**
     * @param array<string, mixed> $value
     * @param array<string, mixed> $original
     */
    protected function serializedValueMatchesOriginal(array $value, array $original): bool
    {
        if (array_key_exists('@uri', $original)) {
            return ($value['@id'] ?? '') === ($original['@uri'] ?? '')
                && ($value['o:label'] ?? '') === ($original['@label'] ?? '');
        }

        if (array_key_exists('@resource', $original)) {
            return (int) ($value['value_resource_id'] ?? 0) === (int) ($original['@resource'] ?? 0);
        }

        return (string) ($value['@value'] ?? '') === (string) ($original['@value'] ?? '');
    }

    /**
     * @param array<int, array<string, mixed>> $values
     * @param array<int, array<string, mixed>> $removals
     * @return array<int, array<string, mixed>>
     */
    protected function removeValuesFromList(array $values, array $removals): array
    {
        $result = $values;
        foreach ($removals as $removal) {
            $index = $this->findSerializedValueIndex($result, $removal['original'] ?? []);
            if ($index === null) {
                continue;
            }
            unset($result[$index]);
        }

        return array_values($result);
    }

    /**
     * @param array<int, array<string, mixed>> $values
     * @param array<string, mixed> $original
     */
    protected function findSerializedValueIndex(array $values, array $original): ?int
    {
        if (array_key_exists('@uri', $original)) {
            foreach ($values as $index => $value) {
                if (($value['@id'] ?? '') === ($original['@uri'] ?? '')
                    && ($value['o:label'] ?? '') === ($original['@label'] ?? '')
                ) {
                    return $index;
                }
            }

            return null;
        }

        if (array_key_exists('@resource', $original)) {
            $originalId = (int) ($original['@resource'] ?? 0);
            foreach ($values as $index => $value) {
                if ((int) ($value['value_resource_id'] ?? 0) === $originalId) {
                    return $index;
                }
            }

            return null;
        }

        foreach ($values as $index => $value) {
            if ((string) ($value['@value'] ?? '') === (string) ($original['@value'] ?? '')) {
                return $index;
            }
        }

        return null;
    }
}
