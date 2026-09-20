<?php

namespace App\Core;

/**
 * Лёгкий помощник для валидации входных данных форм.
 */
class Validator
{
    private array $errors = [];
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function get(string $key, $default = null)
    {
        $value = $this->data[$key] ?? $default;
        return is_string($value) ? trim($value) : $value;
    }

    public function required(string $key, string $label): self
    {
        $value = $this->get($key);
        if ($value === null || $value === '') {
            $this->errors[$key] = "Поле «{$label}» обязательно для заполнения";
        }
        return $this;
    }

    public function maxLength(string $key, int $max, string $label): self
    {
        $value = $this->get($key);
        if (is_string($value) && mb_strlen($value) > $max) {
            $this->errors[$key] = "Поле «{$label}» не должно превышать {$max} символов";
        }
        return $this;
    }

    public function email(string $key, string $label): self
    {
        $value = $this->get($key);
        if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$key] = "Поле «{$label}» должно быть корректным email";
        }
        return $this;
    }

    public function in(string $key, array $allowed, string $label): self
    {
        $value = $this->get($key);
        if ($value !== null && $value !== '' && !in_array($value, $allowed, true)) {
            $this->errors[$key] = "Недопустимое значение поля «{$label}»";
        }
        return $this;
    }

    public function minLength(string $key, int $min, string $label): self
    {
        $value = $this->get($key);
        if ($value !== null && $value !== '' && mb_strlen((string)$value) < $min) {
            $this->errors[$key] = "Поле «{$label}» должно быть не короче {$min} символов";
        }
        return $this;
    }

    /** Добавить произвольную ошибку валидации (для полей вне простых правил выше) */
    public function addError(string $key, string $message): self
    {
        $this->errors[$key] = $message;
        return $this;
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        $first = reset($this->errors);
        return $first !== false ? $first : null;
    }
}
