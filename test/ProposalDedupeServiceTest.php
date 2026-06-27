<?php declare(strict_types=1);

namespace ContributeEnhancementsTest;

use ContributeEnhancements\Service\ProposalDedupeService;
use PHPUnit\Framework\TestCase;

class ProposalDedupeServiceTest extends TestCase
{
    private ProposalDedupeService $dedupe;

    protected function setUp(): void
    {
        $this->dedupe = new ProposalDedupeService();
    }

    public function testKeepsDistinctUserAdditionsWithDuplicateValues(): void
    {
        $proposal = [
            'dcterms:subject' => [
                [
                    'original' => ['@value' => ''],
                    'proposed' => ['@value' => 'logo'],
                ],
                [
                    'original' => ['@value' => ''],
                    'proposed' => ['@value' => 'logo'],
                ],
            ],
        ];

        $result = $this->dedupe->deduplicate($proposal);

        $this->assertCount(2, $result['dcterms:subject']);
    }

    public function testCollapsesDuplicateEntriesForSameOriginal(): void
    {
        $proposal = [
            'dcterms:subject' => [
                [
                    'original' => ['@value' => 'logo'],
                    'proposed' => ['@value' => 'logo'],
                ],
                [
                    'original' => ['@value' => 'logo'],
                    'proposed' => ['@value' => 'brand'],
                ],
            ],
        ];

        $result = $this->dedupe->deduplicate($proposal);

        $this->assertCount(1, $result['dcterms:subject']);
        $this->assertSame('brand', $result['dcterms:subject'][0]['proposed']['@value']);
    }

    public function testFingerprintIncludesLanguageForLiterals(): void
    {
        $fingerprint = $this->dedupe->entryFingerprint([
            'original' => ['@value' => 'title', '@language' => 'en'],
            'proposed' => ['@value' => 'title', '@language' => 'en'],
        ]);

        $this->assertSame('literal:en|title', $fingerprint);
    }
}
