<?php

return [
    'access' => [
        'invitation_hours' => 48,
    ],
    'recovery' => [
        'budget_seconds' => 40,
        'cache_store' => 'database',
    ],
    'checkout' => [
        'access_token_days' => 7,
        'minimum_charge_cents' => 1000,
        'maximum_amount_cents' => 99999999,
    ],
    'information' => [
        'is_demo' => true,
        'terms' => 'DEMOSTRACIÓN — Compra con pago completo y recogida exclusiva en el local. Espera el segundo correo antes de acudir; no se promete disponibilidad inmediata. Condiciones comerciales definitivas pendientes de configurar.',
        'privacy' => 'DEMOSTRACIÓN — Se solicitarán nombre, correo y teléfono para atender la compra y la recogida. Aviso de privacidad definitivo pendiente de configurar.',
        'returns' => 'DEMOSTRACIÓN — Contacta al local para solicitar una devolución. La empresa atenderá el procedimiento fuera de la tienda. Política definitiva pendiente de configurar.',
    ],
    'defaults' => [
        'public_markup_basis_points' => 3000,
        'installer_markup_basis_points' => 1000,
        'tax_basis_points' => 1600,
        'currency' => 'MXN',
        'country' => 'MX',
        'timezone' => 'America/Chihuahua',
        'pickup_name' => 'DEMOSTRACIÓN — Local ficticio',
        'pickup_address' => 'C. Toronja 561 col. Indeco 23070 La Paz, B.C.S.',
        'pickup_hours' => '24/7',
        'contact_email' => 'example@example.com',
        'contact_phone' => '612 117 7002',
        'is_demo' => true,
        'version' => 1,
    ],
];
