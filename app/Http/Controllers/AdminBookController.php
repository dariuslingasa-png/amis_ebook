<?php

namespace App\Http\Controllers;

use App\Models\Ebook;
use App\Models\EbookAccessLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminBookController extends Controller
{
    private const GRADE_LEVELS = [
        'Kinder 1',
        'Kinder 2',
        'Grade 1',
        'Grade 2',
        'Grade 3',
        'Grade 4',
        'Grade 5',
        'Grade 6',
        'Grade 7',
        'Grade 8',
        'Grade 9',
        'Grade 10',
        'K11',
        'K12',
    ];

    /**
     * Admin Dashboard: List all ebooks.
     */
    public function index()
    {
        $books = Ebook::with('creator')->orderBy('created_at', 'desc')->get();
        
        // Stats Overview
        $totalEbooks = Ebook::count();
        $totalViews = EbookAccessLog::where('action', 'view')->count();
        $totalDownloads = EbookAccessLog::where('action', 'stream')->count();
        
        $recentLogs = EbookAccessLog::with(['ebook', 'user'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return view('admin.dashboard', compact('books', 'totalEbooks', 'totalViews', 'totalDownloads', 'recentLogs'));
    }

    /**
     * Show the create ebook form.
     */
    public function create()
    {
        return view('admin.create_book');
    }

    /**
     * Store a new ebook in the database and private storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title'          => 'required|string|max:255',
            'description'    => 'nullable|string',
            'grade_level'    => ['required', 'string', Rule::in(self::GRADE_LEVELS)],
            'pdf_file'       => 'required|file|mimes:pdf|max:51200', // max 50MB
            'cover_image'    => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120',
            'is_downloadable'=> 'nullable|boolean',
            'status'         => 'required|string|in:draft,published',
        ]);

        // 1. Upload private PDF file
        $pdfPath = null;
        if ($request->hasFile('pdf_file')) {
            $file = $request->file('pdf_file');
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $pdfPath = $file->storeAs('private/ebooks', $filename, 'local');
        }

        // 2. Generate cover image from PDF
        $coverPath = null;
        if ($pdfPath) {
            $pdfAbsPath = Storage::disk('local')->path($pdfPath);
            $coverPath = $this->generateCoverFromPdf($pdfAbsPath);
        }

        // 3. Allow manual cover upload to override
        if ($request->hasFile('cover_image')) {
            $manualCover = $this->storeManualCover($request);
            if ($manualCover) {
                $coverPath = $manualCover;
            }
        }

        Ebook::create([
            'title'            => $request->title,
            'description'      => $request->description,
            'grade_level'      => trim($request->grade_level ?? ''),
            'file_path'        => $pdfPath,
            'cover_image_path' => $coverPath,
            'is_downloadable'  => $request->has('is_downloadable'),
            'status'           => $request->status,
            'created_by'       => Auth::id(),
        ]);

        return redirect()->route('admin.books.index')->with('success', 'E-Book uploaded and created successfully.');
    }

    /**
     * Show the edit ebook form.
     */
    public function edit(Ebook $book)
    {
        return view('admin.edit_book', compact('book'));
    }

    /**
     * Update ebook metadata and files.
     */
    public function update(Request $request, Ebook $book)
    {
        $request->validate([
            'title'          => 'required|string|max:255',
            'description'    => 'nullable|string',
            'grade_level'    => ['required', 'string', Rule::in(self::GRADE_LEVELS)],
            'pdf_file'       => 'nullable|file|mimes:pdf|max:51200',
            'cover_image'    => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120',
            'is_downloadable'=> 'nullable|boolean',
            'status'         => 'required|string|in:draft,published',
        ]);

        $data = [
            'title'          => $request->title,
            'description'    => $request->description,
            'grade_level'    => trim($request->grade_level ?? ''),
            'is_downloadable'=> $request->has('is_downloadable'),
            'status'         => $request->status,
        ];

        // Replace PDF file if uploaded
        if ($request->hasFile('pdf_file')) {
            if ($book->file_path) {
                Storage::disk('local')->delete($book->file_path);
            }
            $file = $request->file('pdf_file');
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $data['file_path'] = $file->storeAs('private/ebooks', $filename, 'local');

            // Re-generate cover from new PDF
            $pdfAbsPath = Storage::disk('local')->path($data['file_path']);
            $data['cover_image_path'] = $this->generateCoverFromPdf($pdfAbsPath);
        }

        // Allow manual cover upload to override
        if ($request->hasFile('cover_image')) {
            $manualCover = $this->storeManualCover($request);
            if ($manualCover) {
                $data['cover_image_path'] = $manualCover;
            }
        }

        $book->update($data);

        return redirect()->route('admin.books.index')->with('success', 'E-Book updated successfully.');
    }

    /**
     * Delete an ebook from the database and filesystems.
     */
    public function destroy(Ebook $book)
    {
        // Delete cover image
        if ($book->cover_image_path) {
            $coverFile = Storage::disk('public')->path($book->cover_image_path);
            if (file_exists($coverFile)) {
                @unlink($coverFile);
            }
        }

        // Delete PDF file
        if ($book->file_path) {
            Storage::disk('local')->delete($book->file_path);
        }

        $book->delete();

        return redirect()->route('admin.books.index')->with('success', 'E-Book deleted successfully.');
    }

    /**
     * Store a manually uploaded cover image as WebP.
     */
    private function storeManualCover(Request $request): ?string
    {
        $file = $request->file('cover_image');
        if (! $file) {
            return null;
        }

        $uuid = Str::uuid();
        $webpFilename = "{$uuid}.webp";

        $coversDir = Storage::disk('public')->path('covers');
        if (! is_dir($coversDir)) {
            mkdir($coversDir, 0755, true);
        }

        $targetPath = "{$coversDir}/{$webpFilename}";
        $tempPath = $file->getRealPath();

        // Try to convert to WebP using ImageMagick convert
        $convertCmd = sprintf(
            'convert %s -resize 600x -quality 80 %s 2>&1',
            escapeshellarg($tempPath),
            escapeshellarg($targetPath)
        );
        exec($convertCmd, $output, $returnCode);

        if ($returnCode === 0 && file_exists($targetPath)) {
            return "covers/{$webpFilename}";
        }

        // Fallback: store as-is
        $ext = $file->getClientOriginalExtension();
        $fallbackFilename = "{$uuid}.{$ext}";
        $file->move($coversDir, $fallbackFilename);

        return "covers/{$fallbackFilename}";
    }

    /**
     * Generate a WebP cover image from the first page of a PDF.
     */
    private function generateCoverFromPdf(string $pdfAbsolutePath): ?string
    {
        if (! file_exists($pdfAbsolutePath)) {
            return null;
        }

        $uuid = Str::uuid();
        $tempDir = sys_get_temp_dir();
        $tempPngPrefix = "{$tempDir}/ebook_cover_{$uuid}";

        $coversDir = Storage::disk('public')->path('covers');
        if (! is_dir($coversDir)) {
            mkdir($coversDir, 0755, true);
        }

        $webpFilename = "{$uuid}.webp";
        $webpAbsolutePath = "{$coversDir}/{$webpFilename}";

        // Step 1: Extract first page as PNG using pdftoppm
        $pdftoppmCmd = sprintf(
            'pdftoppm -f 1 -l 1 -r 150 -png %s %s 2>&1',
            escapeshellarg($pdfAbsolutePath),
            escapeshellarg($tempPngPrefix)
        );

        exec($pdftoppmCmd, $output, $returnCode);

        if ($returnCode !== 0) {
            Log::warning("pdftoppm failed for cover generation: " . implode("\n", $output));
            return null;
        }

        // pdftoppm outputs files like: prefix-1.png or prefix-01.png
        $tempPngPath = null;
        foreach (glob("{$tempPngPrefix}*.png") as $file) {
            $tempPngPath = $file;
            break;
        }

        if (! $tempPngPath || ! file_exists($tempPngPath)) {
            return null;
        }

        // Step 2: Convert PNG to WebP
        $converted = false;

        // Try cwebp first
        $cwebpCmd = sprintf(
            'cwebp -q 80 -resize 600 0 %s -o %s 2>&1',
            escapeshellarg($tempPngPath),
            escapeshellarg($webpAbsolutePath)
        );
        exec($cwebpCmd, $cwebpOutput, $cwebpReturn);

        if ($cwebpReturn === 0 && file_exists($webpAbsolutePath)) {
            $converted = true;
        }

        // Fallback: ImageMagick convert
        if (! $converted) {
            $convertCmd = sprintf(
                'convert %s -resize 600x -quality 80 %s 2>&1',
                escapeshellarg($tempPngPath),
                escapeshellarg($webpAbsolutePath)
            );
            exec($convertCmd, $convertOutput, $convertReturn);

            if ($convertReturn === 0 && file_exists($webpAbsolutePath)) {
                $converted = true;
            }
        }

        // Fallback: store as PNG
        if (! $converted) {
            $pngFilename = "{$uuid}.png";
            $pngAbsolutePath = "{$coversDir}/{$pngFilename}";
            copy($tempPngPath, $pngAbsolutePath);
            @unlink($tempPngPath);

            return file_exists($pngAbsolutePath) ? "covers/{$pngFilename}" : null;
        }

        @unlink($tempPngPath);

        return "covers/{$webpFilename}";
    }
}
