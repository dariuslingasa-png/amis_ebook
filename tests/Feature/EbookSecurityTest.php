<?php

namespace Tests\Feature;

use App\Models\Ebook;
use App\Models\EbookAccessLog;
use App\Models\GradeLevel;
use App\Models\SectionSubject;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class EbookSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $studentUser;
    protected User $teacherUser;
    protected GradeLevel $grade4;
    protected GradeLevel $grade5;
    protected Subject $scienceSub;
    protected Subject $arabicSub;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');

        // Dynamically create shared tables for test runtime
        Schema::create('grade_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('grade_level');
            $table->timestamps();
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('student_number');
            $table->string('grade_level');
            $table->timestamps();
        });

        Schema::create('section_subjects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('section_id');
            $table->string('subject_name');
            $table->string('teacher_name');
            $table->timestamps();
        });

        // Create Grade Levels
        $this->grade4 = GradeLevel::create(['name' => 'Grade 4', 'sort_order' => 4]);
        $this->grade5 = GradeLevel::create(['name' => 'Grade 5', 'sort_order' => 5]);

        // Create Subjects
        $this->scienceSub = Subject::create(['name' => 'Science', 'code' => 'SCI4', 'grade_level' => 'Grade 4']);
        $this->arabicSub = Subject::create(['name' => 'Arabic', 'code' => 'ARA4', 'grade_level' => 'Grade 4']);

        // Create Users
        $this->adminUser = User::create([
            'name' => 'Admin Admin',
            'email' => 'admin@amis.test',
            'username' => 'admin',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $this->studentUser = User::create([
            'name' => 'Student Student',
            'email' => 'student@amis.test',
            'username' => 'student',
            'password' => Hash::make('password'),
            'role' => 'student',
        ]);

        // Link student profile to Grade 4
        Student::create([
            'user_id' => $this->studentUser->id,
            'student_number' => 'ST1001',
            'grade_level' => 'Grade 4',
        ]);

        $this->teacherUser = User::create([
            'name' => 'Teacher Teacher',
            'email' => 'teacher@amis.test',
            'username' => 'teacher',
            'password' => Hash::make('password'),
            'role' => 'teacher',
        ]);

        // Link teacher to SectionSubject (Taught Subject: Science)
        SectionSubject::create([
            'section_id' => 1,
            'subject_name' => 'Science',
            'teacher_name' => 'Teacher Teacher',
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('books.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_admin_can_upload_ebook_to_private_storage(): void
    {
        $this->actingAs($this->adminUser);

        $pdf = UploadedFile::fake()->create('textbook.pdf', 500, 'application/pdf');
        $cover = UploadedFile::fake()->create('cover.jpg', 100, 'image/jpeg');

        $response = $this->post(route('admin.books.store'), [
            'title' => 'Science Grade 4 Textbook',
            'description' => 'Official textbook',
            'grade_level' => 'Grade 4',
            'pdf_file' => $pdf,
            'status' => 'published',
        ]);

        $response->assertRedirect(route('admin.books.index'));
        $this->assertDatabaseHas('ebooks', [
            'title' => 'Science Grade 4 Textbook',
        ]);

        $ebook = Ebook::where('title', 'Science Grade 4 Textbook')->first();
        $this->assertNotNull($ebook->file_path);
        
        // Assert stored in local (private) storage
        Storage::disk('local')->assertExists($ebook->file_path);
    }

    public function test_student_can_only_access_assigned_grade_level_ebook(): void
    {
        // 1. Create a Grade 4 book
        $grade4Book = Ebook::create([
            'title' => 'Grade 4 Science',
            'grade_level' => 'Grade 4',
            'file_path' => 'private/ebooks/g4.pdf',
            'created_by' => $this->adminUser->id,
            'status' => 'published',
        ]);

        // 2. Create a Grade 5 book
        $grade5Book = Ebook::create([
            'title' => 'Grade 5 Math',
            'grade_level' => 'Grade 5',
            'file_path' => 'private/ebooks/g5.pdf',
            'created_by' => $this->adminUser->id,
            'status' => 'published',
        ]);

        $this->actingAs($this->studentUser);

        // Catalog should see Grade 4 book, but NOT Grade 5 book
        $response = $this->get(route('books.index'));
        $response->assertStatus(200);
        $response->assertSee('Grade 4 Science');
        $response->assertDontSee('Grade 5 Math');

        // Accessing Grade 4 book reader is allowed
        $response = $this->get(route('books.show', $grade4Book->id));
        $response->assertStatus(200);

        // Accessing Grade 5 book reader is blocked
        $response = $this->get(route('books.show', $grade5Book->id));
        $response->assertStatus(403);
    }

    public function test_teacher_can_access_all_published_ebooks(): void
    {
        // 1. Create a Science Book (Taught by teacher)
        $scienceBook = Ebook::create([
            'title' => 'Science Textbook',
            'grade_level' => 'Grade 4',
            'file_path' => 'private/ebooks/sci.pdf',
            'created_by' => $this->adminUser->id,
            'status' => 'published',
        ]);

        // 2. Create an Arabic Book (NOT taught by teacher)
        $arabicBook = Ebook::create([
            'title' => 'Arabic Textbook',
            'grade_level' => 'Grade 4',
            'file_path' => 'private/ebooks/ara.pdf',
            'created_by' => $this->adminUser->id,
            'status' => 'published',
        ]);

        $this->actingAs($this->teacherUser);

        // Subject assignment was removed from eBooks, so teachers see all published books.
        $response = $this->get(route('books.index'));
        $response->assertStatus(200);
        $response->assertSee('Science Textbook');
        $response->assertSee('Arabic Textbook');

        // Reading Science book is allowed
        $response = $this->get(route('books.show', $scienceBook->id));
        $response->assertStatus(200);

        // Reading Arabic book is also allowed
        $response = $this->get(route('books.show', $arabicBook->id));
        $response->assertStatus(200);
    }

    public function test_unsigned_stream_route_fails_for_pdf_file(): void
    {
        $book = Ebook::create([
            'title' => 'Assigned Book',
            'grade_level' => 'Grade 4',
            'file_path' => 'private/ebooks/book.pdf',
            'created_by' => $this->adminUser->id,
            'status' => 'published',
        ]);

        Storage::disk('local')->put('private/ebooks/book.pdf', 'PDF-CONTENT');

        $this->actingAs($this->studentUser);

        // Direct unsigned access is blocked
        $response = $this->get(route('ebooks.stream', $book->id));
        $response->assertStatus(403); // invalid signature
    }

    public function test_signed_temporary_url_stream_works_and_logs_access(): void
    {
        $book = Ebook::create([
            'title' => 'Assigned Book',
            'grade_level' => 'Grade 4',
            'file_path' => 'private/ebooks/book.pdf',
            'created_by' => $this->adminUser->id,
            'status' => 'published',
        ]);

        Storage::disk('local')->put('private/ebooks/book.pdf', 'PDF-CONTENT');

        $this->actingAs($this->studentUser);

        // Generate signed URL
        $signedUrl = URL::temporarySignedRoute(
            'ebooks.stream',
            now()->addMinutes(5),
            ['ebook' => $book->id]
        );

        $response = $this->get($signedUrl);
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');

        // Verify log entry was created
        $this->assertDatabaseHas('ebook_access_logs', [
            'ebook_id' => $book->id,
            'user_id' => $this->studentUser->id,
            'action' => 'stream',
        ]);
    }

    public function test_sso_auto_login_from_admin_session_cookie(): void
    {
        // 1. Create a session record in the sessions table manually
        $sessionId = 'test-session-id-123';
        \DB::table('sessions')->insert([
            'id' => $sessionId,
            'user_id' => $this->adminUser->id,
            'payload' => 'payload-data',
            'last_activity' => time(),
        ]);

        // 2. Make a request to books.index with the admin session cookie containing the session ID
        $response = $this->withUnencryptedCookies([
            'amis_admin_session' => $sessionId,
        ])->get(route('books.index'));

        // 3. Since it auto-logged in, the response should be 200 (not redirected to login)
        $response->assertStatus(200);
        $this->assertAuthenticatedAs($this->adminUser);
    }
}
