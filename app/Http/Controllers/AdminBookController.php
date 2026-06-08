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
        'Kindergarten',
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
            'cover_image'    => 'nullable|file|mimes:jpg,jpeg,png,webp|max:10240',
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
            'cover_image'    => 'nullable|file|mimes:jpg,jpeg,png,webp|max:10240',
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

        // 1. Try to resize and compress using GD (Pure PHP - highly compatible, works on Bluehost)
        if ($this->resizeAndCompressImageGD($tempPath, $targetPath, 400, 60)) {
            return "covers/{$webpFilename}";
        }

        // 2. Try to convert to WebP using ImageMagick convert (System binary fallback)
        $convertCmd = sprintf(
            'convert %s -resize 400x -quality 60 %s 2>&1',
            escapeshellarg($tempPath),
            escapeshellarg($targetPath)
        );
        exec($convertCmd, $output, $returnCode);

        if ($returnCode === 0 && file_exists($targetPath)) {
            return "covers/{$webpFilename}";
        }

        // 3. Fallback: store as-is
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
            'cwebp -q 60 -resize 400 0 %s -o %s 2>&1',
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
                'convert %s -resize 400x -quality 60 %s 2>&1',
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

    /**
     * Resize and compress an image using PHP's GD library.
     * Generates a WebP image by default, falling back to JPEG if WebP isn't supported.
     */
    private function resizeAndCompressImageGD(string $sourcePath, string $targetPath, int $targetWidth = 400, int $quality = 60): bool
    {
        // 1. Get image info
        $info = @getimagesize($sourcePath);
        if (!$info) {
            return false;
        }

        $mime = $info['mime'];

        // 2. Create image resource from source
        switch ($mime) {
            case 'image/jpeg':
            case 'image/jpg':
                $sourceImage = @imagecreatefromjpeg($sourcePath);
                break;
            case 'image/png':
                $sourceImage = @imagecreatefrompng($sourcePath);
                break;
            case 'image/webp':
                $sourceImage = @imagecreatefromwebp($sourcePath);
                break;
            default:
                return false;
        }

        if (!$sourceImage) {
            return false;
        }

        // 3. Calculate new dimensions preserving aspect ratio
        $origWidth = imagesx($sourceImage);
        $origHeight = imagesy($sourceImage);

        if ($origWidth > $targetWidth) {
            $targetHeight = (int) (($origHeight / $origWidth) * $targetWidth);
        } else {
            // No need to upscale if the source is already small
            $targetWidth = $origWidth;
            $targetHeight = $origHeight;
        }

        // 4. Create new true color image
        $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);
        if (!$targetImage) {
            imagedestroy($sourceImage);
            return false;
        }

        // Handle transparency for PNG/WebP
        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($targetImage, false);
            imagesavealpha($targetImage, true);
            $transparent = imagecolorallocatealpha($targetImage, 255, 255, 255, 127);
            imagefilledrectangle($targetImage, 0, 0, $targetWidth, $targetHeight, $transparent);
        }

        // 5. Resample the image
        if (!imagecopyresampled($targetImage, $sourceImage, 0, 0, 0, 0, $targetWidth, $targetHeight, $origWidth, $origHeight)) {
            imagedestroy($sourceImage);
            imagedestroy($targetImage);
            return false;
        }

        // 6. Save as WebP if possible, otherwise save as JPEG
        $saved = false;
        if (function_exists('imagewebp')) {
            // Save as WebP
            $saved = @imagewebp($targetImage, $targetPath, $quality);
        } else {
            // Fallback to JPEG
            $saved = @imagejpeg($targetImage, $targetPath, $quality);
        }

        // 7. Free up memory
        imagedestroy($sourceImage);
        imagedestroy($targetImage);

        return $saved;
    }
}
