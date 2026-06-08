<?php

namespace App\Http\Controllers;

use App\Models\Ebook;
use App\Models\EbookAccessLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Storage;

class BookController extends Controller
{
    /**
     * Show ebook catalog for students/teachers based on permissions.
     */
    public function index()
    {
        $user = Auth::user();

        if ($user->role === 'admin') {
            // Admins see all ebooks
            $books = Ebook::with('creator')->orderBy('title')->get();

        } elseif ($user->role === 'student') {
            // Students see ebooks matching their grade level (plain text comparison)
            $student    = $user->student;
            $gradeLevel = $student?->grade_level; // e.g. "Grade 4"

            $studentGradeKey = $this->gradeKey($gradeLevel);

            $books = $studentGradeKey
                ? Ebook::where('status', 'published')
                    ->orderBy('title')
                    ->get()
                    ->filter(fn (Ebook $book) => $this->gradeKey($book->grade_level) === $studentGradeKey)
                    ->values()
                : collect();

        } elseif ($user->role === 'teacher') {
            // Subject assignment was removed from eBooks; teachers see all published books.
            $books = Ebook::where('status', 'published')
                ->orderBy('title')
                ->get();
        } else {
            $books = collect();
        }

        return view('books.index', compact('books'));
    }

    /**
     * Open the ebook reader for a specific book after validating permissions.
     */
    public function show(Ebook $book)
    {
        $this->authorizeAccess($book);

        // Generate a temporary signed URL that expires in 10 minutes
        $streamUrl = URL::temporarySignedRoute(
            'ebooks.stream',
            now()->addMinutes(10),
            ['ebook' => $book->id]
        );

        $this->logAccess($book, 'view');

        return view('books.show', compact('book', 'streamUrl'));
    }

    /**
     * Securely stream the private PDF from storage.
     */
    public function stream(Ebook $ebook)
    {
        $this->authorizeAccess($ebook);

        if (!Storage::disk('local')->exists($ebook->file_path)) {
            abort(404, 'E-Book file not found.');
        }

        $absolutePath = Storage::disk('local')->path($ebook->file_path);

        if (! request()->boolean('preview')) {
            $this->logAccess($ebook, 'stream');
        }

        return response()->file($absolutePath, [
            'Content-Type'           => 'application/pdf',
            'Content-Disposition'    => 'inline; filename="' . basename($ebook->file_path) . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Validates that the logged in user has permission to read this ebook.
     */
    protected function authorizeAccess(Ebook $book): void
    {
        $user = Auth::user();

        // Admin always has full access
        if ($user->role === 'admin') {
            return;
        }

        // Student: match by grade_level text
        if ($user->role === 'student') {
            $student = $user->student;
            $studentGrade = $this->gradeKey($student?->grade_level);
            $bookGrade    = $this->gradeKey($book->grade_level);

            if ($studentGrade && $bookGrade && $studentGrade === $bookGrade) {
                return;
            }
            abort(403, 'Access denied. This ebook is not assigned to your grade level.');
        }

        if ($user->role === 'teacher') {
            return;
        }

        abort(403, 'Access denied. Unauthorized role.');
    }

    /**
     * Log ebook access.
     */
    protected function logAccess(Ebook $book, string $action): void
    {
        EbookAccessLog::create([
            'ebook_id'   => $book->id,
            'user_id'    => Auth::id(),
            'action'     => $action,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    protected function gradeKey(?string $grade): ?string
    {
        $grade = strtolower(trim($grade ?? ''));
        if ($grade === '') {
            return null;
        }

        $grade = preg_replace('/\s+/', ' ', $grade);

        if (preg_match('/^kinder\s*([12])$/', $grade, $match)) {
            return 'kinder-' . $match[1];
        }

        if (preg_match('/^(?:grade\s*)?([1-9]|1[0-2])$/', $grade, $match)) {
            return 'grade-' . $match[1];
        }

        if (preg_match('/^k(?:11|12)$/', $grade)) {
            return 'grade-' . substr($grade, 1);
        }

        return $grade;
    }
}
