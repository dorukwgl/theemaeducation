<?php

namespace EMA\Utils;

use EMA\Utils\Logger;
use Exception;

class Validator
{
    private array $errors = [];
    private array $data = [];
    private array $rules = [];

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    public function setData(array $data): self
    {
        $this->data = $data;
        return $this;
    }

    public function setRules(array $rules): self
    {
        $this->rules = $rules;
        return $this;
    }

    public function validate(): bool
    {
        $this->errors = [];

        foreach ($this->rules as $field => $ruleString) {
            $fieldRules = explode('|', $ruleString);
            $value = $this->data[$field] ?? null;

            foreach ($fieldRules as $rule) {
                $this->applyRule($field, $rule, $value);
            }
        }

        $isValid = empty($this->errors);
        return $isValid;
    }

    private function applyRule(string $field, string $rule, $value): void
    {
        // Parse rule with parameters (e.g., "min:8")
        $parts = explode(':', $rule);
        $ruleName = $parts[0];
        $parameter = $parts[1] ?? null;

        switch ($ruleName) {
            case 'required':
                $this->validateRequired($field, $value);
                break;
            case 'email':
                if ($value !== null && $value !== '') {
                    $this->validateEmail($field, $value);
                }
                break;
            case 'min':
                if ($value !== null && $value !== '') {
                    $this->validateMin($field, $value, (int)$parameter);
                }
                break;
            case 'max':
                if ($value !== null && $value !== '') {
                    $this->validateMax($field, $value, (int)$parameter);
                }
                break;
            case 'between':
                if ($value !== null && $value !== '') {
                    $params = explode(',', $parameter);
                    $this->validateBetween($field, $value, (int)$params[0], (int)$params[1]);
                }
                break;
            case 'confirmed':
                $this->validateConfirmed($field, $value);
                break;
            case 'same':
                $this->validateSame($field, $value, $parameter);
                break;
            case 'different':
                $this->validateDifferent($field, $value, $parameter);
                break;
            case 'in':
                if ($value !== null && $value !== '') {
                    $allowed = explode(',', $parameter);
                    $this->validateIn($field, $value, $allowed);
                }
                break;
            case 'not_in':
                if ($value !== null && $value !== '') {
                    $forbidden = explode(',', $parameter);
                    $this->validateNotIn($field, $value, $forbidden);
                }
                break;
            case 'url':
                if ($value !== null && $value !== '') {
                    $this->validateUrl($field, $value);
                }
                break;
            case 'regex':
                if ($value !== null && $value !== '') {
                    $this->validateRegex($field, $value, $parameter);
                }
                break;
            case 'alpha':
                if ($value !== null && $value !== '') {
                    $this->validateAlpha($field, $value);
                }
                break;
            case 'alpha_num':
                if ($value !== null && $value !== '') {
                    $this->validateAlphaNum($field, $value);
                }
                break;
            case 'numeric':
                if ($value !== null && $value !== '') {
                    $this->validateNumeric($field, $value);
                }
                break;
            case 'integer':
                if ($value !== null && $value !== '') {
                    $this->validateInteger($field, $value);
                }
                break;
            case 'array':
                if ($value !== null && $value !== '') {
                    $this->validateArray($field, $value);
                }
                break;
            case 'file':
                $this->validateFile($field, $value);
                break;
            case 'image':
                $this->validateImage($field, $value);
                break;
            case 'mimes':
                if ($value !== null && $value !== '') {
                    $allowedMimes = explode(',', $parameter);
                    $this->validateMimes($field, $value, $allowedMimes);
                }
                break;
            case 'size':
                if ($value !== null && $value !== '') {
                    $this->validateSize($field, $value, (int)$parameter);
                }
                break;
            case 'unique':
                if ($value !== null && $value !== '') {
                    $this->validateUnique($field, $value, $parameter);
                }
                break;
            case 'exists':
                if ($value !== null && $value !== '') {
                    $this->validateExists($field, $value, $parameter);
                }
                break;
            case 'password':
                if ($value !== null && $value !== '') {
                    $this->validatePassword($field, $value);
                }
                break;
            case 'phone':
                if ($value !== null && $value !== '') {
                    $this->validatePhone($field, $value);
                }
                break;
        }
    }

    private function validateRequired(string $field, $value): void
    {
        if ($value === null || $value === '') {
            $this->errors[$field][] = "The $field field is required.";
        }
    }

    private function validateEmail(string $field, $value): void
    {
        if (!Security::validateEmail($value)) {
            $this->errors[$field][] = "The $field must be a valid email address.";
        }
    }

    private function validateMin(string $field, $value, int $min): void
    {
        if (is_string($value) && strlen($value) < $min) {
            $this->errors[$field][] = "The $field must be at least $min characters.";
        } elseif (is_numeric($value) && $value < $min) {
            $this->errors[$field][] = "The $field must be at least $min.";
        }
    }

    private function validateMax(string $field, $value, int $max): void
    {
        if (is_string($value) && strlen($value) > $max) {
            $this->errors[$field][] = "The $field must not exceed $max characters.";
        } elseif (is_numeric($value) && $value > $max) {
            $this->errors[$field][] = "The $field must not exceed $max.";
        }
    }

    private function validateBetween(string $field, $value, int $min, int $max): void
    {
        if (is_string($value) && (strlen($value) < $min || strlen($value) > $max)) {
            $this->errors[$field][] = "The $field must be between $min and $max characters.";
        } elseif (is_numeric($value) && ($value < $min || $value > $max)) {
            $this->errors[$field][] = "The $field must be between $min and $max.";
        }
    }

