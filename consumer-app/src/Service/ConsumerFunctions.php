<?php
namespace App\Service;

class ConsumerFunctions
{
    public function sayHello(string $name): string
    {
        return "Bonjour, $name !";
    }

    public function add(float $a, float $b): float
    {
        return $a + $b;
    }

    public function listFunctions(): array
    {
        return [
            "sayHello" => "Greets a person by name",
            "add"      => "Adds two numbers",
        ];
    }
}
