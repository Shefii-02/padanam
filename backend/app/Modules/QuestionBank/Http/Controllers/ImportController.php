<?php

namespace App\Modules\QuestionBank\Http\Controllers;

use App\Core\Http\ApiResponse;
use App\Core\Http\Controller;
use App\Models\QuestionImport;
use App\Modules\QuestionBank\Services\ImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImportController extends Controller
{
    public function __construct(private ImportService $imports) {}

    public function index()
    {
        return ApiResponse::ok(QuestionImport::with('creator:id,name', 'folder:id,name')->latest()->limit(50)->get()
            ->map(fn ($i) => collect($i->toArray())->except(['preview', 'file_path'])));
    }

    /** Step 1: upload + parse → preview */
    public function upload(Request $r)
    {
        $opts = $r->validate([
            'file' => 'required|file|max:20480',
            'lang' => 'nullable|in:'.implode(',', config('app.supported_languages')),
            'folder_id' => 'nullable|integer|exists:question_folders,id',
            'label_ids' => 'nullable|array', 'label_ids.*' => 'integer|exists:labels,id',
            'default_marks' => 'nullable|numeric|min:0|max:100',
            'default_negative' => 'nullable|numeric|min:0|max:100',
            'match_translations' => 'nullable|boolean',
        ]);

        return ApiResponse::created($this->imports->upload($r->file('file'), $opts), 'File checked');
    }

    public function show(QuestionImport $import)
    {
        return ApiResponse::ok($import);
    }

    /** Step 3: import (runs in the queue; poll show()) */
    public function confirm(Request $r, QuestionImport $import)
    {
        return ApiResponse::ok($this->imports->confirm($import, $r->boolean('skip_duplicates', true)), 'Import started');
    }

    public function errors(QuestionImport $import)
    {
        abort_unless($import->error_report_path && Storage::disk('local')->exists($import->error_report_path), 404);

        return Storage::disk('local')->download($import->error_report_path, 'import_errors_'.$import->id.'.csv');
    }

    public function template()
    {
        return response($this->imports->templateCsv(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="padanam_questions_template.csv"',
        ]);
    }
}
