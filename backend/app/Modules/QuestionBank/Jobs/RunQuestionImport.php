<?php

namespace App\Modules\QuestionBank\Jobs;

use App\Models\QuestionImport;
use App\Modules\QuestionBank\Services\ImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunQuestionImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(public int $importId, public bool $skipDuplicates = true) {}

    public function handle(ImportService $service): void
    {
        $import = QuestionImport::findOrFail($this->importId);
        auth()->onceUsingId($import->created_by);   // created_by on each question
        $service->run($import, $this->skipDuplicates);
    }

    public function failed(\Throwable $e): void
    {
        QuestionImport::whereKey($this->importId)->update(['status' => 'failed']);
    }
}
