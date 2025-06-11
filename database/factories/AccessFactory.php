<?php

namespace Database\Factories;

use App\Models\Access;
use App\Models\Menu;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccessFactory extends Factory
{
    protected $model = Access::class;

    public function definition()
    {
        return [
            'role_id' => function () {
                return Role::factory()->create()->id;
            },
            'menu_id' => function () {
                return Menu::factory()->create()->id;
            },
            'status' => 2,
        ];
    }
}
