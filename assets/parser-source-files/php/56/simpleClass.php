<?php
class Calculator {
    const TAX_RATE = 0.23;
    
    public function sum(...$numbers) { // Variadic
        return array_sum($numbers);
    }
    
    public function power($base, $exp) {
        return $base ** $exp; // Exponentiation
    }
}

$calc = new Calculator();
$values = [1, 2, 3];
$result = $calc->sum(...$values); // Argument unpacking
