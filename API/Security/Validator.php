<?php
namespace API\Security;

/**
 * Validateur de données
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
                $this->applyRule($field, $value, $r);
            }
        }

        return empty($this->errors);
    }

    /**
     * Applique une règle de validation
     */
    protected function applyRule($field, $value, $rule)
    {
        if ($rule === 'required' && empty($value)) {
            $this->errors[$field][] = "Le champ $field est requis";
        }

        if (strpos($rule, 'min:') === 0) {
            $min = (int)substr($rule, 4);
            if (strlen($value) < $min) {
                $this->errors[$field][] = "Le champ $field doit contenir au moins $min caractères";
            }
        }

        if (strpos($rule, 'max:') === 0) {
            $max = (int)substr($rule, 4);
            if (strlen($value) > $max) {
                $this->errors[$field][] = "Le champ $field ne peut pas dépasser $max caractères";
            }
        }

        if ($rule === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field][] = "Le champ $field doit être une adresse email valide";
        }
    }

    /**
     * Retourne les erreurs de validation
     */
    public function errors()
    {
        return $this->errors;
    }
}
