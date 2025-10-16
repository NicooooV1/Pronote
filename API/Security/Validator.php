<?php
/**
 * Input Validator - Validation de données
 * Pattern : Fluent Interface
 */

namespace Pronote\Security;

class Validator {
    private $data = [];
    private $errors = [];
    private $rules = [];
    private $messages = [];
    
    /**
     * Règles de validation disponibles
     */
    private const AVAILABLE_RULES = [
        'required', 'email', 'min', 'max', 'numeric', 
        'integer', 'alpha', 'alphanumeric', 'date', 
        'regex', 'in', 'confirmed', 'unique'
    ];
    
    public function __construct(array $data) {
        $this->data = $data;
    }
    
    /**
     * Définit les règles de validation
     * @param array $rules ['field' => 'required|email|min:5']
     */
    public function rules(array $rules) {
        foreach ($rules as $field => $fieldRules) {
            $this->rules[$field] = is_string($fieldRules) 
                ? explode('|', $fieldRules) 
                : $fieldRules;
        }
        return $this;
    }
    
    /**
     * Messages personnalisés
     */
    public function messages(array $messages) {
        $this->messages = $messages;
        return $this;
    }
    
    /**
     * Lance la validation
     */
    public function validate() {
        foreach ($this->rules as $field => $rules) {
            $value = $this->data[$field] ?? null;
            
            foreach ($rules as $rule) {
                $this->validateRule($field, $value, $rule);
            }
        }
        
        return empty($this->errors);
    }
    
    /**
     * Valide une règle spécifique
     */
    protected function validateRule($field, $value, $rule) {
        // Parser la règle (ex: "min:5" => ['min', '5'])
        $parsedRule = $this->parseRule($rule);
        $ruleName = $parsedRule['name'];
        $parameters = $parsedRule['parameters'];
        
        // Appeler la méthode de validation
        $method = 'validate' . ucfirst($ruleName);
        
        if (!method_exists($this, $method)) {
            throw new \Exception("Validation rule [{$ruleName}] does not exist.");
        }
        
        $passes = $this->$method($field, $value, $parameters);
        
        if (!$passes) {
            $this->addError($field, $ruleName, $parameters);
        }
    }
    
    /**
     * Parse une règle
     */
    protected function parseRule($rule) {
        if (strpos($rule, ':') !== false) {
            list($name, $params) = explode(':', $rule, 2);
            return [
                'name' => $name,
                'parameters' => explode(',', $params)
            ];
        }
        
        return ['name' => $rule, 'parameters' => []];
    }
    
    /**
     * Ajoute une erreur
     */
    protected function addError($field, $rule, $parameters = []) {
        $message = $this->getMessage($field, $rule, $parameters);
        
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        
        $this->errors[$field][] = $message;
    }
    
    /**
     * Récupère le message d'erreur
     */
    protected function getMessage($field, $rule, $parameters = []) {
        $key = "{$field}.{$rule}";
        
        if (isset($this->messages[$key])) {
            return $this->messages[$key];
        }
        
        $defaultMessages = [
            'required' => 'Le champ :field est requis.',
            'email' => 'Le champ :field doit être une adresse email valide.',
            'min' => 'Le champ :field doit contenir au moins :min caractères.',
            'max' => 'Le champ :field ne peut pas dépasser :max caractères.',
            'numeric' => 'Le champ :field doit être un nombre.',
            'integer' => 'Le champ :field doit être un entier.',
            'alpha' => 'Le champ :field ne peut contenir que des lettres.',
            'alphanumeric' => 'Le champ :field ne peut contenir que des lettres et chiffres.',
            'date' => 'Le champ :field doit être une date valide.',
            'regex' => 'Le format du champ :field est invalide.',
            'in' => 'La valeur du champ :field est invalide.',
            'confirmed' => 'La confirmation du champ :field ne correspond pas.',
            'unique' => 'La valeur du champ :field existe déjà.'
        ];
        
        $message = $defaultMessages[$rule] ?? 'Le champ :field est invalide.';
        
        // Remplacer les placeholders
        $message = str_replace(':field', $field, $message);
        foreach ($parameters as $key => $value) {
            $message = str_replace(":{$key}", $value, $message);
        }
        
        if (isset($parameters[0])) {
            $message = str_replace(":{$rule}", $parameters[0], $message);
        }
        
        return $message;
    }
    
