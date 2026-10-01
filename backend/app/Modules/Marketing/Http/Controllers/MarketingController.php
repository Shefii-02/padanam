<?php

namespace App\Modules\Marketing\Http\Controllers;

use App\Core\Http\ApiResponse;
use App\Core\Http\Controller;
use App\Models\MarketingExport;
use App\Modules\Marketing\Services\MarketingExportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MarketingController extends Controller
{
    public function __construct(private MarketingExportService $service) {}

    public function types()
    {
        return ApiResponse::ok(['types' => MarketingExportService::TYPES, 'fields' => MarketingExportService::FIELDS, 'formats' => MarketingExportService::FORMATS,
            'districts' => ['Thiruvananthapuram', 'Kollam', 'Pathanamthitta', 'Alappuzha', 'Kottayam', 'Idukki', 'Ernakulam', 'Thrissur', 'Palakkad', 'Malappuram', 'Kozhikode', 'Wayanad', 'Kannur', 'Kasaragod']]);
    }

    /** Live "how many people" count while filters change. */
    public function count(Request $r)
    {
        $type = $this->type($r);

        return ApiResponse::ok(['count' => $this->service->count($type, $this->filters($r))]);
    }

    public function export(Request $r)
    {
        $type = $this->type($r);
        $format = $r->validate(['format' => ['nullable', Rule::in(MarketingExportService::FORMATS)]])['format'] ?? 'standard';

        return $this->service->export($type, $this->filters($r), (array) $r->input('fields', []), $format, $r->user()->id);
    }

    public function history()
    {
        return ApiResponse::paginated(MarketingExport::with('creator:id,name')->latest('id')->paginate(25)->through(fn ($e) => [
            'id' => $e->id, 'type' => $e->type, 'format' => $e->format, 'rows' => $e->rows, 'filters' => $e->filters, 'fields' => $e->fields,
            'by' => $e->creator?->name, 'created_at' => $e->created_at,
        ]));
    }

    private function type(Request $r): string
    {
        return $r->validate(['type' => ['required', Rule::in(MarketingExportService::TYPES)]])['type'];
    }

    private function filters(Request $r): array
    {
        return collect($r->except(['type', 'fields', 'format', 'page']))->filter(fn ($v) => $v !== null && $v !== '' && $v !== [])->all();
    }
}
