<?php

declare(strict_types=1);

namespace Butschster\Commander\Module\ComposerManager\Component;

use Butschster\Commander\UI\Component\Display\Text\ListComponent;

/**
 * Component for displaying package authors with email and role information
 */
final class AuthorsList extends ListComponent
{
    /**
     * @param array<array{name?: string, email?: string, role?: string}> $authors
     */
    public function __construct(array $authors)
    {
        $formattedAuthors = $this->formatAuthors($authors);
        parent::__construct($formattedAuthors);
    }

    /**
     * Format authors array into displayable strings
     *
     * @param array<array{name?: string, email?: string, role?: string}> $authors
     * @return array<string>
     */
    private function formatAuthors(array $authors): array
    {
        return \array_map(static function (array $author): string {
            $line = $author['name'] ?? 'Unknown';

            if ($author['email'] ?? null) {
                $line .= " <{$author['email']}>";
            }

            if ($author['role'] ?? null) {
                $line .= " ({$author['role']})";
            }

            return $line;
        }, $authors);
    }
}
