<?php

namespace App\Rules;

use App\Models\Supplier;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

class UniqueSupplierPerLocation implements DataAwareRule, ValidationRule
{
    /** @var array<int|string, array<string, mixed>> */
    protected array $data = [];

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // "3.business_name" => "3"
        $rowKey   = Str::before($attribute, '.');
        $location = $this->data[$rowKey]['location'] ?? null;

        $location = is_string($location) ? trim($location) : $location;
        $location = ($location === '' ? null : $location);

        // where('location', null) becomes "IS NULL" automatically
        $exists = Supplier::query()
            ->where('name', trim((string) $value))
            ->where('location', $location)
            ->exists();

        if ($exists) {
            $fail($location
                ? "The \"Business Name\" already exists for location \"{$location}\"."
                : 'The "Business Name" already exists with no location.');
        }
    }
}
