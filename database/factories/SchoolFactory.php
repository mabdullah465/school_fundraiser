<?php

namespace Database\Factories;

use App\Enums\SchoolStatus;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<School>
 */
class SchoolFactory extends Factory
{
    protected $model = School::class;

    public function definition(): array
    {
        $name = fake()->unique()->company().' High School';

        return [
            'ulid' => (string) Str::ulid(),
            'name' => $name,
            'email' => Str::slug($name, '.').'@example.test',
            'phone' => '+1 555 010 '.fake()->numerify('####'),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->stateAbbr(),
            'country' => 'US',
            'status' => SchoolStatus::Approved,
            'approved_at' => now(),
        ];
    }

    public function status(SchoolStatus $status): static
    {
        return $this->state(fn () => [
            'status' => $status,
            'approved_at' => $status === SchoolStatus::Approved ? now() : null,
            'deactivated_at' => $status === SchoolStatus::Deactivated ? now() : null,
        ]);
    }
}
