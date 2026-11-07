<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\ProposalBreak;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProposalBreak>
 */
class ProposalBreakFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'application_id' => Application::factory(),
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ];
    }
}
