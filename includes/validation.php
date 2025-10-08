<?php
// ==================== VALIDATION FUNCTIONS ====================

class Validator {
    private $errors = [];
    private $data = [];
    
    public function __construct($data) {
        $this->data = $data;
    }
    
    /**
     * Validate data against rules
     */
    public function validate($rules) {
        foreach ($rules as $field => $ruleString) {
            $rules = explode('|', $ruleString);
            
            foreach ($rules as $rule) {
                $this->applyRule($field, $rule);
            }
        }
        
        return empty($this->errors);
    }
    
    /**
     * Apply validation rule
     */
    private function applyRule($field, $rule) {
        // Parse rule and parameters
        if (strpos($rule, ':') !== false) {
            list($ruleName, $parameter) = explode(':', $rule, 2);
        } else {
            $ruleName = $rule;
            $parameter = null;
        }
        
        $value = $this->data[$field] ?? null;
        
        switch ($ruleName) {
            case 'required':
                if (empty($value) && $value !== '0') {
                    $this->addError($field, "$field is required");
                }
                break;
                
            case 'email':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, "$field must be a valid email");
                }
                break;
                
            case 'numeric':
                if (!empty($value) && !is_numeric($value)) {
                    $this->addError($field, "$field must be numeric");
                }
                break;
                
