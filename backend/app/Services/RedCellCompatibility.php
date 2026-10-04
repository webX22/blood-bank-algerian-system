<?php

namespace App\Services;

class RedCellCompatibility
{
    public function isCompatible(string $donorType, string $recipientType): bool
    {
        [$donorAbo, $donorRh] = $this->split($donorType);
        [$recipientAbo, $recipientRh] = $this->split($recipientType);

        $aboCompatible = $donorAbo === 'O'
            || $recipientAbo === 'AB'
            || $donorAbo === $recipientAbo
            || (in_array($donorAbo, ['A', 'B'], true) && $recipientAbo === 'AB');
        $rhCompatible = $donorRh === '-' || $recipientRh === '+';

        return $aboCompatible && $rhCompatible;
    }

    /**
     * @return array{string, string}
     */
    private function split(string $type): array
    {
        $type = strtoupper(trim($type));

        if (! preg_match('/^(A|B|AB|O)([+-])$/', $type, $matches)) {
            throw new \InvalidArgumentException('Blood type must use ABO and Rh notation, such as O-.');
        }

        return [$matches[1], $matches[2]];
    }
}
