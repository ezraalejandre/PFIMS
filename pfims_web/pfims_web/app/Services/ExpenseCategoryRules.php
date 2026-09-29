<?php

namespace App\Services;

class ExpenseCategoryRules
{
    public static function component(object $category): string
    {
        $code = strtoupper((string) ($category->category_code ?? ''));
        $name = strtolower((string) ($category->category_name ?? ''));

        return match (true) {
            in_array($code, ['CONSTRUCTION_SUPPLY', 'CONST_SUPPLY'], true),
            str_contains($name, 'construction suppl'), str_contains($name, 'material') => 'material',
            in_array($code, ['SALARIES_WAGES', 'ADMIN_SALARIES', 'OFFICE_SALARIES', 'EMPLOYER_CONTRIBUTIONS', 'SSS_PHILHEALTH'], true),
            str_contains($name, 'salary'), str_contains($name, 'salaries'), str_contains($name, 'wages'),
            str_contains($name, 'labor'), str_contains($name, 'contribution') => 'labor',
            in_array($code, ['EQUIPMENT_RENTAL', 'TRANSPO'], true),
            str_contains($name, 'equipment'), str_contains($name, 'transport') => 'equipment',
            default => 'other',
        };
    }
}
