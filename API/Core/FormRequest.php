<?php

declare(strict_types=1);

namespace API\Core;

/**
 * Abstract base for validated form requests.
 *
 * Subclass this and implement rules() to declare validation rules.
 * The validated data is extracted from GET, POST, or JSON body automatically.
 *
 * Usage in a controller:
 *   $request = CreateNoteRequest::fromGlobals();
 *   if (!$request->isValid()) {
 *       return $this->error($request->errors(), 422);
 *   }
 *   $data = $request->validated();
 */
abstract class FormRequest
{
    private array $data = [];
    private array $errors = [];
    private bool $validated = false;

    /**
     * Define validation rules.
     * Keys are field names, values are rule strings (pipe-separated) or arrays.
     * Uses the existing API\Security\Validator rule syntax.
     *
     * @return array<string, string|array>
     */
    abstract public function rules(): array;

    /**
     * Optional: define default values for missing fields.
     *
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [];
    }

    /**
     * Create an instance from the current HTTP request globals.
     */
    public static function fromGlobals(): static
    {
        $instance = new static();
        $instance->data = $instance->extractInput();
        return $instance;
    }

    /**
     * Create an instance from an explicit data array (useful for testing).
     */
    public static function fromArray(array $data): static
    {
        $instance = new static();
        $instance->data = $data;
        return $instance;
    }

    /**
     * Whether the request data passes all validation rules.
     */
    public function isValid(): bool
    {
        if (!$this->validated) {
            $this->runValidation();
        }
        return empty($this->errors);
    }

    /**
     * Returns the validated data (only fields declared in rules()).
     * Call after isValid().
     */
    public function validated(): array
    {
        if (!$this->validated) {
            $this->runValidation();
        }

        $result = [];
        $defaults = $this->defaults();

        foreach (array_keys($this->rules()) as $field) {
            if (array_key_exists($field, $this->data)) {
                $result[$field] = $this->data[$field];
            } elseif (array_key_exists($field, $defaults)) {
                $result[$field] = $defaults[$field];
            }
        }

        return $result;
    }

    /**
     * Returns validation errors, keyed by field name.
     *
     * @return array<string, string[]>
     */
    public function errors(): array
    {
        if (!$this->validated) {
            $this->runValidation();
        }
        return $this->errors;
    }

    /**
     * Get a single input value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Get all raw input data.
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * Sends a 422 JSON response with validation errors and terminates.
     * Convenience method for use in controllers.
     */
    public function sendErrorResponse(): never
    {
        if (!headers_sent()) {
            http_response_code(422);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'error'   => 'Validation failed',
            'errors'  => $this->errors(),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ─── Private ─────────────────────────────────────────────────────────────

    private function extractInput(): array
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $data = [];

        // GET parameters
        if ($method === 'GET') {
            $data = $_GET;
        }

        // POST form data
        if (!empty($_POST)) {
            $data = array_merge($data, $_POST);
        }

        // JSON body (for REST endpoints)
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            if ($raw) {
                $json = json_decode($raw, true);
                if (is_array($json)) {
                    $data = array_merge($data, $json);
                }
            }
        }

        // Apply defaults for missing fields
        foreach ($this->defaults() as $key => $default) {
            if (!array_key_exists($key, $data)) {
                $data[$key] = $default;
            }
        }

        return $data;
    }

    private function runValidation(): void
    {
        $this->validated = true;
        $this->errors = [];

        /** @var \API\Security\Validator $validator */
        $validator = app('validator');
        $validator->validate($this->data, $this->rules());
        $this->errors = $validator->getErrors();
    }
}
