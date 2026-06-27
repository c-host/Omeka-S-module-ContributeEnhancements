<?php declare(strict_types=1);
/**
 * Quick smoke tests (no PHPUnit required).
 * Run: php test/smoke.php
 */

require dirname(__DIR__) . '/src/Service/ProposalRemovalApprovalService.php';
require dirname(__DIR__) . '/src/Service/ProposalDedupeService.php';

use ContributeEnhancements\Service\ProposalDedupeService;

$failures = 0;
$dedupe = new ProposalDedupeService();

$additions = $dedupe->deduplicate([
    'dcterms:subject' => [
        ['original' => ['@value' => ''], 'proposed' => ['@value' => 'a']],
        ['original' => ['@value' => ''], 'proposed' => ['@value' => 'a']],
    ],
]);
if (count($additions['dcterms:subject']) !== 2) {
    echo "FAIL duplicate user additions should remain distinct\n";
    ++$failures;
} else {
    echo "OK duplicate user additions remain distinct\n";
}

$collapsed = $dedupe->deduplicate([
    'dcterms:subject' => [
        ['original' => ['@value' => 'logo'], 'proposed' => ['@value' => 'logo']],
        ['original' => ['@value' => 'logo'], 'proposed' => ['@value' => 'brand']],
    ],
]);
if (count($collapsed['dcterms:subject']) !== 1) {
    echo "FAIL duplicate originals should collapse\n";
    ++$failures;
} else {
    echo "OK duplicate originals collapse\n";
}

exit($failures ? 1 : 0);
