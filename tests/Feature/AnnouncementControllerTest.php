<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Log;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AnnouncementsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $employee;
    protected $department;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a department
        $this->department = Department::create(['name' => 'HR']);

        // Create a user and employee for authentication
        $this->user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
            'role_id' => 1,
        ]);

        $this->employee = Employee::create([
            'user_id' => $this->user->id,
            'name' => 'John Doe',
            'start_of_contract' => now(),
            'end_of_contract' => now()->addYear(),
            'department_id' => $this->department->id,
            'position_id' => 1,
        ]);
    }

    /** @test */
    public function authenticated_user_can_view_announcements_index()
    {
        $this->actingAs($this->user);
        $response = $this->get(route('announcements'));

        $response->assertStatus(200);
        $response->assertViewIs('pages.announcements');
        $response->assertViewHas('announcements');
    }

    /** @test */
    public function unauthenticated_user_cannot_view_announcements_index()
    {
        $response = $this->get(route('announcements'));

        $response->assertRedirect('/login');
    }

    /** @test */
    public function authenticated_user_can_view_create_form()
    {
        $this->actingAs($this->user);
        $response = $this->get(route('announcements.create'));

        $response->assertStatus(200);
        $response->assertViewIs('pages.announcements_create');
        $response->assertViewHas('departments');
    }

    /** @test */
    public function authenticated_user_can_store_announcement()
    {
        Storage::fake('public');
        $this->actingAs($this->user);

        $data = [
            'title' => 'New Announcement',
            'description' => 'This is a test announcement.',
            'department_id' => $this->department->id,
            'attachment' => UploadedFile::fake()->create('document.pdf', 100),
        ];

        $response = $this->post(route('announcements.store'), $data);

        $response->assertRedirect(route('announcements'));
        $response->assertSessionHas('status', 'Successfully created an announcement.');
        $this->assertDatabaseHas('announcements', [
            'title' => 'New Announcement',
            'department_id' => $this->department->id,
            'created_by' => $this->employee->id,
        ]);
        $this->assertDatabaseHas('logs', [
            'description' => "John Doe created an announcement titled 'New Announcement'",
        ]);
        Storage::disk('public')->assertExists('attachments/document.pdf');
    }

    /** @test */
    public function authenticated_user_can_view_specific_announcement()
    {
        $this->actingAs($this->user);
        $announcement = Announcement::create([
            'title' => 'Test Announcement',
            'description' => 'Test Description',
            'department_id' => $this->department->id,
            'created_by' => $this->employee->id,
        ]);

        $response = $this->get(route('announcements.show', $announcement));

        $response->assertStatus(200);
        $response->assertViewIs('pages.announcements_show');
        $response->assertViewHas('announcement', $announcement);
    }

    /** @test */
    public function authenticated_user_can_edit_own_announcement()
    {
        $this->actingAs($this->user);
        $announcement = Announcement::create([
            'title' => 'Test Announcement',
            'description' => 'Test Description',
            'department_id' => $this->department->id,
            'created_by' => $this->employee->id,
        ]);

        $response = $this->get(route('announcements.edit', $announcement));

        $response->assertStatus(200);
        $response->assertViewIs('pages.announcements_edit');
        $response->assertViewHas('announcement', $announcement);
        $response->assertViewHas('departments');
    }

    /** @test */
    public function authenticated_user_can_update_own_announcement()
    {
        Storage::fake('public');
        $this->actingAs($this->user);
        $announcement = Announcement::create([
            'title' => 'Test Announcement',
            'description' => 'Test Description',
            'department_id' => $this->department->id,
            'created_by' => $this->employee->id,
        ]);

        $data = [
            'title' => 'Updated Announcement',
            'description' => 'Updated Description',
            'department_id' => $this->department->id,
            'attachment' => UploadedFile::fake()->create('updated.pdf', 100),
        ];

        $response = $this->put(route('announcements.update', $announcement), $data);

        $response->assertRedirect(route('announcements'));
        $response->assertSessionHas('status', 'Successfully updated announcement.');
        $this->assertDatabaseHas('announcements', [
            'id' => $announcement->id,
            'title' => 'Updated Announcement',
            'description' => 'Updated Description',
        ]);
        $this->assertDatabaseHas('logs', [
            'description' => "John Doe updated an announcement titled 'Test Announcement'",
        ]);
        Storage::disk('public')->assertExists('attachments/updated.pdf');
    }

    /** @test */
    public function authenticated_user_can_delete_own_announcement()
    {
        $this->actingAs($this->user);
        $announcement = Announcement::create([
            'title' => 'Test Announcement',
            'description' => 'Test Description',
            'department_id' => $this->department->id,
            'created_by' => $this->employee->id,
        ]);

        $response = $this->delete(route('announcements.destroy', $announcement));

        $response->assertRedirect(route('announcements'));
        $response->assertSessionHas('status', 'Successfully deleted announcement.');
        $this->assertDatabaseMissing('announcements', ['id' => $announcement->id]);
        $this->assertDatabaseHas('logs', [
            'description' => "John Doe deleted an announcement titled 'Test Announcement'",
        ]);
    }

    /** @test */
    public function authenticated_user_can_print_announcements()
    {
        $this->actingAs($this->user);
        $announcement = Announcement::create([
            'title' => 'Test Announcement',
            'description' => 'Test Description',
            'department_id' => $this->department->id,
            'created_by' => $this->employee->id,
        ]);

        $response = $this->get(route('announcements.print'));

        $response->assertStatus(200);
        $response->assertViewIs('pages.announcements_print');
        $response->assertViewHas('announcements');
    }
}