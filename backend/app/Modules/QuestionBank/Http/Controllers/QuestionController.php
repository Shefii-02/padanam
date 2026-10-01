<?php

namespace App\Modules\QuestionBank\Http\Controllers;

use App\Core\Http\ApiResponse;
use App\Core\Http\Controller;
use App\Models\Question;
use App\Modules\QuestionBank\DTOs\QuestionData;
use App\Modules\QuestionBank\Http\Requests\QuestionRequest;
use App\Modules\QuestionBank\Repositories\QuestionRepository;
use App\Modules\QuestionBank\Resources\QuestionResource;
use App\Modules\QuestionBank\Services\QuestionService;
use Illuminate\Http\Request;

class QuestionController extends Controller
{
    public function __construct(private QuestionRepository $repo, private QuestionService $service) {}

    public function index(Request $r)
    {
        $q = $this->repo->query()->withCount('testQuestions');

        return ApiResponse::ok(QuestionResource::collection($this->repo->paginate($r->all(), QuestionService::WITH, $q)));
    }

    /** Distinct subjects/topics for filter dropdowns. */
    public function facets()
    {
        return ApiResponse::ok([
            'subjects' => Question::whereNotNull('subject')->distinct()->orderBy('subject')->pluck('subject'),
            'topics' => Question::whereNotNull('topic')->select('subject', 'topic')->distinct()->orderBy('topic')->get()->groupBy('subject')->map->pluck('topic'),
        ]);
    }

    public function show(Question $question)
    {
        return ApiResponse::ok(new QuestionResource($question->load(QuestionService::WITH)->loadCount('testQuestions')));
    }

    public function store(QuestionRequest $r)
    {
        return ApiResponse::created(new QuestionResource($this->service->create(QuestionData::fromRequest($r))), 'Question saved');
    }

    public function update(QuestionRequest $r, Question $question)
    {
        return ApiResponse::ok(new QuestionResource($this->service->update($question, QuestionData::fromRequest($r))), 'Question updated');
    }

    public function destroy(Question $question)
    {
        $this->service->delete($question);

        return ApiResponse::ok(null, 'Question deleted');
    }

    public function bulk(Request $r)
    {
        $d = $r->validate([
            'ids' => 'required|array|max:1000', 'ids.*' => 'integer',
            'action' => 'required|in:move,label,unlabel,delete,difficulty',
            'folder_id' => 'nullable|integer|exists:question_folders,id',
            'label_ids' => 'nullable|array', 'label_ids.*' => 'integer|exists:labels,id',
            'difficulty' => 'required_if:action,difficulty|in:easy,moderate,hard',
        ]);
        abort_if($d['action'] === 'delete' && ! $r->user()->can('question_bank.delete'), 403);
        $n = $this->service->bulk($d['ids'], $d['action'], $d);

        return ApiResponse::ok(['affected' => $n], "$n questions updated");
    }
}
