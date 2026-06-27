<?php declare(strict_types=1);

namespace ContributeEnhancements\Service;

use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\ResourceTemplateRepresentation;

/**
 * Stores item metadata as it existed when a contribution was submitted.
 */
class ProposalBaselineService
{
    public const BASELINE_KEY = 'o-module-contribute-enhancements:baseline';

    protected ProposalDiffService $diffService;

    public function __construct(ProposalDiffService $diffService)
    {
        $this->diffService = $diffService;
    }

  /**
   * @return array<string, mixed>
   */
  public function stripSpecialKeys(array $proposal): array
  {
    unset($proposal['template'], $proposal['media'], $proposal[self::BASELINE_KEY]);

    return $proposal;
  }

  public function hasBaseline(array $proposal): bool
  {
    return !empty($proposal[self::BASELINE_KEY]) && is_array($proposal[self::BASELINE_KEY]);
  }

  /**
   * @return array<string, array<int, array<string, mixed>>>
   */
  public function getBaseline(array $proposal): array
  {
    $baseline = $proposal[self::BASELINE_KEY] ?? [];

    return is_array($baseline) ? $baseline : [];
  }

  /**
   * @param array<string, true> $editableTerms
   * @return array<string, array<int, array<string, mixed>>>
   */
  public function captureBaseline(
    ?AbstractResourceEntityRepresentation $resource,
    array $editableTerms
  ): array {
    if (!$resource) {
      return [];
    }

    $baseline = [];
    foreach ($editableTerms as $term => $_) {
      if ($term === 'file') {
        continue;
      }

      $values = $resource->value($term, ['all' => true]) ?: [];
      $rows = [];
      foreach ($values as $value) {
        $rows[] = $this->valueToPayload($value);
      }

      if ($rows) {
        $baseline[$term] = $rows;
      }
    }

    return $baseline;
  }

  /**
   * @param array<string, true> $editableTerms
   */
  public function attachBaseline(
    array $proposal,
    ?AbstractResourceEntityRepresentation $resource,
    ?ResourceTemplateRepresentation $template
  ): array {
    if ($this->hasBaseline($proposal) || !$resource) {
      return $proposal;
    }

    $editableTerms = $this->diffService->editableTermsFromTemplate($template);
    if (!$editableTerms) {
      return $proposal;
    }

    $proposal[self::BASELINE_KEY] = $this->captureBaseline($resource, $editableTerms);

    return $proposal;
  }

  /**
   * @return array<string, mixed>
   */
  protected function valueToPayload(\Omeka\Api\Representation\ValueRepresentation $value): array
  {
    $baseType = $value->type();
    if (str_contains((string) $baseType, 'resource')) {
      $resource = $value->valueResource();

      return [
        '@resource' => $resource ? (int) $resource->id() : 0,
      ];
    }

    if ($value->uri() !== null && $value->uri() !== '') {
      return [
        '@uri' => $value->uri(),
        '@label' => $value->value(),
      ];
    }

    $payload = ['@value' => $value->value()];
    if ($value->lang()) {
      $payload['@language'] = $value->lang();
    }

    return $payload;
  }
}
