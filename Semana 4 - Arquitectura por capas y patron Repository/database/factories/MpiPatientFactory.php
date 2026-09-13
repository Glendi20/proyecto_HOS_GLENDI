<?php

namespace Database\Factories;

use App\Domain\Patients\ValueObjects\PatientUuid;
use App\Infrastructure\Patients\Eloquent\MpiPatientModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MpiPatientModel>
 */
class MpiPatientFactory extends Factory
{
    protected $model = MpiPatientModel::class;

    public function definition(): array
    {
        return [
            'id' => PatientUuid::generate()->value(),
            'full_name_normalized' => mb_strtolower(fake()->firstName().' '.fake()->lastName()),
            'birth_date' => fake()->date(),
            'gender' => fake()->randomElement(['M', 'F', 'otro']),
            'dpi_normalized' => fake()->optional()->numerify('#############'),
            'status' => 'active',
        ];
    }
}
