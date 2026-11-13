<?php
/**
 * CatLab Drinks - Simple bar automation system
 * Copyright (C) 2019 Thijs Van der Schaeghe
 * CatLab Interactive bvba, Gent, Belgium
 * http://www.catlab.eu/
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

namespace App\Support;

/**
 * Labels an amount with a currency the way the user's interface language
 * writes it. Amounts are always shown with two decimals, whatever the
 * currency's own minor unit, because every stored amount in the system is a
 * two-decimal quantity and the label must equal the stored number.
 */
class Money
{
    /**
     * @param float $amount
     * @param string $currency ISO 4217 alphabetic code, e.g. "EUR".
     * @param string|null $locale Defaults to the application locale.
     * @return string
     */
    public static function format(float $amount, string $currency, ?string $locale = null): string
    {
        if (!class_exists(\NumberFormatter::class)) {
            return self::fallbackFormat($amount, $currency);
        }

        $formatter = new \NumberFormatter($locale ?? app()->getLocale(), \NumberFormatter::CURRENCY);
        $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, 2);
        $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 2);

        $formatted = $formatter->formatCurrency($amount, $currency);
        if ($formatted === false) {
            return self::fallbackFormat($amount, $currency);
        }

        return $formatted;
    }

    /**
     * Plain "CODE 2.50" label for environments without ext-intl.
     * @param float $amount
     * @param string $currency
     * @return string
     */
    public static function fallbackFormat(float $amount, string $currency): string
    {
        return $currency . ' ' . number_format($amount, 2, '.', '');
    }
}
