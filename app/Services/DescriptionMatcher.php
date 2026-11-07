<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Compara a descrição escrita à mão com a que vem do extrato do cartão, que traz
 * prefixos e cortes do adquirente ("uber" e "DL*UberRides", "tt burguer" e
 * "RESERVA TT BURGER - AR").
 */
class DescriptionMatcher
{
    /** Tamanho mínimo de palavra que identifica um estabelecimento. */
    private const MEANINGFUL_WORD = 4;

    /** Tamanho mínimo de palavra de uma descrição cadastrada ("Google One"). */
    private const REGISTERED_WORD = 3;

    /** As duas descrições têm alguma palavra relevante em comum? */
    public function similar(string $first, string $second): bool
    {
        $others = $this->words($second, self::MEANINGFUL_WORD);

        foreach ($this->words($first, self::MEANINGFUL_WORD) as $word) {
            foreach ($others as $other) {
                if ($this->sameWord($word, $other)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * O extrato traz todas as palavras do nome cadastrado? Mais rígido que
     * similar(): aqui o valor não ajuda a confirmar, e "Google One" não pode casar
     * com "Google YouTube".
     */
    public function containsAllWords(string $registered, string $statement): bool
    {
        $words = $this->words($registered, self::REGISTERED_WORD);
        $statementWords = $this->words($statement, self::REGISTERED_WORD);

        if ($words === []) {
            return false;
        }

        foreach ($words as $word) {
            $found = false;

            foreach ($statementWords as $statementWord) {
                $found = $found || $this->sameWord($word, $statementWord);
            }

            if (! $found) {
                return false;
            }
        }

        return true;
    }

    private function words(string $text, int $minLength): array
    {
        $normalized = preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($text)));

        return array_values(array_filter(
            explode(' ', $normalized),
            fn (string $word) => strlen($word) >= $minLength
        ));
    }

    private function sameWord(string $first, string $second): bool
    {
        if (str_contains($first, $second) || str_contains($second, $first)) {
            return true;
        }

        // Um erro de digitação só conta em palavra longa: "burguer" e "burger".
        return min(strlen($first), strlen($second)) >= 5 && levenshtein($first, $second) <= 1;
    }
}
