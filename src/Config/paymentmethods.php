<?php
return [
    'cielo_standard' => [
        'code' => 'cielo_standard',
        'title' => 'Cielo',
        'description' => 'Cielo Payment',
        'class' => \Lucena\Cielo\Payment\Standard::class,
        'sandbox' => true,
        'active' => true,
        'business_account' => 'test@webkul.com',
        'sort' => 100
    ]
];