<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $country = fake()->randomElement(['USA', 'Germany']);
        $base = [
            'name'      => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'salary'    => fake()->randomFloat(2, 40000, 120000),
            'country'   => $country,
        ];

        if ($country === 'USA') {
            $base['ssn'] = fake()->numerify('###-##-####');
            $base['address'] = fake()->streetAddress() . ', ' . fake()->city() . ', ' . fake()->stateAbbr();
            $base['goal'] = null;
            $base['tax_id'] = null;
        } else {
            $base['goal'] = fake()->sentence();
            $base['tax_id'] = 'DE' . fake()->numerify('#########');
            $base['ssn'] = null;
            $base['address'] = null;
        }

        return $base;
    }

    public function usa(): static
    {
        return $this->state(fn (array $attributes) => [
            'country'   => 'USA',
            'ssn'       => fake()->numerify('###-##-####'),
            'address'   => fake()->streetAddress() . ', ' . fake()->city() . ', ' . fake()->stateAbbr(),
            'goal'      => null,
            'tax_id'    => null,
        ]);
    }

    public function germany(): static
    {
        return $this->state(fn (array $attributes) => [
            'country' => 'Germany',
            'goal'    => fake()->sentence(),
            'tax_id'  => 'DE' . fake()->numerify('#########'),
            'ssn'     => null,
            'address' => null,
        ]);
    }
}
