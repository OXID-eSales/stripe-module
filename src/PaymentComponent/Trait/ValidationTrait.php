<?php

declare(strict_types=1);

namespace OxidSolutionCatalysts\Stripe\PaymentComponent\Trait;

/**
 * Trait for input validation helpers
 *
 * Reusability: 100%
 * Usage: Add to controllers and request handlers
 */
trait ValidationTrait
{
    /**
     * Validate required field exists and is not empty
     *
     * @param array $data Data array
     * @param string $field Field name
     * @param string|null $errorMessage Custom error message
     * @throws \InvalidArgumentException If field is missing or empty
     */
    protected function validateRequired(array $data, string $field, ?string $errorMessage = null): void
    {
        if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
            throw new \InvalidArgumentException(
                $errorMessage ?? "Required field '{$field}' is missing or empty"
            );
        }
    }

    /**
     * Validate field type
     *
     * @param mixed $value Value to validate
     * @param string $expectedType Expected type (string, int, float, bool, array, object)
     * @param string $fieldName Field name for error message
     * @throws \InvalidArgumentException If type mismatch
     */
    protected function validateType(mixed $value, string $expectedType, string $fieldName): void
    {
        $actualType = gettype($value);

        $typeMatches = match ($expectedType) {
            'string' => is_string($value),
            'int', 'integer' => is_int($value),
            'float', 'double' => is_float($value),
            'bool', 'boolean' => is_bool($value),
            'array' => is_array($value),
            'object' => is_object($value),
            default => false,
        };

        if (!$typeMatches) {
            throw new \InvalidArgumentException(
                "Field '{$fieldName}' expected type '{$expectedType}', got '{$actualType}'"
            );
        }
    }

    /**
     * Validate field is one of allowed values
     *
     * @param mixed $value Value to validate
     * @param array $allowedValues Allowed values
     * @param string $fieldName Field name for error message
     * @throws \InvalidArgumentException If value not allowed
     */
    protected function validateEnum(mixed $value, array $allowedValues, string $fieldName): void
    {
        if (!in_array($value, $allowedValues, true)) {
            $allowed = implode(', ', array_map(fn($v) => "'{$v}'", $allowedValues));
            throw new \InvalidArgumentException(
                "Field '{$fieldName}' must be one of: {$allowed}. Got: '{$value}'"
            );
        }
    }

    /**
     * Validate numeric range
     *
     * @param int|float $value Value to validate
     * @param int|float|null $min Minimum value (inclusive)
     * @param int|float|null $max Maximum value (inclusive)
     * @param string $fieldName Field name for error message
     * @throws \InvalidArgumentException If out of range
     */
    protected function validateRange(
        int|float $value,
        int|float|null $min,
        int|float|null $max,
        string $fieldName
    ): void {
        if ($min !== null && $value < $min) {
            throw new \InvalidArgumentException(
                "Field '{$fieldName}' must be at least {$min}. Got: {$value}"
            );
        }

        if ($max !== null && $value > $max) {
            throw new \InvalidArgumentException(
                "Field '{$fieldName}' must be at most {$max}. Got: {$value}"
            );
        }
    }

    /**
     * Validate string length
     *
     * @param string $value Value to validate
     * @param int|null $minLength Minimum length
     * @param int|null $maxLength Maximum length
     * @param string $fieldName Field name for error message
     * @throws \InvalidArgumentException If length out of range
     */
    protected function validateLength(
        string $value,
        ?int $minLength,
        ?int $maxLength,
        string $fieldName
    ): void {
        $length = mb_strlen($value);

        if ($minLength !== null && $length < $minLength) {
            throw new \InvalidArgumentException(
                "Field '{$fieldName}' must be at least {$minLength} characters. Got: {$length}"
            );
        }

        if ($maxLength !== null && $length > $maxLength) {
            throw new \InvalidArgumentException(
                "Field '{$fieldName}' must be at most {$maxLength} characters. Got: {$length}"
            );
        }
    }

    /**
     * Validate email format
     *
     * @param string $email Email to validate
     * @param string $fieldName Field name for error message
     * @throws \InvalidArgumentException If invalid email
     */
    protected function validateEmail(string $email, string $fieldName = 'email'): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException(
                "Field '{$fieldName}' must be a valid email address. Got: '{$email}'"
            );
        }
    }

    /**
     * Validate URL format
     *
     * @param string $url URL to validate
     * @param string $fieldName Field name for error message
     * @throws \InvalidArgumentException If invalid URL
     */
    protected function validateUrl(string $url, string $fieldName = 'url'): void
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException(
                "Field '{$fieldName}' must be a valid URL. Got: '{$url}'"
            );
        }
    }
}
