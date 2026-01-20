<?php
/**
 * ===========================================
 * إعدادات العملة الموحدة للمتجر
 * ===========================================
 * تم التحديث: 2025-12-20 18:52:34
 */

if (defined('CURRENCY_LOADED')) { return; }
define('CURRENCY_LOADED', true);

if (!defined('STORE_CURRENCY')) {
    define('STORE_CURRENCY', 'SAR');
}

$CURRENCIES = [
    'SAR' => [
        'code' => 'SAR',
        'name' => '',
        'symbol' => 'ر.س',
        'position' => 'after',
        'decimals' => 2,
        'tax_rate' => 0.15
    ]
];

$CURRENT_CURRENCY = $CURRENCIES[STORE_CURRENCY] ?? $CURRENCIES['SAR'];

if (!function_exists('formatPrice')) {
    function formatPrice($price, $showSymbol = true) {
        global $CURRENT_CURRENCY;
        $formatted = number_format($price, $CURRENT_CURRENCY['decimals']);
        if (!$showSymbol) return $formatted;
        if ($CURRENT_CURRENCY['position'] === 'before') {
            return $CURRENT_CURRENCY['symbol'] . ' ' . $formatted;
        }
        return $formatted . ' ' . $CURRENT_CURRENCY['symbol'];
    }
}

if (!function_exists('getCurrencySymbol')) {
    function getCurrencySymbol() {
        global $CURRENT_CURRENCY;
        return $CURRENT_CURRENCY['symbol'];
    }
}

if (!function_exists('getTaxRate')) {
    function getTaxRate() {
        global $CURRENT_CURRENCY;
        return $CURRENT_CURRENCY['tax_rate'];
    }
}

if (!function_exists('calculateTax')) {
    function calculateTax($amount) {
        global $CURRENT_CURRENCY;
        return $amount * $CURRENT_CURRENCY['tax_rate'];
    }
}

if (!function_exists('getCurrencyName')) {
    function getCurrencyName() {
        global $CURRENT_CURRENCY;
        return $CURRENT_CURRENCY['name'];
    }
}

if (!function_exists('getCurrencyCode')) {
    function getCurrencyCode() {
        global $CURRENT_CURRENCY;
        return $CURRENT_CURRENCY['code'];
    }
}
?>