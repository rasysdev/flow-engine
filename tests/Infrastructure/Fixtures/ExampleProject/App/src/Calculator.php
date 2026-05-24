<?php

namespace App;

/**
 * Calculadora simples para testes do Flow Engine.
 * 
 * Esta classe é usada como fixture para validar:
 * - Análise de métodos públicos
 * - Introspecção de parâmetros
 * - Execução guiada
 */
final class Calculator
{
    /**
     * Soma dois números inteiros.
     * 
     * @param int $a Primeiro número
     * @param int $b Segundo número
     * @return int Resultado da soma
     */
    public function sum(int $a, int $b): int
    {
        return $a + $b;
    }

    /**
     * Subtrai dois números inteiros.
     * 
     * @param int $a Minuendo
     * @param int $b Subtraendo
     * @return int Resultado da subtração
     */
    public function subtract(int $a, int $b): int
    {
        return $a - $b;
    }

    /**
     * Multiplica dois números inteiros.
     * 
     * @param int $a Primeiro fator
     * @param int $b Segundo fator
     * @return int Resultado da multiplicação
     */
    public function multiply(int $a, int $b): int
    {
        return $a * $b;
    }

    /**
     * Divide dois números (inteiros ou decimais).
     * 
     * @param float $a Dividendo
     * @param float $b Divisor
     * @return float Resultado da divisão
     * @throws \InvalidArgumentException Se divisor for zero
     */
    public function divide(float $a, float $b): float
    {
        if ($b === 0.0) {
            throw new \InvalidArgumentException('Division by zero');
        }
        
        return $a / $b;
    }

    /**
     * Calcula potência.
     * 
     * @param int $base Base
     * @param int $exponent Expoente (default: 2)
     * @return int Resultado
     */
    public function power(int $base, int $exponent = 2): int
    {
        return (int) pow($base, $exponent);
    }

    /**
     * Método privado (não deve aparecer no Flow).
     */
    private function privateHelper(): string
    {
        return 'internal';
    }
}