<?php
namespace API\Security;

/**
 * Validateur de données (amélioré)
 */
class Validator
{
    protected $errors = [];

    /**
     * Valide des données selon des règles
     */
    public function validate($data, $rules)
    {
        $this->errors = [];

        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            $ruleList = is_array($rule) ? $rule : explode('|', $rule);

            foreach ($ruleList as $r) {
                $this->applyRule($field, $value, $r, $data);
            }
        }

        return empty($this->errors);
    }

    /**
     * Applique une règle de validation
     */
    protected function applyRule($field, $value, $rule, $allData = [])
    {
        // Required - ne doit pas être null ou chaîne vide
        if ($rule === 'required') {
            if ($value === null || $value === '') {
                $this->errors[$field][] = "Le champ $field est requis";
                return;
            }
        }

        // Numeric
        if ($rule === 'numeric' && $value !== null && $value !== '') {
            if (!is_numeric($value)) {
                $this->errors[$field][] = "Le champ $field doit être numérique";
            }
        }

        // Integer
        if ($rule === 'integer' && $value !== null && $value !== '') {
            if (!filter_var($value, FILTER_VALIDATE_INT)) {
                $this->errors[$field][] = "Le champ $field doit être un entier";
            }
        }

        // Email
        if ($rule === 'email' && $value !== null && $value !== '') {
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $this->errors[$field][] = "Le champ $field doit être une adresse email valide";
            }
        }

        // Min length
        if (strpos($rule, 'min:') === 0 && $value !== null) {
            $min = (int)substr($rule, 4);
            if (strlen((string)$value) < $min) {
                $this->errors[$field][] = "Le champ $field doit contenir au moins $min caractères";
            }
        }

        // Max length
        if (strpos($rule, 'max:') === 0 && $value !== null) {
            $max = (int)substr($rule, 4);
            if (strlen((string)$value) > $max) {
                $this->errors[$field][] = "Le champ $field ne peut pas dépasser $max caractères";
            }
        }

        // In (liste de valeurs autorisées)
        if (strpos($rule, 'in:') === 0 && $value !== null && $value !== '') {
            $allowed = explode(',', substr($rule, 3));
            if (!in_array($value, $allowed, true)) {
                $this->errors[$field][] = "Le champ $field doit être parmi : " . implode(', ', $allowed);
            }
        }

        // Between (pour nombres)
        if (strpos($rule, 'between:') === 0 && $value !== null && $value !== '') {
            list($min, $max) = explode(',', substr($rule, 8));
            if (!is_numeric($value) || $value < $min || $value > $max) {
                $this->errors[$field][] = "Le champ $field doit être entre $min et $max";
            }
        }

        // Date
        if ($rule === 'date' && $value !== null && $value !== '') {
            $d = \DateTime::createFromFormat('Y-m-d', $value);
            if (!$d || $d->format('Y-m-d') !== $value) {
                $this->errors[$field][] = "Le champ $field doit être une date valide (Y-m-d)";
            }
        }

        // Confirmed (ex: password_confirmation)
        if ($rule === 'confirmed') {
            $confirmField = $field . '_confirmation';
            if (!isset($allData[$confirmField]) || $allData[$confirmField] !== $value) {
                $this->errors[$field][] = "Le champ $field ne correspond pas à sa confirmation";
            }
        }

        // Regex pattern
        if (strpos($rule, 'regex:') === 0 && $value !== null && $value !== '') {
            $pattern = substr($rule, 6);
            if (!preg_match($pattern, $value)) {
                $this->errors[$field][] = "Le champ $field ne respecte pas le format requis";
            }
        }

        // URL
        if ($rule === 'url' && $value !== null && $value !== '') {
            if (!filter_var($value, FILTER_VALIDATE_URL)) {
                $this->errors[$field][] = "Le champ $field doit être une URL valide";
            }
        }

        // Boolean
        if ($rule === 'boolean' && $value !== null && $value !== '') {
            if (!in_array($value, [true, false, 0, 1, '0', '1'], true)) {
                $this->errors[$field][] = "Le champ $field doit être un booléen";
            }
        }
    }

    /**
     * Retourne les erreurs de validation
     */
    public function errors()
    {
        return $this->errors;
    }

    /**
     * Retourne la première erreur
     */
    public function firstError()
    {
        foreach ($this->errors as $fieldErrors) {
            return $fieldErrors[0] ?? null;
        }
        return null;
    }
}
