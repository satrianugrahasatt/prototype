<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceTime;
use App\Models\AttendanceType;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AttendancesControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected $employee;

    protected $attendanceTimeIn;

    protected $attendanceTimeOut;

    protected $attendanceTypeOntime;

    protected $attendanceTypeLate;

    protected $role;

    protected $department;

    protected $position;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a role
        $this->role = Role::create([
            'name' => 'Test Role',
            'is_super_user' => false,
        ]);

        // Create a department
        $this->department = Department::create([
            'name' => 'HR',
            'code' => 'HRD',
            'address' => '123 Test Street',
        ]);

        // Create a position
        $this->position = Position::create(['name' => 'Test Position']);

        // Create attendance times and types
        $this->attendanceTimeIn = AttendanceTime::create(['name' => 'IN']);
        $this->attendanceTimeOut = AttendanceTime::create(['name' => 'OUT']);
        $this->attendanceTypeOntime = AttendanceType::create(['name' => 'ONTIME']);
        $this->attendanceTypeLate = AttendanceType::create(['name' => 'LATE']);

        // Create a user
        $this->user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
            'role_id' => $this->role->id,
        ]);

        // Create an employee
        $this->employee = Employee::create([
            'user_id' => $this->user->id,
            'name' => 'John Doe',
            'start_of_contract' => now(),
            'end_of_contract' => now()->addYear(),
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
        ]);
    }

    /** @test */
    public function authenticated_user_can_view_attendances_index()
    {
        $this->actingAs($this->user);
        $response = $this->get(route('attendances'));

        $response->assertStatus(200);
        $response->assertViewIs('pages.attendances');
        $response->assertViewHas('attendances');
    }

    /** @test */
    public function unauthenticated_user_cannot_view_attendances_index()
    {
        $response = $this->get(route('attendances'));

        $response->assertRedirect('/login');
    }

    /** @test */
    public function authenticated_user_can_check_in_before_8am()
    {
        $this->actingAs($this->user);

        // Mock time to 7:59 AM
        Carbon::setTestNow(Carbon::createFromTime(7, 59, 0, 'Asia/Jakarta'));

        $response = $this->post(route('attendances.store'), ['sick' => 0]);

        $response->assertRedirect(route('attendances'));
        $this->assertDatabaseHas('attendances', [
            'employee_id' => $this->employee->id,
            'attendance_time_id' => $this->attendanceTimeIn->id,
            'attendance_type_id' => $this->attendanceTypeOntime->id,
        ]);
    }

    /** @test */
    public function authenticated_user_cannot_check_in_after_8am()
    {
        $this->actingAs($this->user);

        // Mock time to 8:01 AM
        Carbon::setTestNow(Carbon::createFromTime(8, 1, 0, 'Asia/Jakarta'));

        $response = $this->post(route('attendances.store'), ['sick' => 0]);

        $response->assertRedirect(route('attendances'));
        $response->assertSessionHas('status', 'Please wait for checkin time.');
        $this->assertDatabaseMissing('attendances', [
            'employee_id' => $this->employee->id,
        ]);
    }

    /** @test */
    public function authenticated_user_can_check_out_after_4pm()
    {
        $this->actingAs($this->user);

        // Create a check-in record
        Attendance::create([
            'employee_id' => $this->employee->id,
            'attendance_time_id' => $this->attendanceTimeIn->id,
            'attendance_type_id' => $this->attendanceTypeOntime->id,
            'created_at' => Carbon::today('Asia/Jakarta'),
        ]);

        // Mock time to 4:01 PM
        Carbon::setTestNow(Carbon::createFromTime(16, 1, 0, 'Asia/Jakarta'));

        $response = $this->post(route('attendances.store'), ['sick' => 0]);

        $response->assertRedirect(route('attendances'));
        $this->assertDatabaseHas('attendances', [
            'employee_id' => $this->employee->id,
            'attendance_time_id' => $this->attendanceTimeOut->id,
        ]);
    }

    /** @test */
    public function authenticated_user_can_submit_sick_attendance()
    {
        $this->actingAs($this->user);

        $response = $this->post(route('attendances.store'), [
            'sick' => 1,
            'message' => 'Sick leave request',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('attendances', [
            'employee_id' => $this->employee->id,
            'message' => 'Sick leave request',
        ]);
    }

    /** @test */
    public function authenticated_user_can_print_attendances()
    {
        $this->actingAs($this->user);
        Attendance::create([
            'employee_id' => $this->employee->id,
            'attendance_time_id' => $this->attendanceTimeIn->id,
            'attendance_type_id' => $this->attendanceTypeOntime->id,
        ]);

        $response = $this->get(route('attendances.print'));

        $response->assertStatus(200);
        $response->assertViewIs('pages.attendances_print');
        $response->assertViewHas('attendances');
    }
}
