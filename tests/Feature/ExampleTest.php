<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Role;
use App\Models\Department;
use App\Models\Position;
use App\Models\Employee;
use App\Models\EmployeeDetail;
use App\Models\EmployeeLeave;
use App\Models\Log;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;

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
    public function it_can_store_new_employee()
    {
        Storage::fake('public');
        $role = Role::factory()->create();
        $department = Department::factory()->create();
        $position = Position::factory()->create();

        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'role_id' => $role->id,
            'start_of_contract' => '2025-01-01',
            'end_of_contract' => '2026-01-01',
            'department_id' => $department->id,
            'position_id' => $position->id,
            'identity_number' => '123456789',
            'gender' => 'male',
            'date_of_birth' => '1990-01-01',
            'phone' => '1234567890',
            'address' => '123 Main St',
            'photo' => UploadedFile::fake()->image('photo.jpg'),
            'cv' => UploadedFile::fake()->create('cv.pdf'),
            'last_education' => 'Bachelor',
            'gpa' => 3.5,
            'work_experience_in_years' => 5,
        ];

        $response = $this->actingAs($this->user)->post(route('employees-data.store'), $data);

        $response->assertRedirect(route('employees-data'));
        $response->assertSessionHas('status', 'Successfully created an employee.');

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'role_id' => $role->id,
        ]);
        $this->assertDatabaseHas('employees', [
            'name' => 'John Doe',
            'department_id' => $department->id,
            'position_id' => $position->id,
        ]);
        $this->assertDatabaseHas('employee_details', [
            'identity_number' => '123456789',
            'email' => 'john@example.com',
        ]);
        $this->assertDatabaseHas('employee_leaves', [
            'leaves_quota' => 12,
            'used_leaves' => 0,
        ]);
        $this->assertDatabaseHas('logs', [
            'description' => $this->user->employee->name . " created an employee named 'John Doe'",
        ]);

        Storage::disk('public')->assertExists('photos/photo.jpg');
        Storage::disk('public')->assertExists('cvs/cv.pdf');
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
    public function it_can_update_employee()
    {
        Storage::fake('public');
        $employee = Employee::factory()->create([
            'user_id' => User::factory()->create()->id,
            'department_id' => Department::factory()->create()->id,
            'position_id' => Position::factory()->create()->id,
        ]);
        EmployeeDetail::factory()->create(['employee_id' => $employee->id]);
        $newRole = Role::factory()->create();
        $newDepartment = Department::factory()->create();
        $newPosition = Position::factory()->create();

        $data = [
            'user_id' => $employee->user_id,
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'newpassword123',
            'role_id' => $newRole->id,
            'start_of_contract' => '2025-02-01',
            'end_of_contract' => '2026-02-01',
            'department_id' => $newDepartment->id,
            'position_id' => $newPosition->id,
            'identity_number' => '987654321',
            'gender' => 'female',
            'date_of_birth' => '1995-01-01',
            'phone' => '0987654321',
            'address' => '456 Main St',
            'photo' => UploadedFile::fake()->image('new_photo.jpg'),
            'cv' => UploadedFile::fake()->create('new_cv.pdf'),
            'last_education' => 'Master',
            'gpa' => 3.8,
            'work_experience_in_years' => 7,
            'is_active' => 1,
        ];

        $response = $this->actingAs($this->user)->put(route('employees-data.update', $employee), $data);

        $response->assertRedirect(route('employees-data'));
        $response->assertSessionHas('status', 'Successfully updated an employee.');

        $this->assertDatabaseHas('users', [
            'id' => $employee->user_id,
            'email' => 'jane@example.com',
            'role_id' => $newRole->id,
        ]);
        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'name' => 'Jane Doe',
            'department_id' => $newDepartment->id,
            'position_id' => $newPosition->id,
            'is_active' => 1,
        ]);
        $this->assertDatabaseHas('employee_details', [
            'employee_id' => $employee->id,
            'identity_number' => '987654321',
            'email' => 'jane@example.com',
        ]);
        $this->assertDatabaseHas('logs', [
            'description' => $this->user->employee->name . " updated an employee's detail named 'Jane Doe'",
        ]);

        Storage::disk('public')->assertExists('photos/new_photo.jpg');
        Storage::disk('public')->assertExists('cvs/new_cv.pdf');
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
            'description' => $this->user->employee->name . " deleted an employee named '{$employee->name}'",
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
