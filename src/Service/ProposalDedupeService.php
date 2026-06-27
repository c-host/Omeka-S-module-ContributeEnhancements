<?php declare(strict_types=1);

namespace ContributeEnhancements\Service;

/**
 * Ensures at most one proposal entry per original value fingerprint per term.
 */
class ProposalDedupeService
{
  /**
   * @param array<string, mixed> $proposal
   * @return array<string, mixed>
   */
  public function deduplicate(array $proposal): array
  {
    foreach ($proposal as $term => $entries) {
      if ($term === 'template' || $term === 'media' || !is_array($entries)) {
        continue;
      }

      $proposal[$term] = $this->deduplicateTermEntries($entries);
    }

    return $proposal;
  }

  /**
   * @param array<int|string, array<string, mixed>> $entries
   * @return array<int|string, array<string, mixed>>
   */
  protected function deduplicateTermEntries(array $entries): array
  {
    $deduped = [];

    foreach ($entries as $entry) {
      if (!is_array($entry)) {
        continue;
      }

      // Each user-submitted addition is its own action, even when the value
      // duplicates existing item metadata.
      if ($this->isEmptyOriginal($entry['original'] ?? [])) {
        $deduped[] = $entry;
        continue;
      }

            $fingerprint = $this->entryFingerprint($entry);
            if ($fingerprint === '') {
                $deduped[] = $entry;
                continue;
            }

      if (!isset($deduped[$fingerprint])) {
        $deduped[$fingerprint] = $entry;
        continue;
      }

      $deduped[$fingerprint] = $this->preferEntry($deduped[$fingerprint], $entry);
    }

    return array_values($deduped);
  }

  /**
   * @param array<string, mixed> $entry
   */
  public function entryFingerprint(array $entry): string
  {
    $original = $entry['original'] ?? [];
    if ($this->isEmptyOriginal($original)) {
      return 'proposed:' . $this->fingerprint($entry['proposed'] ?? []);
    }

    return $this->fingerprint($original);
  }

  /**
   * @param array<string, mixed> $original
   */
  protected function isEmptyOriginal(array $original): bool
  {
    if (array_key_exists('@uri', $original)) {
      return ($original['@uri'] ?? '') === '';
    }

    if (array_key_exists('@resource', $original)) {
      return !(int) ($original['@resource'] ?? 0);
    }

    return ($original['@value'] ?? '') === '';
  }

  /**
   * @param array<string, mixed> $original
   */
  public function fingerprint(array $original): string
  {
    if (array_key_exists('@uri', $original)) {
      return 'uri:' . ($original['@uri'] ?? '') . '|' . ($original['@label'] ?? '');
    }

    if (array_key_exists('@resource', $original)) {
      return 'resource:' . (int) ($original['@resource'] ?? 0);
    }

    $language = $original['@language'] ?? '';

    return 'literal:' . $language . '|' . (string) ($original['@value'] ?? '');
  }

  /**
   * @param array<string, mixed> $current
   * @param array<string, mixed> $candidate
   * @return array<string, mixed>
   */
  protected function preferEntry(array $current, array $candidate): array
  {
    $currentScore = $this->entryScore($current);
    $candidateScore = $this->entryScore($candidate);

    return $candidateScore >= $currentScore ? $candidate : $current;
  }

  /**
   * @param array<string, mixed> $entry
   */
  protected function entryScore(array $entry): int
  {
    $score = 0;

    if (!empty($entry[ProposalRemovalApprovalService::ENTRY_APPROVED])) {
      $score += 100;
    }

    if ($this->isRemovalEntry($entry)) {
      $score += 50;
    }

    if (!empty($entry[ProposalRemovalApprovalService::ENTRY_RESTORED])) {
      $score += 10;
    }

    if (($entry['proposed'] ?? null) !== ($entry['original'] ?? null)) {
      $score += 5;
    }

    return $score;
  }

  /**
   * @param array<string, mixed> $entry
   */
  protected function isRemovalEntry(array $entry): bool
  {
    $original = $entry['original'] ?? [];
    $proposed = $entry['proposed'] ?? [];

    if (array_key_exists('@uri', $original)) {
      return ($proposed['@uri'] ?? '') === '' && ($original['@uri'] ?? '') !== '';
    }

    if (array_key_exists('@resource', $original)) {
      return !(int) ($proposed['@resource'] ?? 0) && (int) ($original['@resource'] ?? 0);
    }

    return ($original['@value'] ?? '') !== '' && ($proposed['@value'] ?? '') === '';
  }
}