    // ==================== RÈGLES DE VALIDATION ====================
    
    protected function validateRequired($field, $value, $parameters) {
        if (is_null($value)) {
            return false;
        }
        
        if (is_string($value) && trim($value) === '') {
            return false;
        }
        
        if (is_array($value) && empty($value)) {
            return false;
        }
        
        return true;
    }
    
    protected function validateEmail($field, $value, $parameters) {
        if (is_null($value) || $value === '') {
            return true; // Required doit gérer ça
        }
        
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    protected function validateMin($field, $value, $parameters) {
        if (is_null($value)) {
            return true;
        }
        
        $min = (int)$parameters[0];
        
        if (is_numeric($value)) {
            return $value >= $min;
        }
        
        return mb_strlen($value) >= $min;
    }
    
    protected function validateMax($field, $value, $parameters) {
        if (is_null($value)) {
            return true;
        }
        
        $max = (int)$parameters[0];
        
        if (is_numeric($value)) {
            return $value <= $max;
        }
        
        return mb_strlen($value) <= $max;
    }
    
    protected function validateNumeric($field, $value, $parameters) {
        if (is_null($value) || $value === '') {
            return true;
        }
        
        return is_numeric($value);
    }
    
    protected function validateInteger($field, $value, $parameters) {
        if (is_null($value) || $value === '') {
            return true;
        }
        
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }
    
    protected function validateAlpha($field, $value, $parameters) {
        if (is_null($value) || $value === '') {
            return true;
        }
        
        return preg_match('/^[a-zA-ZÀ-ÿ\s]+$/', $value) === 1;
    }
    
    protected function validateAlphanumeric($field, $value, $parameters) {
        if (is_null($value) || $value === '') {
            return true;
        }
        
        return preg_match('/^[a-zA-Z0-9À-ÿ\s]+$/', $value) === 1;
    }
    
    protected function validateDate($field, $value, $parameters) {
        if (is_null($value) || $value === '') {
            return true;
        }
        
        return strtotime($value) !== false;
    }
    
    protected function validateRegex($field, $value, $parameters) {
        if (is_null($value) || $value === '') {
            return true;
        }
        
        return preg_match($parameters[0], $value) === 1;
    }
    
    protected function validateIn($field, $value, $parameters) {
        if (is_null($value) || $value === '') {
            return true;
        }
        
        return in_array($value, $parameters, true);
    }
    
    protected function validateConfirmed($field, $value, $parameters) {
        $confirmField = $field . '_confirmation';
        $confirmValue = $this->data[$confirmField] ?? null;
        
        return $value === $confirmValue;
    }
    
    protected function validateUnique($field, $value, $parameters) {
        // Format: unique:table,column,except_id
        if (count($parameters) < 2) {
            throw new \Exception("Unique rule requires table and column parameters.");
        }
        
        $table = $parameters[0];
        $column = $parameters[1];
        $except = $parameters[2] ?? null;
        
        // Cette validation nécessite la connexion DB
        // On laisse une implémentation basique ici
        return true; // TODO: Implémenter avec DB
    }
    
    // ==================== GETTERS ====================
    
    public function errors() {
        return $this->errors;
    }
    
    public function fails() {
        return !empty($this->errors);
    }
    
    public function passes() {
        return empty($this->errors);
    }
    
    public function getFirstError($field) {
        return $this->errors[$field][0] ?? null;
    }
    
    public function getAllErrors() {
        $all = [];
        foreach ($this->errors as $errors) {
            $all = array_merge($all, $errors);
        }
        return $all;
    }
    
    /**
     * Helper statique pour validation rapide
     */
    public static function make(array $data, array $rules, array $messages = []) {
        $validator = new static($data);
        return $validator->rules($rules)->messages($messages);
    }
}
