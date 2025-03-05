<?php

namespace isayalcintr\BaseObjects;

use BadMethodCallException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

abstract class BaseFilterObject extends BaseObject
{
    public string $alias = "";
    protected ?int $limit = null;

    public function __construct(string $alias = "")
    {
        $this->alias = $alias;
    }

    /**
     * @return int|null
     */
    public function getLimit(): ?int
    {
        return $this->limit;
    }

    /**
     * @param int|null $limit
     * @return self
     */
    public function setLimit(?int $limit): self
    {
        $this->limit = min(max(0, $limit), 100); // 0 ile 100 arasında bir değer olmasını garanti eder
        return $this;
    }

    public function isFilterableLimit(): false
    {
        return false;
    }

    public function apply(Builder $query, array $only = [], array $except = []): Builder
    {
        $appliedFilters = $this->applyCustomFilters($query, $only, $except);

        $this->applyDynamicFilters($query, $appliedFilters, $only, $except);

        if ($this->getLimit() > 0) {
            $query->limit($this->getLimit());
        }

        return $query;
    }

    /**
     * Özel olarak tanımlanmış filterToX metotlarını uygular.
     */
    protected function applyCustomFilters(Builder $query, array $only, array $except): array
    {
        $methods = get_class_methods($this);
        $filterMethods = array_filter($methods, fn($method) => str_starts_with($method, 'filterTo'));

        if (!empty($only)) {
            $filterMethods = array_filter($filterMethods, fn($method) => in_array(lcfirst(substr($method, 8)), $only));
        }
        if (!empty($except)) {
            $filterMethods = array_filter($filterMethods, fn($method) => !in_array(lcfirst(substr($method, 8)), $except));
        }

        $appliedFilters = [];

        foreach ($filterMethods as $method) {
            $property = lcfirst(substr($method, 8));
            if ($this->isFilterable($property)) {
                $query = $this->$method($query);
                $appliedFilters[] = $property;
            }
        }

        return $appliedFilters;
    }

    /**
     * Dinamik olarak property’ler için filtre uygular.
     */
    protected function applyDynamicFilters(Builder $query, array $appliedFilters, array $only, array $except): void
    {
        foreach (get_object_vars($this) as $property => $value) {
            if ($this->shouldApplyDynamicFilter($property, $value, $appliedFilters, $only, $except)) {
                $methodName = 'filterTo' . ucfirst($property);
                $query = $this->applyFilterIfPossible($query, $methodName, $property);
            }
        }
    }

    /**
     * Dinamik filtreleme yapılıp yapılmayacağını kontrol eder.
     */
    protected function shouldApplyDynamicFilter(string $property, mixed $value, array $appliedFilters, array $only, array $except): bool
    {
        return $value !== null &&
            !in_array($property, $appliedFilters) &&
            (empty($only) || in_array($property, $only)) &&
            !in_array($property, $except);
    }

    protected function applyFilterIfPossible(Builder $query, string $method, string $property): Builder
    {
        if ($this->isFilterable($property)) {
            if (method_exists($this, $method)) {
                return $this->$method($query);
            }
            if (property_exists($this, $property) && method_exists($this, 'get' . ucfirst($property))) {
                return $this->__call($method, [$query]);
            }
        }
        return $query;
    }

    public function __call($name, $arguments)
    {
        if (str_starts_with($name, 'filterTo')) {
            return $this->applySimpleFilter($name, $arguments[0]);
        }

        throw new BadMethodCallException("Method {$name} does not exist.");
    }

    /**
     * Eğer özel bir filterToX metodu yoksa, doğrudan where koşulu ekler.
     */
    protected function applySimpleFilter(string $name, Builder $query): Builder
    {
        $property = lcfirst(substr($name, 8));

        if (property_exists($this, $property) && method_exists($this, 'get' . ucfirst($property))) {
            $value = $this->{'get' . ucfirst($property)}();
            if ($value !== null) {
                $query->where($this->getColumn(Str::snake($property)), $value);
            }
        }

        return $query;
    }

    /**
     * Belirtilen property filtrelenebilir mi?
     */
    protected function isFilterable(string $property): bool
    {
        $method = 'isFilterable' . ucfirst($property);
        return method_exists($this, $method)
            ? $this->$method()
            : (property_exists($this, $property) && $this->$property !== null);
    }

    /**
     * SQL sorgusunda hangi sütun adını kullanmalıyız?
     */
    protected function getColumn(string $column): string
    {
        return $this->alias ? "{$this->alias}.{$column}" : $column;
    }
}
