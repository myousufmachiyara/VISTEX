<?php

// Category code => ordered list of extra fields for that category.
// Each field: key (stored in products.attributes JSON), label, type (text|number|select).
// Categories not listed here (e.g. 'packaging') get NO extra fields —
// only the generic ones (name, sku, measurement unit, etc.) apply.

return [

    // Processed / SKU / Rejection / Leftover share this field set
    'sku' => [
        ['key' => 'construction',    'label' => 'Construction',    'type' => 'text'],
        ['key' => 'width',           'label' => 'Width',           'type' => 'text'],
        ['key' => 'blend',           'label' => 'Blend',           'type' => 'text'],
        ['key' => 'print_method',    'label' => 'Print Method',    'type' => 'text'],
        ['key' => 'finished_width',  'label' => 'Finished Width',  'type' => 'text'],
        ['key' => 'design_code',     'label' => 'Design Code',     'type' => 'text'],
        ['key' => 'color',           'label' => 'Color',           'type' => 'text'],
    ],

    'rejection' => [
        ['key' => 'construction',    'label' => 'Construction',    'type' => 'text'],
        ['key' => 'width',           'label' => 'Width',           'type' => 'text'],
        ['key' => 'blend',           'label' => 'Blend',           'type' => 'text'],
        ['key' => 'print_method',    'label' => 'Print Method',    'type' => 'text'],
        ['key' => 'finished_width',  'label' => 'Finished Width',  'type' => 'text'],
        ['key' => 'design_code',     'label' => 'Design Code',     'type' => 'text'],
        ['key' => 'color',           'label' => 'Color',           'type' => 'text'],
    ],

    'leftover' => [
        ['key' => 'construction',    'label' => 'Construction',    'type' => 'text'],
        ['key' => 'width',           'label' => 'Width',           'type' => 'text'],
        ['key' => 'blend',           'label' => 'Blend',           'type' => 'text'],
        ['key' => 'print_method',    'label' => 'Print Method',    'type' => 'text'],
        ['key' => 'finished_width',  'label' => 'Finished Width',  'type' => 'text'],
        ['key' => 'design_code',     'label' => 'Design Code',     'type' => 'text'],
        ['key' => 'color',           'label' => 'Color',           'type' => 'text'],
    ],

    'yarn' => [
        ['key' => 'count',   'label' => 'Count',   'type' => 'text'],
        ['key' => 'blend',   'label' => 'Blend',   'type' => 'text'],
        ['key' => 'quality', 'label' => 'Quality',  'type' => 'text'],
    ],

    'greige' => [
        ['key' => 'warp',              'label' => 'Warp',               'type' => 'text'],
        ['key' => 'weft',              'label' => 'Weft',               'type' => 'text'],
        ['key' => 'ends',              'label' => 'Ends',                'type' => 'text'],
        ['key' => 'picks',             'label' => 'Picks',               'type' => 'text'],
        ['key' => 'blend',             'label' => 'Blend',               'type' => 'text'],
        ['key' => 'weft_yarn_quality', 'label' => 'Weft Yarn Quality',   'type' => 'text'],
        ['key' => 'warp_yarn_quality', 'label' => 'Warp Yarn Quality',   'type' => 'text'],
    ],

    'cut_pcs' => [
        ['key' => 'width', 'label' => 'Width', 'type' => 'text'],
    ],

    // 'packaging' intentionally has no entry — generic fields only
];