    private function validateConfirmed(string $field, $value): void
    {
        $confirmationField = $field . '_confirmation';
        $confirmationValue = $this->data[$confirmationField] ?? null;

        if ($value !== $confirmationValue) {
            $this->errors[$field][] = "The $field confirmation does not match.";
        }
    }

    private function validateSame(string $field, $value, string $otherField): void
    {
        $otherValue = $this->data[$otherField] ?? null;

        if ($value !== $otherValue) {
            $this->errors[$field][] = "The $field must match $otherField.";
        }
    }

    private function validateDifferent(string $field, $value, string $otherField): void
    {
        $otherValue = $this->data[$otherField] ?? null;

        if ($value === $otherValue) {
            $this->errors[$field][] = "The $field must be different from $otherField.";
        }
    }

    private function validateIn(string $field, $value, array $allowed): void
    {
        if (!in_array($value, $allowed)) {
            $this->errors[$field][] = "The selected $field is invalid.";
        }
    }

    private function validateNotIn(string $field, $value, array $forbidden): void
    {
        if (in_array($value, $forbidden)) {
            $this->errors[$field][] = "The selected $field is invalid.";
        }
    }

    private function validateUrl(string $field, $value): void
    {
        if (!Security::validateUrl($value)) {
            $this->errors[$field][] = "The $field must be a valid URL.";
        }
    }

    private function validateRegex(string $field, $value, string $pattern): void
    {
        if (!preg_match($pattern, $value)) {
            $this->errors[$field][] = "The $field format is invalid.";
        }
    }

    private function validateAlpha(string $field, $value): void
    {
        if (!preg_match('/^[a-zA-Z]+$/', $value)) {
            $this->errors[$field][] = "The $field may only contain letters.";
        }
    }

    private function validateAlphaNum(string $field, $value): void
    {
        if (!preg_match('/^[a-zA-Z0-9]+$/', $value)) {
            $this->errors[$field][] = "The $field may only contain letters and numbers.";
        }
    }

    private function validateNumeric(string $field, $value): void
    {
        if (!is_numeric($value)) {
            $this->errors[$field][] = "The $field must be a number.";
        }
    }

    private function validateInteger(string $field, $value): void
    {
        if (!filter_var($value, FILTER_VALIDATE_INT)) {
            $this->errors[$field][] = "The $field must be an integer.";
        }
    }

    private function validateArray(string $field, $value): void
    {
        if (!is_array($value)) {
            $this->errors[$field][] = "The $field must be an array.";
        }
    }

    private function validateFile(string $field, $value): void
    {
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
            $this->errors[$field][] = "The $field file is required.";
        }
    }

    private function validateImage(string $field, $value): void
    {
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
            return;
        }

        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($fileInfo, $_FILES[$field]['tmp_name']);

        if (!in_array($mimeType, \EMA\Config\Constants::ALLOWED_IMAGE_TYPES)) {
            $this->errors[$field][] = "The $field must be an image.";
        }
    }

    private function validateMimes(string $field, $value, array $allowedMimes): void
    {
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
            return;
        }

        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($fileInfo, $_FILES[$field]['tmp_name']);

        if (!in_array($mimeType, $allowedMimes)) {
            $this->errors[$field][] = "The $field has an invalid file type.";
        }
    }

    private function validateSize(string $field, $value, int $maxSize): void
    {
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
            return;
        }

        $fileSize = $_FILES[$field]['size'];

        if ($fileSize > $maxSize) {
            $maxMB = round($maxSize / 1048576, 2);
            $this->errors[$field][] = "The $field may not be greater than $maxMB MB.";
        }
    }

    private function validateUnique(string $field, $value, string $table): void
    {
        try {
            $conn = \EMA\Config\Database::getConnection();
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM `$table` WHERE `$field` = ?");
            $stmt->bind_param('s', $value);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();

            if ($row['count'] > 0) {
                $this->errors[$field][] = "The $field has already been taken.";
            }
        } catch (Exception $e) {
            Logger::error('Unique validation failed', [
                'field' => $field,
                'table' => $table,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function validateExists(string $field, $value, string $table): void
    {
        try {
            $parts = explode(',', $table);
            $tableName = $parts[0];
            $column = $parts[1] ?? 'id';

            $conn = \EMA\Config\Database::getConnection();
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM `$tableName` WHERE `$column` = ?");
            $stmt->bind_param('s', $value);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();

            if ($row['count'] === 0) {
                $this->errors[$field][] = "The selected $field is invalid.";
            }
        } catch (Exception $e) {
            Logger::error('Exists validation failed', [
                'field' => $field,
                'table' => $table,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function validatePassword(string $field, $value): void
    {
        $errors = Security::validatePasswordStrength($value);

        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->errors[$field][] = $error;
            }
        }
    }

    private function validatePhone(string $field, $value): void
    {
        $phone = Security::sanitizePhone($value);

        if (!preg_match('/^[0-9]{10}$/', $phone)) {
            $this->errors[$field][] = "The $field must be a valid 10-digit phone number.";
        }
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getFirstError(): ?string
    {
        foreach ($this->errors as $fieldErrors) {
            return $fieldErrors[0] ?? null;
        }
        return null;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function getErrorForField(string $field): array
    {
        return $this->errors[$field] ?? [];
    }

    public function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    private function sanitizeDataForLogging(): array
    {
        $sanitized = $this->data;
        $sensitiveFields = ['password', 'token', 'secret', 'api_key'];

        foreach ($sensitiveFields as $field) {
            if (isset($sanitized[$field])) {
                $sanitized[$field] = '***REDACTED***';
            }
        }

        return $sanitized;
    }

    public static function make(array $data, array $rules): self
    {
        $validator = new self($data);
        $validator->setRules($rules);
        return $validator;
    }
}