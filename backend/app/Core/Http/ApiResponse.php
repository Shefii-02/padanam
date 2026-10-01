<?php

namespace App\Core\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\AbstractPaginator;

/**
 * One envelope for every response:
 *   { success, message, data, meta? }
 */
final class ApiResponse
{
    public static function ok(mixed $data = null, string $message = 'OK', int $status = 200, array $meta = []): JsonResponse
    {
        if ($data instanceof AnonymousResourceCollection && $data->resource instanceof AbstractPaginator) {
            $p = $data->resource;
            $meta['pagination'] = [
                'page' => $p->currentPage(),
                'per_page' => $p->perPage(),
                'total' => method_exists($p, 'total') ? $p->total() : null,
                'last_page' => method_exists($p, 'lastPage') ? $p->lastPage() : null,
            ];
            $data = $data->collection;
        }
        if ($data instanceof JsonResource) {
            $data = $data->resolve(request());
        }

        $body = ['success' => true, 'message' => $message, 'data' => $data];
        if ($meta) {
            $body['meta'] = $meta;
        }

        return response()->json($body, $status);
    }

    public static function created(mixed $data = null, string $message = 'Created'): JsonResponse
    {
        return self::ok($data, $message, 201);
    }

    /** Paginator (already mapped with ->through()) → data = items, meta.pagination + extra meta. */
    public static function paginated(AbstractPaginator $p, array $meta = [], string $message = 'OK'): JsonResponse
    {
        $meta['pagination'] = [
            'page' => $p->currentPage(), 'per_page' => $p->perPage(),
            'total' => method_exists($p, 'total') ? $p->total() : null,
            'last_page' => method_exists($p, 'lastPage') ? $p->lastPage() : null,
        ];

        return self::ok(array_values($p->items()), $message, 200, $meta);
    }

    public static function fail(string $message, int $status = 400, array $extra = []): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message, 'data' => null] + $extra, $status);
    }
}
