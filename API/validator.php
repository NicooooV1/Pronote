<?php
/**
 * Système de validation centralisé
 */

class Validator {
    private $data;
    private $errors = [];
    private $rules = [
        'required' => 'Le champ %s est requis',
        'email' => 'Le champ %s doit être un email valide',
        'min' => 'Le champ %s doit contenir au moins %d caractères',
        'max' => 'Le champ %s ne peut pas dépasser %d caractères',
        'numeric' => 'Le champ %s doit être numérique',
        'date' => 'Le champ %s doit être une date valide'
    ];

    public function __construct($data) {
        $this->data = $data;
    }

    public function validate($rules) {
        foreach ($rules as $field => $fieldRules) {
            $value = $this->data[$field] ?? null;
            
            foreach ($fieldRules as $rule) {
                $this->applyRule($field, $value, $rule);
            }
        }
        
        return empty($this->errors);
    }

    private function applyRule($field, $value, $rule) {
        if (is_array($rule)) {
            $ruleName = $rule[0];
            $params = array_slice($rule, 1);
        } else {
            $ruleName = $rule;
            $params = [];
        }

        switch ($ruleName) {
            case 'required':
                if (empty($value)) {
                    $this->errors[$field][] = sprintf($this->rules['required'], $field);
                }
                break;
            
            case 'email':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->errors[$field][] = sprintf($this->rules['email'], $field);
                }
                break;
            
            case 'min':
                if (strlen($value) < $params[0]) {
                    $this->errors[$field][] = sprintf($this->rules['min'], $field, $params[0]);
                }
                break;
            
            case 'max':
                if (strlen($value) > $params[0]) {
                    $this->errors[$field][] = sprintf($this->rules['max'], $field, $params[0]);
                }
                break;
            
            case 'numeric':
                if (!is_numeric($value)) {
                    $this->errors[$field][] = sprintf($this->rules['numeric'], $field);
                }
                break;
        }
    }

    public function getErrors() {
        return $this->errors;
    }

    public function getFirstError($field) {
        return $this->errors[$field][0] ?? null;
    }
}
