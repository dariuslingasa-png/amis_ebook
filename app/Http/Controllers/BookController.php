<?php

namespace App\Http\Controllers;

use App\Models\Ebook;
use App\Models\EbookAccessLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Storage;

class BookController extends Controller
{
    /**
     * Show the public ebook catalog with pagination.
     */
    public function index()
    {
        $user = Auth::user();
        $isAdmin = $user?->role === 'admin';

        $query = Ebook::select([
            'id', 'title', 'description', 'grade_level',
            'cover_image_path', 'is_downloadable', 'status',
        ])->withCount(['logs as views_count' => function ($query) {
            $query->where('action', 'view');
        }]);

        if (!$isAdmin) {
            $query->where('status', 'published');
        }

        $books = $query->get()->sortBy('title', SORT_NATURAL | SORT_FLAG_CASE)->values();

        return view('books.index', compact('books'));
    }

    /**
     * Open the ebook reader for a specific book after validating permissions.
     */
    public function show(Ebook $book)
    {
        $this->authorizeAccess($book);

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
     * Validates that the requested ebook is public or the logged in user is an admin.
     */
    protected function authorizeAccess(Ebook $book): void
    {
        $user = Auth::user();

        if ($user?->role === 'admin') {
            return;
        }

        if ($book->status === 'published') {
            return;
        }

        abort(404);
    }

    /**
     * Log ebook access.
     */
    protected function logAccess(Ebook $book, string $action): void
    {
        if (! Auth::check()) {
            return;
        }

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
