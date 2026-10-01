<?php

namespace App\Modules\Courses\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContentRequest extends FormRequest
{
    public function rules(): array
    {
        $c = $this->isMethod('post');

        return [
            'type' => [$c ? 'required' : 'prohibited', 'in:video,live,pdf,note,article,test,quiz,link'],
            'title' => [$c ? 'required' : 'sometimes', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'folder_id' => ['nullable', 'integer', 'exists:course_folders,id'],
            'access' => ['nullable', 'in:free,premium,demo'],
            'publish_at' => ['nullable', 'date'],
            'unlock_after_content_id' => ['nullable', 'integer', 'exists:contents,id'],
            'batch_ids' => ['nullable', 'array'],
            'batch_ids.*' => ['integer', 'exists:batches,id'],
            'sort' => ['nullable', 'integer'],
            // typed payload
            'source' => ['nullable', 'in:youtube,aws'],
            'url' => ['nullable', 'string', 'max:500'],
            'youtube_id' => ['nullable', 'string', 'max:40'],
            's3_key' => ['nullable', 'string', 'max:500'],
            'hls_path' => ['nullable', 'string', 'max:500'],
            'duration_sec' => ['nullable', 'integer', 'min:0'],
            'file' => ['nullable', 'file', 'mimes:pdf', 'max:51200'],
            'downloadable' => ['nullable', 'boolean'],
            'body' => ['nullable', 'array'],
            'tip' => ['nullable', 'array'],
            'one_liners' => ['nullable', 'array'],
            'note_title' => ['nullable'],
            'ref_id' => ['nullable', 'integer'],
        ];
    }
}
