<?php
declare(strict_types=1);

namespace Mailings;

/** Field rules shared by the API and the importer. See Validator for rule keys. */
final class Schema
{
    /** @return array<string,array<string,mixed>> */
    public static function companyRules(): array
    {
        return [
            'name' => ['required' => true, 'max' => 255],
            'org_number' => ['max' => 20],
            'billing_address' => ['max' => 255],
            'postal_code' => ['max' => 20],
            'city' => ['max' => 100],
            'country' => ['format' => 'country', 'default' => 'NO'],
            'website' => ['format' => 'url', 'max' => 255],
            'category' => ['max' => 100],
            'status' => ['enum' => ['active', 'inactive', 'do_not_contact'], 'default' => 'active'],
            'notes' => ['max' => 5000],
        ];
    }

    /** @return array<string,array<string,mixed>> */
    public static function contactRules(): array
    {
        return [
            'company_id' => ['required' => true, 'format' => 'int'],
            'first_name' => ['required' => true, 'max' => 100],
            'last_name' => ['required' => true, 'max' => 100],
            'job_title' => ['max' => 100],
            'email' => ['required' => true, 'format' => 'email', 'max' => 254],
            'phone' => ['max' => 40],
            'consent_status' => ['enum' => ['unknown', 'granted', 'withdrawn'], 'default' => 'unknown'],
            'notes' => ['max' => 5000],
        ];
    }

    /** @return array<string,array<string,mixed>> */
    public static function userRules(): array
    {
        return [
            'email' => ['required' => true, 'format' => 'email', 'max' => 254],
            'display_name' => ['max' => 150],
            'role' => ['enum' => ['viewer', 'editor', 'admin'], 'default' => 'viewer'],
            'is_active' => ['enum' => ['0', '1'], 'default' => '1'],
        ];
    }

    /** @return array<string,array<string,mixed>> */
    public static function mailboxRules(): array
    {
        return [
            'display_name' => ['required' => true, 'max' => 150],
            'email' => ['required' => true, 'format' => 'email', 'max' => 254],
            'is_active' => ['enum' => ['0', '1'], 'default' => '1'],
        ];
    }

    /** @return array<string,array<string,mixed>> */
    public static function campaignRules(): array
    {
        return [
            'name' => ['required' => true, 'max' => 255],
            'description' => ['max' => 5000],
            'status' => ['enum' => ['draft', 'active', 'closed'], 'default' => 'draft'],
        ];
    }

    /** quantity_available empty/null means unlimited. campaign_id comes from the URL. @return array<string,array<string,mixed>> */
    public static function productRules(): array
    {
        return [
            'name' => ['required' => true, 'max' => 100],
            'description' => ['max' => 255],
            'price' => ['required' => true, 'format' => 'decimal'],
            'quantity_available' => ['format' => 'int'],
            'sort_order' => ['format' => 'int', 'default' => 0],
        ];
    }

    /** campaign comes from the URL; the product must belong to it. @return array<string,array<string,mixed>> */
    public static function saleCreateRules(): array
    {
        return [
            'product_id' => ['required' => true, 'format' => 'int'],
            'company_id' => ['required' => true, 'format' => 'int'],
            'quantity' => ['format' => 'int', 'min' => 1, 'default' => 1],
            'unit_price' => ['format' => 'decimal'],
            'status' => ['enum' => ['reserved', 'sold', 'cancelled'], 'default' => 'reserved'],
            'notes' => ['max' => 5000],
        ];
    }

    /** @return array<string,array<string,mixed>> */
    public static function saleUpdateRules(): array
    {
        return [
            'quantity' => ['required' => true, 'format' => 'int', 'min' => 1],
            'unit_price' => ['required' => true, 'format' => 'decimal'],
            'status' => ['required' => true, 'enum' => ['reserved', 'sold', 'cancelled']],
            'notes' => ['max' => 5000],
        ];
    }
}
