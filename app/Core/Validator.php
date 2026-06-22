<?php

declare(strict_types=1);

namespace App\Core;

/**
 * data-light Input Validator
 *
 * @author Suraj Dubey
 * @link https://mrsuraj.rf.gd
 * @link https://github.com/5ur4jd-dev
 */
class Validator
{
    private array $errors = [];

    public function required(string $field, mixed $value, string $message = null): self
    {
        if (empty($value) || (is_string($value) && trim($value) === '')) {
            $this->errors[$field] = $message ?? "{$field} is required.";
        }
        return $this;
    }

    public function min(string $field, string $value, int $min, string $message = null): self
    {
        if (strlen($value) < $min) {
            $this->errors[$field] = $message ?? "{$field} must be at least {$min} characters.";
        }
        return $this;
    }

    public function max(string $field, string $value, int $max, string $message = null): self
    {
        if (strlen($value) > $max) {
            $this->errors[$field] = $message ?? "{$field} must not exceed {$max} characters.";
        }
        return $this;
    }

    public function email(string $field, string $value, string $message = null): self
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = $message ?? "{$field} must be a valid email address.";
        }
        return $this;
    }

    public function in(string $field, string $value, array $allowed, string $message = null): self
    {
        if (!in_array($value, $allowed, true)) {
            $this->errors[$field] = $message ?? "{$field} must be one of: " . implode(', ', $allowed) . '.';
        }
        return $this;
    }

    public function fileType(string $field, array $file, array $allowedMimeTypes, string $message = null): self
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            $this->errors[$field] = $message ?? "{$field} has invalid file type. Allowed: " . implode(', ', $allowedMimeTypes) . '.';
        }
        return $this;
    }

    public function fileSize(string $field, array $file, int $maxSizeBytes, string $message = null): self
    {
        if ($file['size'] > $maxSizeBytes) {
            $mb = round($maxSizeBytes / 1024 / 1024, 2);
            $this->errors[$field] = $message ?? "{$field} exceeds maximum size of {$mb}MB.";
        }
        return $this;
    }

    public function fileExtension(string $field, string $filename, array $allowedExtensions, string $message = null): self
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions, true)) {
            $this->errors[$field] = $message ?? "{$field} has invalid extension. Allowed: " . implode(', ', $allowedExtensions) . '.';
        }
        return $this;
    }

    public function numeric(string $field, mixed $value, string $message = null): self
    {
        if (!is_numeric($value)) {
            $this->errors[$field] = $message ?? "{$field} must be numeric.";
        }
        return $this;
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function passes(): bool
    {
        return empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        return $this->errors[array_key_first($this->errors)] ?? null;
    }
}

