<?php

namespace App\Support\Api;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;

/**
 * One reusable pattern for every module's index() endpoint instead of
 * hand-rolling filter/sort/search/pagination 19 times over. Every
 * allow-list is explicit - a client can never filter or sort by a column
 * the controller didn't opt in, which is also how this avoids leaking
 * internal-only columns (soft-delete timestamps, hashes, etc.) through
 * query params.
 *
 * Usage: ApiQuery::for($request, Product::query())
 *   ->filters(['status', 'category_id'])
 *   ->sorts(['name', 'created_at', 'price'])
 *   ->searchable(['name', 'description'])
 *   ->paginate();
 */
class ApiQuery
{
    private array $filterable = [];

    private array $sortable = [];

    private array $searchableColumns = [];

    private int $defaultPerPage = 15;

    private int $maxPerPage = 100;

    private function __construct(
        private readonly Request $request,
        private readonly Builder|Relation $query,
    ) {}

    /** Accepts a relation (Model::reviews()) as readily as a bare query builder. */
    public static function for(Request $request, Builder|Relation $query): self
    {
        return new self($request, $query);
    }

    public function filters(array $columns): self
    {
        $this->filterable = $columns;

        return $this;
    }

    public function sorts(array $columns): self
    {
        $this->sortable = $columns;

        return $this;
    }

    public function searchable(array $columns): self
    {
        $this->searchableColumns = $columns;

        return $this;
    }

    public function defaultPerPage(int $value): self
    {
        $this->defaultPerPage = $value;

        return $this;
    }

    public function paginate(): LengthAwarePaginator
    {
        $this->applyFilters();
        $this->applySearch();
        $this->applySort();

        $perPage = min(
            (int) ($this->request->integer('per_page') ?: $this->defaultPerPage),
            $this->maxPerPage,
        );

        return $this->query->paginate(max($perPage, 1))->withQueryString();
    }

    private function applyFilters(): void
    {
        $filters = (array) $this->request->query('filter', []);

        foreach ($filters as $column => $value) {
            if (! in_array($column, $this->filterable, true) || $value === null || $value === '') {
                continue;
            }

            if (is_array($value)) {
                $this->query->whereIn($column, $value);
            } else {
                $this->query->where($column, $value);
            }
        }
    }

    private function applySearch(): void
    {
        $term = trim((string) $this->request->query('search', ''));

        if ($term === '' || empty($this->searchableColumns)) {
            return;
        }

        $this->query->where(function (Builder $q) use ($term) {
            foreach ($this->searchableColumns as $column) {
                $q->orWhere($column, 'like', '%'.addcslashes($term, '%_\\').'%');
            }
        });
    }

    private function applySort(): void
    {
        $sortParam = trim((string) $this->request->query('sort', ''));

        if ($sortParam === '') {
            return;
        }

        foreach (explode(',', $sortParam) as $field) {
            $direction = str_starts_with($field, '-') ? 'desc' : 'asc';
            $column = ltrim($field, '-');

            if (in_array($column, $this->sortable, true)) {
                $this->query->orderBy($column, $direction);
            }
        }
    }
}
