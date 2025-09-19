<?php
namespace App\Service;

class LuckyNumbersService
{
    /**
     * Supported lottery presets.
     */
    public const GAMES = [
        'euromillions' => ['pool' => 50, 'picks' => 5, 'bonus_pool' => 12, 'bonus_picks' => 2, 'label' => 'EuroMillions'],
        'swisslotto'   => ['pool' => 42, 'picks' => 6, 'bonus_pool' => 6,  'bonus_picks' => 1, 'label' => 'Swiss Lotto'],
        'powerball'    => ['pool' => 69, 'picks' => 5, 'bonus_pool' => 26, 'bonus_picks' => 1, 'label' => 'Powerball'],
        'eurojackpot'  => ['pool' => 50, 'picks' => 5, 'bonus_pool' => 12, 'bonus_picks' => 2, 'label' => 'EuroJackpot'],
        'custom'       => ['pool' => 60, 'picks' => 6, 'bonus_pool' => null, 'bonus_picks' => 0, 'label' => 'Custom'],
    ];

    /**
     * Generate a draw (unique numbers per pool). If $seed is provided, output is deterministic.
     * @return array{game:string,label:string,numbers:int[],bonus:int[]|null,seed:?string}
     */
    public function draw(
        string $game,
        ?int $pool = null,
        ?int $picks = null,
        ?int $bonusPool = null,
        ?int $bonusPicks = null,
        ?string $seed = null
    ): array {
        $g = self::GAMES[$game] ?? null;
        if ($g === null && $game !== 'custom') {
            throw new \InvalidArgumentException("Unsupported game: {$game}");
        }

        $pool       = $pool       ?? ($g['pool'] ?? null);
        $picks      = $picks      ?? ($g['picks'] ?? null);
        $bonusPool  = $bonusPool  ?? ($g['bonus_pool'] ?? null);
        $bonusPicks = $bonusPicks ?? ($g['bonus_picks'] ?? 0);
        $label      = $g['label'] ?? 'Custom';

        if (!$pool || !$picks || $picks > $pool) {
            throw new \InvalidArgumentException('Invalid pool/picks');
        }
        if ($bonusPicks && (!$bonusPool || $bonusPicks > $bonusPool)) {
            throw new \InvalidArgumentException('Invalid bonus pool/picks');
        }

        $rng = $this->rng($seed);

        $numbers = $this->pickUnique($rng, $pool, $picks);
        sort($numbers);

        $bonus = null;
        if ($bonusPicks && $bonusPool) {
            $bonus = $this->pickUnique($rng, $bonusPool, $bonusPicks);
            sort($bonus);
        }

        return [
            'game'    => $game,
            'label'   => $label,
            'numbers' => $numbers,
            'bonus'   => $bonus,
            'seed'    => $seed,
        ];
    }

    /** Deterministic RNG (SplitMix64) if seed provided; else random_int(). */
    private function rng(?string $seed): \Closure
    {
        if ($seed === null || $seed === '') {
            return function (int $n): int { return random_int(1, $n); };
        }

        // Derive 64-bit state from seed (portable)
        $h = hash('sha256', $seed, true);                 // 32 bytes
        $unpacked = @unpack('J', substr($h, 0, 8));       // 64-bit unsigned
        $state = $unpacked ? $unpacked[1] : 0;

        $next = function () use (&$state): int {
            $state = ($state + 0x9E3779B97F4A7C15) & 0xFFFFFFFFFFFFFFFF;
            $z = $state;
            $z = (($z ^ ($z >> 30)) * 0xBF58476D1CE4E5B9) & 0xFFFFFFFFFFFFFFFF;
            $z = (($z ^ ($z >> 27)) * 0x94D049BB133111EB) & 0xFFFFFFFFFFFFFFFF;
            $z ^= ($z >> 31);
            return (int) ($z & 0x7FFFFFFF);
        };

        return function (int $n) use ($next): int {
            return ($next() % $n) + 1; // 1..n
        };
    }

    /** Pick k unique integers from 1..n. */
    private function pickUnique(callable $rng, int $n, int $k): array
    {
        $set = [];
        while (count($set) < $k) {
            $x = $rng($n);
            $set[$x] = true;
        }
        return array_keys($set);
    }
}