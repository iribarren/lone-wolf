<?php

declare(strict_types=1);

namespace App\Journal\Domain;

/**
 * Denormalized copy of a consulted oracle result ({oracleTitle, resultText}).
 * Stored with the entry so journal history stays readable even if the oracle
 * is later retired or edited (data-model.md Oracles Context note).
 */
final readonly class OracleSnapshot
{
    public function __construct(
        public string $oracleTitle,
        public string $resultText,
    ) {
        if (trim($this->oracleTitle) === '' || trim($this->resultText) === '') {
            throw new \InvalidArgumentException('An oracle snapshot needs both the table title and the result text.');
        }
    }

    /** @return array{oracleTitle: string, resultText: string} */
    public function toArray(): array
    {
        return ['oracleTitle' => $this->oracleTitle, 'resultText' => $this->resultText];
    }

    /**
     * @param array{oracleTitle?: mixed, resultText?: mixed} $payload
     */
    public static function fromArray(array $payload): self
    {
        $title = $payload['oracleTitle'] ?? '';
        $text = $payload['resultText'] ?? '';

        if (!is_string($title) || !is_string($text)) {
            throw new \InvalidArgumentException('An oracle snapshot payload must contain string fields.');
        }

        return new self($title, $text);
    }
}
