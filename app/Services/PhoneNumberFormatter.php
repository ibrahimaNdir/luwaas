<?php

namespace App\Services;

class PhoneNumberFormatter
{
    /**
     * Format a phone number to E.164 format
     *
     * @param string $phone The phone number to format
     * @return string The formatted E.164 phone number
     */
    public static function formatE164(string $phone): string
    {
        // Remove all non-digit characters
        $cleaned = preg_replace('/[^0-9]/', '', $phone);

        // If it starts with 0, it's a local Senegalese number (format: 07xxxxxxxx)
        if (preg_match('/^0/', $cleaned)) {
            // Remove the leading 0 and add country code +221
            return '+221' . substr($cleaned, 1);
        }

        // If it already starts with 221 (country code without +)
        if (preg_match('/^221/', $cleaned)) {
            // Just add the + at the beginning
            return '+' . $cleaned;
        }

        // If it already starts with +, assume it's already in E.164 format
        if (preg_match('/^\\+/', $phone)) {
            return $phone;
        }

        // Otherwise, return as is (should ideally be validated, but for now just return)
        return $cleaned;
    }
}