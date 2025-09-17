<?php
/**
 * API Validator Helper
 */
class ApiValidator {
    private $errors = [];
    
    public function allowedValues($params, $allowed) {
        foreach ($params as $key => $value) {
            if (isset($allowed[$key]) && !in_array($value, $allowed[$key])) {
                $this->errors[] = "Invalid value for {$key}: {$value}";
                return false;
            }
        }
        return true;
    }
    
    public function getErrors() {
        return $this->errors;
    }
}
