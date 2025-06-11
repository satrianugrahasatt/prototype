<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeDetail;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmployeesControllerTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function test_basic_test()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Membuat pengguna untuk autentikasi
        $this->user = User::factory()->create([
            'role_id' => Role::factory()->create()->id,
        ]);
        $this->user->employee = Employee::factory()->create([
            'user_id' => $this->user->id,
            'department_id' => Department::factory()->create()->id,
            'position_id' => Position::factory()->create()->id,
        ]);
    }

    /** @test */
    public function it_can_display_employees_index_page()
    {
        $employees = Employee::factory()->count(3)->create();

        $response = $this->actingAs($this->user)->get(route('employees-data'));

        $response->assertStatus(200);
        $response->assertViewIs('pages.employees-data');
        $response->assertViewHas('employees', function ($viewEmployees) use ($employees) {
            return $viewEmployees->count() === $employees->count();
        });
    }

    /** @test */
    public function it_can_display_create_employee_form()
    {
        $roles = Role::factory()->count(2)->create();
        $departments = Department::factory()->count(2)->create();
        $positions = Position::factory()->count(2)->create();

        $response = $this->actingAs($this->user)->get(route('employees-data.create'));

        $response->assertStatus(200);
        $response->assertViewIs('pages.employees-data_create');
        $response->assertViewHas('roles', fn ($viewRoles) => $viewRoles->count() === $roles->count());
        $response->assertViewHas('departments', fn ($viewDepartments) => $viewDepartments->count() === $departments->count());
        $response->assertViewHas('positions', fn ($viewPositions) => $viewPositions->count() === $positions->count());
    }


    /** @test */
    public function it_can_display_employee_details()
    {
        $employee = Employee::factory()->create([
            'user_id' => User::factory()->create()->id,
            'department_id' => Department::factory()->create()->id,
            'position_id' => Position::factory()->create()->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('employees-data.show', $employee));

        $response->assertStatus(200);
        $response->assertViewIs('pages.employees-data_show');
        $response->assertViewHas('employee', fn ($viewEmployee) => $viewEmployee->id === $employee->id);
    }

    /** @test */
    public function it_can_display_edit_employee_form()
    {
        $employee = Employee::factory()->create([
            'user_id' => User::factory()->create()->id,
            'department_id' => Department::factory()->create()->id,
            'position_id' => Position::factory()->create()->id,
        ]);
        $roles = Role::factory()->count(2)->create();
        $departments = Department::factory()->count(2)->create();
        $positions = Position::factory()->count(2)->create();

        $response = $this->actingAs($this->user)->get(route('employees-data.edit', $employee));

        $response->assertStatus(200);
        $response->assertViewIs('pages.employees-data_edit');
        $response->assertViewHas('employee', fn ($viewEmployee) => $viewEmployee->id === $employee->id);
        $response->assertViewHas('roles', fn ($viewRoles) => $viewRoles->count() === $roles->count());
        $response->assertViewHas('departments', fn ($viewDepartments) => $viewDepartments->count() === $departments->count());
        $response->assertViewHas('positions', fn ($viewPositions) => $viewPositions->count() === $positions->count());
    }

    
    /** @test */
    public function it_can_delete_employee()
    {
        $employee = Employee::factory()->create([
            'user_id' => User::factory()->create()->id,
            'department_id' => Department::factory()->create()->id,
            'position_id' => Position::factory()->create()->id,
        ]);

        $response = $this->actingAs($this->user)->delete(route('employees-data.destroy', $employee));

        $response->assertRedirect(route('employees-data'));
        $response->assertSessionHas('status', 'Successfully deleted an employee.');

        $this->assertDatabaseMissing('users', ['id' => $employee->user_id]);
        $this->assertDatabaseMissing('employees', ['id' => $employee->id]);
        $this->assertDatabaseHas('logs', [
            'description' => $this->user->employee->name." deleted an employee named '{$employee->name}'",
        ]);
    }

    /** @test */
    public function it_can_display_print_employees_page()
    {
        $employees = Employee::factory()->count(3)->create();

        $response = $this->actingAs($this->user)->get(route('employees-data.print'));

        $response->assertStatus(200);
        $response->assertViewIs('pages.employees-data_print');
        $response->assertViewHas('employees', fn ($viewEmployees) => $viewEmployees->count() === $employees->count());
    }

    /** @test */
    public function it_restricts_access_to_unauthenticated_users()
    {
        $response = $this->get(route('employees-data'));

        $response->assertRedirect('/login');
    }
}
