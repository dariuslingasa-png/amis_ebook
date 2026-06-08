<?php

namespace App\Console\Commands;

use App\Models\Ebook;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateEbookCovers extends Command
{
    protected $signature = 'ebooks:generate-covers
                            {--force : Regenerate covers even if one already exists}
                            {--id= : Generate cover for a specific ebook ID only}';

    protected $description = 'Generate WebP cover images from the first page of each ebook PDF';

    public function handle(): int
    {
        $query = Ebook::query();

        if ($id = $this->option('id')) {
            $query->where('id', $id);
        }

        if (! $this->option('force')) {
            $query->whereNull('cover_image_path');
        }

        $books = $query->get();

        if ($books->isEmpty()) {
            $this->info('No ebooks need cover generation.');

            return self::SUCCESS;
        }

        $this->info("Generating covers for {$books->count()} ebook(s)...");

        $successCount = 0;
        $failCount = 0;

        foreach ($books as $book) {
            $this->line("  → {$book->title} (ID: {$book->id})");

            $coverPath = $this->generateCover($book);

            if ($coverPath) {
                $book->update(['cover_image_path' => $coverPath]);
                $this->info("    ✓ Cover saved: {$coverPath}");
                $successCount++;
            } else {
                $this->warn("    ✗ Failed to generate cover");
                $failCount++;
            }
        }

        $this->newLine();
        $this->info("Done! {$successCount} covers generated, {$failCount} failed.");

        return $failCount > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Generate a WebP cover image from the first page of a PDF.
     */
    private function generateCover(Ebook $book): ?string
    {
        $pdfAbsolutePath = Storage::disk('local')->path($book->file_path);

        if (! file_exists($pdfAbsolutePath)) {
            $this->error("    PDF not found: {$pdfAbsolutePath}");

            return null;
        }

        $uuid = Str::uuid();
        $tempDir = sys_get_temp_dir();
        $tempPngPrefix = "{$tempDir}/ebook_cover_{$uuid}";
        $webpFilename = "{$uuid}.webp";

        // Ensure public covers directory exists
        $coversDir = Storage::disk('public')->path('covers');
        if (! is_dir($coversDir)) {
            mkdir($coversDir, 0755, true);
        }

        $webpAbsolutePath = "{$coversDir}/{$webpFilename}";

        // Step 1: Extract first page as PNG using pdftoppm
        // -f 1 -l 1 = first page only, -r 150 = 150 DPI (good balance of quality/size),
        // -png = output PNG format
        $pdftoppmCmd = sprintf(
            'pdftoppm -f 1 -l 1 -r 150 -png %s %s 2>&1',
            escapeshellarg($pdfAbsolutePath),
            escapeshellarg($tempPngPrefix)
        );

        exec($pdftoppmCmd, $output, $returnCode);

        if ($returnCode !== 0) {
            $this->error("    pdftoppm failed (code {$returnCode}): " . implode("\n", $output));

            return null;
        }

        // pdftoppm outputs files like: prefix-1.png or prefix-01.png
        $tempPngPath = null;
        foreach (glob("{$tempPngPrefix}*.png") as $file) {
            $tempPngPath = $file;
            break;
        }

        if (! $tempPngPath || ! file_exists($tempPngPath)) {
            $this->error("    Generated PNG not found at {$tempPngPrefix}*.png");

            return null;
        }

        // Step 2: Convert PNG to WebP using cwebp (if available) or fallback to GD
        $converted = false;

        // Try cwebp first (produces better WebP compression)
        $cwebpCmd = sprintf(
            'cwebp -q 60 -resize 400 0 %s -o %s 2>&1',
            escapeshellarg($tempPngPath),
            escapeshellarg($webpAbsolutePath)
        );

        exec($cwebpCmd, $cwebpOutput, $cwebpReturn);

        if ($cwebpReturn === 0 && file_exists($webpAbsolutePath)) {
            $converted = true;
        }

        // Fallback: try ImageMagick convert
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

        // Fallback: just copy the PNG and rename (still better than downloading 28MB PDF)
        if (! $converted) {
            // Store as PNG if WebP conversion isn't possible
            $pngFilename = "{$uuid}.png";
            $pngAbsolutePath = "{$coversDir}/{$pngFilename}";
            copy($tempPngPath, $pngAbsolutePath);

            // Cleanup temp file
            @unlink($tempPngPath);

            if (file_exists($pngAbsolutePath)) {
                return "covers/{$pngFilename}";
            }

            return null;
        }

        // Cleanup temp PNG
        @unlink($tempPngPath);

        return "covers/{$webpFilename}";
    }
}