            case 'integer':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_INT)) {
                    $this->addError($field, "$field must be an integer");
                }
                break;
                
            case 'min':
                if (!empty($value)) {
                    if (is_numeric($value) && $value < $parameter) {
                        $this->addError($field, "$field must be at least $parameter");
                    } elseif (is_string($value) && strlen($value) < $parameter) {
                        $this->addError($field, "$field must be at least $parameter characters");
                    }
                }
                break;
                
            case 'max':
                if (!empty($value)) {
                    if (is_numeric($value) && $value > $parameter) {
                        $this->addError($field, "$field must not exceed $parameter");
                    } elseif (is_string($value) && strlen($value) > $parameter) {
                        $this->addError($field, "$field must not exceed $parameter characters");
                    }
                }
                break;
                
            case 'between':
                list($min, $max) = explode(',', $parameter);
                if (!empty($value)) {
                    if (is_numeric($value) && ($value < $min || $value > $max)) {
                        $this->addError($field, "$field must be between $min and $max");
                    }
                }
                break;
                
            case 'in':
                $allowed = explode(',', $parameter);
                if (!empty($value) && !in_array($value, $allowed)) {
                    $this->addError($field, "$field must be one of: " . implode(', ', $allowed));
                }
                break;
                
            case 'unique':
                $this->validateUnique($field, $value, $parameter);
                break;
                
            case 'exists':
                $this->validateExists($field, $value, $parameter);
                break;
                
            case 'date':
                if (!empty($value) && !$this->isValidDate($value)) {
                    $this->addError($field, "$field must be a valid date");
                }
                break;
                
            case 'phone':
                if (!empty($value) && !isValidPhone($value)) {
                    $this->addError($field, "$field must be a valid phone number");
                }
                break;
                
            case 'pin':
                if (!empty($value) && !isValidPIN($value)) {
                    $this->addError($field, "$field must be a 4-digit PIN");
                }
                break;
                
            case 'barcode':
                if (!empty($value) && !isValidBarcode($value)) {
                    $this->addError($field, "$field must be a valid barcode");
                }
                break;
                
            case 'alpha':
                if (!empty($value) && !preg_match('/^[a-zA-Z]+$/', $value)) {
                    $this->addError($field, "$field must contain only letters");
                }
                break;
                
            case 'alpha_num':
                if (!empty($value) && !preg_match('/^[a-zA-Z0-9]+$/', $value)) {
                    $this->addError($field, "$field must contain only letters and numbers");
                }
                break;
                
            case 'alpha_dash':
                if (!empty($value) && !preg_match('/^[a-zA-Z0-9\-\_]+$/', $value)) {
                    $this->addError($field, "$field must contain only letters, numbers, dashes and underscores");
                }
                break;
                
            case 'url':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->addError($field, "$field must be a valid URL");
                }
                break;
                
            case 'ip':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_IP)) {
                    $this->addError($field, "$field must be a valid IP address");
                }
                break;
                
            case 'json':
                if (!empty($value)) {
                    json_decode($value);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $this->addError($field, "$field must be valid JSON");
                    }
                }
                break;
                
            case 'regex':
                if (!empty($value) && !preg_match($parameter, $value)) {
                    $this->addError($field, "$field format is invalid");
                }
                break;
                
            case 'confirmed':
                $confirmField = $field . '_confirmation';
                if (!empty($value) && (!isset($this->data[$confirmField]) || $value !== $this->data[$confirmField])) {
                    $this->addError($field, "$field confirmation does not match");
                }
                break;
                
            case 'same':
                if (!empty($value) && (!isset($this->data[$parameter]) || $value !== $this->data[$parameter])) {
                    $this->addError($field, "$field must match $parameter");
                }
                break;
                
            case 'different':
                if (!empty($value) && isset($this->data[$parameter]) && $value === $this->data[$parameter]) {
                    $this->addError($field, "$field must be different from $parameter");
                }
                break;
                
            case 'before':
                if (!empty($value) && !$this->isDateBefore($value, $parameter)) {
                    $this->addError($field, "$field must be before $parameter");
                }
                break;
                
            case 'after':
                if (!empty($value) && !$this->isDateAfter($value, $parameter)) {
                    $this->addError($field, "$field must be after $parameter");
                }
                break;
                
            case 'file':
                if (!empty($value) && !isset($_FILES[$field])) {
                    $this->addError($field, "$field must be a file");
                }
                break;
                
            case 'image':
                if (!empty($value) && isset($_FILES[$field])) {
                    $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
                    $result = validateFileUpload($_FILES[$field], $allowedTypes);
                    if (!$result['success']) {
                        $this->addError($field, $result['message']);
                    }
                }
                break;
        }
    }
    
    /**
     * Validate unique in database
     */
    private function validateUnique($field, $value, $parameter) {
        if (empty($value)) return;
        
        global $conn;
        
        // Parse table:column,except_id
        $parts = explode(',', $parameter);
        list($table, $column) = explode(':', $parts[0], 2);
        $exceptId = isset($parts[1]) ? intval($parts[1]) : null;
        
        if ($exceptId) {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM $table WHERE $column = ? AND id != ?");
            $stmt->bind_param("si", $value, $exceptId);
        } else {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM $table WHERE $column = ?");
            $stmt->bind_param("s", $value);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['count'] > 0) {
            $this->addError($field, "$field already exists");
        }
        
        $stmt->close();
    }
    
    /**
     * Validate exists in database
     */
    private function validateExists($field, $value, $parameter) {
        if (empty($value)) return;
        
        global $conn;
        
        // Parse table:column
        list($table, $column) = explode(':', $parameter, 2);
        
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM $table WHERE $column = ?");
        $stmt->bind_param("s", $value);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['count'] === 0) {
            $this->addError($field, "$field does not exist");
        }
        
        $stmt->close();
    }
    
    /**
     * Check if valid date
     */
    private function isValidDate($date, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
    
    /**
     * Check if date is before another
     */
    private function isDateBefore($date1, $date2) {
        return strtotime($date1) < strtotime($date2);
    }
    
    /**
     * Check if date is after another
     */
    private function isDateAfter($date1, $date2) {
        return strtotime($date1) > strtotime($date2);
    }
    
    /**
     * Add error
     */
    private function addError($field, $message) {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }
    
    /**
     * Get all errors
     */
    public function errors() {
        return $this->errors;
    }
    
    /**
     * Get first error for field
     */
    public function first($field) {
        return isset($this->errors[$field]) ? $this->errors[$field][0] : null;
    }
    
    /**
     * Check if has errors
     */
    public function fails() {
        return !empty($this->errors);
    }
    
    /**
     * Check if validation passed
     */
    public function passes() {
        return empty($this->errors);
    }
}

