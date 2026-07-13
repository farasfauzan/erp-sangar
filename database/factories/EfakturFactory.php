<?php

namespace Database\Factories;

use App\Models\Efaktur;
use Illuminate\Database\Eloquent\Factories\Factory;

class EfakturFactory extends Factory
{
    protected $model = Efaktur::class;

    public function definition(): array
    {
        return [
            'faktur_number' => '010.' . $this->faker->unique()->numerify('###') . '-' . $this->faker->numerify('######') . '.' . $this->faker->numerify('####'),
            'faktur_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'npwp_penjual' => $this->faker->numerify('##.###.###.#-###.###'),
            'nama_penjual' => $this->faker->company(),
            'npwp_pembeli' => $this->faker->numerify('##.###.###.#-###.###'),
            'nama_pembeli' => $this->faker->company(),
            'dpp' => $this->faker->randomFloat(2, 1000000, 500000000),
            'ppn' => fn($attrs) => round($attrs['dpp'] * 0.11, 2),
            'ppnbm' => 0,
            'status' => $this->faker->randomElement(['draft', 'validated', 'submitted', 'approved', 'rejected']),
            'validation_errors' => null,
            'notes' => null,
            'uploaded_by' => null,
        ];
    }
}
