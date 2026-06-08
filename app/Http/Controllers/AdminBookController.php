<?php

namespace App\Http\Controllers;

use App\Models\Ebook;
use App\Models\EbookAccessLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

        Ebook::create([
            'title'          => $request->title,
            'description'    => $request->description,
            'grade_level'    => trim($request->grade_level ?? ''),
            'file_path'      => $pdfPath,
            'is_downloadable'=> $request->has('is_downloadable'),
            'status'         => $request->status,
            'created_by'     => Auth::id(),
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
        }

        $book->update($data);

        return redirect()->route('admin.books.index')->with('success', 'E-Book updated successfully.');
    }

    /**
     * Delete an ebook from the database and filesystems.
     */
    public function destroy(Ebook $book)
    {
        // Delete PDF file
        if ($book->file_path) {
            Storage::disk('local')->delete($book->file_path);
        }

        $book->delete();

        return redirect()->route('admin.books.index')->with('success', 'E-Book deleted successfully.');
    }

}