/**
 * Helper function to validate data
 */
function validate($data, $rules) {
    $validator = new Validator($data);
    $validator->validate($rules);
    return $validator;
}

/**
 * Validate product data
 */
function validateProduct($data, $isUpdate = false) {
    $rules = [
        'name' => 'required|max:255',
        'category_id' => 'required|integer|exists:categories:id',
        'selling_price' => 'required|numeric|min:0',
        'cost_price' => 'required|numeric|min:0',
        'stock_quantity' => 'required|integer|min:0',
        'reorder_level' => 'required|integer|min:0',
        'unit' => 'required|in:bottle,case,pack,liter,ml,piece,box'
    ];
    
    if (!$isUpdate || !empty($data['barcode'])) {
        $exceptId = $isUpdate && isset($data['id']) ? $data['id'] : null;
        $rules['barcode'] = 'barcode|unique:products:barcode,' . $exceptId;
    }
    
    return validate($data, $rules);
}

/**
 * Validate sale data
 */
function validateSale($data) {
    $rules = [
        'items' => 'required',
        'total_amount' => 'required|numeric|min:0',
        'amount_paid' => 'required|numeric|min:0',
        'payment_method' => 'required|in:cash,mpesa,mpesa_till,card'
    ];
    
    if ($data['payment_method'] === 'mpesa' || $data['payment_method'] === 'mpesa_till') {
        $rules['mpesa_reference'] = 'required|max:100';
    }
    
    return validate($data, $rules);
}

/**
 * Validate user data
 */
function validateUser($data, $isUpdate = false) {
    $rules = [
        'name' => 'required|max:255',
        'role' => 'required|in:owner,seller',
        'status' => 'required|in:active,inactive'
    ];
    
    $exceptId = $isUpdate && isset($data['id']) ? $data['id'] : null;
    $rules['pin_code'] = 'required|pin|unique:users:pin_code,' . $exceptId;
    
    if (!empty($data['email'])) {
        $rules['email'] = 'email|max:255|unique:users:email,' . $exceptId;
    }
    
    if (!empty($data['phone'])) {
        $rules['phone'] = 'phone|unique:users:phone,' . $exceptId;
    }
    
    return validate($data, $rules);
}

/**
 * Validate customer data
 */
function validateCustomer($data, $isUpdate = false) {
    $rules = [
        'name' => 'required|max:255'
    ];
    
    $exceptId = $isUpdate && isset($data['id']) ? $data['id'] : null;
    
    if (!empty($data['phone'])) {
        $rules['phone'] = 'phone|unique:customers:phone,' . $exceptId;
    }
    
    if (!empty($data['email'])) {
        $rules['email'] = 'email|max:255|unique:customers:email,' . $exceptId;
    }
    
    if (!empty($data['credit_limit'])) {
        $rules['credit_limit'] = 'numeric|min:0';
    }
    
    return validate($data, $rules);
}

/**
 * Validate supplier data
 */
function validateSupplier($data, $isUpdate = false) {
    $rules = [
        'name' => 'required|max:255'
    ];
    
    if (!empty($data['phone'])) {
        $rules['phone'] = 'phone';
    }
    
    if (!empty($data['email'])) {
        $rules['email'] = 'email|max:255';
    }
    
    return validate($data, $rules);
}

/**
 * Validate expense data
 */
function validateExpense($data) {
    $rules = [
        'category' => 'required|max:255',
        'amount' => 'required|numeric|min:0.01',
        'expense_date' => 'required|date',
        'description' => 'required'
    ];
    
    return validate($data, $rules);
}

/**
 * Validate category data
 */
function validateCategory($data, $isUpdate = false) {
    $rules = [
        'name' => 'required|max:255'
    ];
    
    $exceptId = $isUpdate && isset($data['id']) ? $data['id'] : null;
    $rules['name'] .= '|unique:categories:name,' . $exceptId;
    
    return validate($data, $rules);
}