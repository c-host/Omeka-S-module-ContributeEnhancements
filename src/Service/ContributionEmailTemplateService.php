<?php declare(strict_types=1);

namespace ContributeEnhancements\Service;

use Contribute\Api\Representation\ContributionRepresentation;
use Doctrine\DBAL\Connection;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Settings\Settings;

class ContributionEmailTemplateService
{
    public const TYPE_ACCEPTED = 'accepted';
    public const TYPE_REJECTED = 'rejected';
    public const TYPE_NEEDS_CHANGES = 'needs-changes';
    public const TYPE_ADDED_TO_OMEKA = 'added-to-omeka';

    protected Settings $settings;

    protected Connection $connection;

    /** @var array<string, array{subject: string, body: string}> */
    protected array $defaults;

    public function __construct(Settings $settings, Connection $connection, array $defaults)
    {
        $this->settings = $settings;
        $this->connection = $connection;
        $this->defaults = $defaults;
    }

    /**
     * @return array<string, string>
     */
    public function templateTypes(): array
    {
        return [
            self::TYPE_ACCEPTED => 'Accepted',
            self::TYPE_REJECTED => 'Rejected',
            self::TYPE_NEEDS_CHANGES => 'Needs changes',
            self::TYPE_ADDED_TO_OMEKA => 'Added to Omeka',
        ];
    }

    /**
     * @return array{subject: string, body: string}
     */
    public function render(ContributionRepresentation $contribution, string $type): array
    {
        $type = $this->normalizeType($type);
        $placeholders = $this->placeholders($contribution);

        $subject = $this->settings->get('contribute_enhancements_email_' . $type . '_subject')
            ?: ($this->defaults[$type]['subject'] ?? '');
        $body = $this->settings->get('contribute_enhancements_email_' . $type . '_body')
            ?: ($this->defaults[$type]['body'] ?? '');

        return [
            'subject' => $this->fill($subject, $placeholders),
            'body' => $this->fill($body, $placeholders),
        ];
    }

    public function templateForValidatedStatus(?bool $validated): ?string
    {
        if ($validated === true) {
            return self::TYPE_ACCEPTED;
        }

        if ($validated === false) {
            return self::TYPE_REJECTED;
        }

        return self::TYPE_NEEDS_CHANGES;
    }

    protected function normalizeType(string $type): string
    {
        $types = array_keys($this->templateTypes());
        if (!in_array($type, $types, true)) {
            throw new \InvalidArgumentException('Unknown email template type.');
        }

        return $type;
    }

    /**
     * @return array<string, string>
     */
    protected function placeholders(ContributionRepresentation $contribution): array
    {
        $mainTitle = (string) $this->settings->get('installation_title', 'Omeka S');
        $mainUrl = $this->installationUrl() ?: '{main_url}';

        $owner = $contribution->owner();
        $email = $contribution->email() ?: ($owner ? $owner->email() : '');

        $resource = $contribution->resource();
        $itemTitle = $resource ? (string) $resource->displayTitle() : $mainTitle;
        $itemUrls = $resource ? $this->formatPublicItemUrls($resource) : '';

        return [
            '{main_title}' => $mainTitle,
            '{main_url}' => $mainUrl,
            '{item_title}' => $itemTitle,
            '{item_urls}' => $itemUrls,
            '{contribution_id}' => (string) $contribution->id(),
            '{contributor_email}' => (string) $email,
            '{email}' => (string) $email,
            '{user_email}' => (string) $email,
            '{user_name}' => $owner ? (string) $owner->name() : '',
        ];
    }

    protected function formatPublicItemUrls(AbstractResourceEntityRepresentation $resource): string
    {
        if (!$resource->isPublic()) {
            return '';
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT s.slug, s.title
             FROM item_site its
             INNER JOIN site s ON s.id = its.site_id
             INNER JOIN resource r ON r.id = its.item_id
             WHERE its.item_id = :item_id AND r.is_public = 1
             ORDER BY s.title ASC',
            ['item_id' => $resource->id()]
        );

        if (!$rows) {
            return '';
        }

        $lines = [];
        foreach ($rows as $row) {
            $url = $this->siteItemUrl($row['slug'], $resource->id());
            $lines[] = '- ' . $row['title'] . ': ' . $url;
        }

        return implode("\n", $lines);
    }

    protected function installationUrl(): string
    {
        $url = (string) $this->settings->get('installation_url', '');
        if ($url !== '') {
            return rtrim($url, '/');
        }

        if (!empty($_SERVER['HTTP_HOST'])) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

            return $scheme . '://' . $_SERVER['HTTP_HOST'];
        }

        return '';
    }

    protected function siteItemUrl(string $siteSlug, int $itemId): string
    {
        return $this->installationUrl() . '/s/' . $siteSlug . '/item/' . $itemId;
    }

    /**
     * @param array<string, string> $placeholders
     */
    protected function fill(string $message, array $placeholders): string
    {
        if ($message === '') {
            return '';
        }

        return strtr($message, $placeholders);
    }
}
