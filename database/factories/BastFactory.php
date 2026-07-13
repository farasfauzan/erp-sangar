<?php

namespace Database\Factories;

use App\Models\Bast;
use App\Models\Opname;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bast>
 */
class BastFactory extends Factory
{
    protected $model = Bast::class;

    protected static int $counter = 0;

    public function definition(): array
    {
        return [
            'opname_id' => Opname::factory(),
            'bast_number' => 'BAST-' . str_pad(++self::$counter, 5, '0', STR_PAD_LEFT),
            'bast_date' => fake()->dateTimeBetween('-1 month', 'now'),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
