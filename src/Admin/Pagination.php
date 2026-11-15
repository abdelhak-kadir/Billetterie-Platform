<?php

declare(strict_types=1);

namespace App\Admin;

final class Pagination
{
    public readonly int $page;
    public readonly int $pages;

    public function __construct(int $page, public readonly int $limit, public readonly int $total)
    {
        $this->pages = max(1, (int) ceil($total / max(1, $limit)));
        $this->page = min(max(1, $page), $this->pages);
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->limit;
    }

    public function hasPrevious(): bool
    {
        return $this->page > 1;
    }

    public function hasNext(): bool
    {
        return $this->page < $this->pages;
    }
}
