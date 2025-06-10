<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    // public function test_basic_test()
    // {
    //     $response = $this->get('/');

    //     $response->assertStatus(200);
    // }

    public function test_basic_test()
    {
        $adminRole = \App\Models\Role::create(['name' => 'Administrator']);
        $menu = \App\Models\Menu::create(['name' => 'Home', 'url' => '/']);
        \App\Models\Access::create(['role_id' => $adminRole->id, 'menu_id' => $menu->id]);
        \App\Models\Admin::create(['role_id' => $adminRole->id]);

        $response = $this->get('/');
        $response->assertStatus(200);
    }
}
