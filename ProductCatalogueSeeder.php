<?php
declare(strict_types=1);

/**
 * Versioned catalogue definitions. Keep this file limited to catalogue data so
 * additional catalogues can use the same ProductCatalogueInstaller engine.
 */
final class ProductCatalogueSeeder
{
    public static function catalogues(): array
    {
        return [
            'chivicks-signage-materials' => [
                'name' => 'Chivicks Concept signage materials',
                'categories' => [
                    [
                        'key' => 'foamboard-pvc',
                        'name' => 'Foamboard (PVC)',
                        'products' => [
                            '3mm Foamboard', '4mm Foamboard', '5mm Foamboard', '6mm Foamboard',
                            '8mm Foamboard', '10mm Foamboard', '15mm Foamboard', '20mm Foamboard',
                        ],
                    ],
                    [
                        'key' => 'original-plastic',
                        'name' => 'Original Plastic',
                        'products' => [
                            '2mm clear org', '3mm clear org', '4mm clear org', '5mm clear org',
                            '6mm clear org', '8mm clear org', '10mm clear org', '15mm clear org',
                            '20mm clear org', '2mm white org', '3mm white org', '4mm white org',
                            'Red org', 'Light yellow org', 'Golden Yellow org', 'Blue org', 'Green org',
                            'Black org', 'Orange org', 'Pink org', 'Purple org', 'Lemon org', 'Skyblue org',
                            'Matt gold org', 'Matt silver org', '2mm Mirror gold (light) org',
                            '2mm Mirror gold (dark) org', '3mm Mirror gold (light) org', 'Mirror silver org',
                        ],
                    ],
                    [
                        'key' => 'china-plastic',
                        'name' => 'China Plastic',
                        'products' => [
                            '2mm clear china', '3mm clear china', '3mm white china', '4mm clear china',
                            '4mm white china', '5mm clear china', '6mm clear china', '8mm clear china',
                            '10mm clear china', '3mm Mirror gold china', 'Yellow china', 'Red China',
                        ],
                    ],
                    [
                        'key' => 'acoboard-acp',
                        'name' => 'Acoboard (ACP)',
                        'products' => [
                            'White cladding', 'Black cladding', 'Blue cladding', 'Red cladding',
                            'Grey cladding', 'Brushed silver cladding', 'Matt silver cladding',
                            'Matt gold cladding', 'Mirror silver cladding', 'Mirror gold cladding',
                            'White partition', 'Black partition', 'Cream partition', 'Silver partition',
                            'Grey partition',
                        ],
                    ],
                    [
                        'key' => 'vinyl-sav',
                        'name' => 'Vinyl (SAV)',
                        'products' => [
                            'White sav', 'Black sav', 'Mirror gold sav', 'Mirror gold vinyl', 'Matt black vinyl',
                        ],
                    ],
                    [
                        'key' => 'accessories',
                        'name' => 'Accessories',
                        'products' => [
                            'Plastic cutter', 'Plastic blade', 'Sticker cutter', 'Sticker blade',
                            'China gum', '3M foam squgee', 'Plastic squgee',
                        ],
                    ],
                ],
            ],
        ];
    }
}
