<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\StoreTicketRequest;
use App\Models\Ticket;
use App\Models\TicketComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

#[OA\Info(version: '1.0.0', title: 'HelpdeskLiteAPI', description: 'Internal support tickets API')]
#[OA\SecurityScheme(securityScheme: 'apiKey', type: 'apiKey', description: "API Key Authentication", name: 'X-API-Key', in: 'header')]
class TicketController
{
    #[OA\Post(
        path: '/api/v1/tickets',
        summary: 'Создать заявку',
        tags: ['Tickets'],
        security: [['apiKey' => []]],

        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['title', 'description', 'author_email'],
                properties: [
                    new OA\Property(property: 'title', type: 'string', example: 'Не работает отчёт'),
                    new OA\Property(property: 'description', type: 'string', example: 'При открытии 500 ошибка'),
                    new OA\Property(property: 'author_email', type: 'string', format: 'email', example: 'user@company.local'),
                ],
            ),
        ),

        responses: [
            new OA\Response(
                response: 201,
                description: "Заявка успешно создана",
                content: new OA\JsonContent(properties: [new OA\Property(property: 'id', type: 'integer')])
            ),
            new OA\Response(
                response: 422,
                description: "Ошибка валидации"
            ),
        ],
    )]
    public function store(StoreTicketRequest $request): JsonResponse
    {
        $now = now()->format('Y-m-d H:i:s');
        $ticket = Ticket::create([
            'title' => $request->title,
            'description' => $request->description,
            'author_email' => $request->author_email,
            'status' => 'new',
            'version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return response()->json(['id' => $ticket->id], 201);
    }

    #[OA\Get(
        path: '/api/v1/tickets',
        summary: 'Получить список заявок (фильтры + пагинация)',
        tags: ['Tickets'],
        security: [['apiKey' => []]],

        parameters: [
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['new', 'in_progress', 'done', 'closed'])),
            new OA\Parameter(name: 'q', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 20)),
            new OA\Parameter(name: 'sort', in: 'query', schema: new OA\Schema(type: 'string', example: '-created_at')),
        ],

        responses: [
            new OA\Response(
                response: 201,
                description: "Cписок заявок успешно получен",
            ),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        $query = Ticket::query();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($sub) use ($q) {
                $sub->where('title', 'LIKE', "%{$q}%")
                    ->orWhere('description', 'LIKE', "%{$q}%");
            });
        }

        $sort = $request->input('sort', '-created_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $field = ltrim($sort, '-');
        if (!in_array($field, ['created_at', 'updated_at'], true)) {
            $field = 'created_at';
        }
        $query->orderBy($field, $direction);

        $perPage = min((int) $request->input('per_page', 20), 100);
        $page = max((int) $request->input('page', 1), 1);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $items = $paginator->getCollection()->map(fn(Ticket $t) => [
            'id' => $t->id,
            'title' => $t->title,
            'status' => $t->status,
            'created_at' => $t->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $t->updated_at->format('Y-m-d H:i:s'),
        ]);

        return response()->json([
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'items' => $items,
        ]);
    }

    #[OA\Get(
        path: '/api/v1/tickets/{id}',
        summary: 'Получить карточку заявки + комментарии',
        tags: ['Tickets'],
        security: [['apiKey' => []]],

        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],

        responses: [
            new OA\Response(response: 200, description: 'Заявка получена'),
            new OA\Response(response: 404, description: 'Заявка не найдена')
        ],
    )]
    public function show(int $id): JsonResponse
    {
        $ticket = Ticket::with('comments')->find($id);

        if (!$ticket) {
            return response()->json([
                'error' => ['code' => 'not_found', 'message' => 'Ticket not found'],
            ], 404);
        }

        return response()->json([
            'id' => $ticket->id,
            'title' => $ticket->title,
            'description' => $ticket->description,
            'author_email' => $ticket->author_email,
            'status' => $ticket->status,
            'version' => $ticket->version,
            'created_at' => $ticket->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $ticket->updated_at->format('Y-m-d H:i:s'),
            'comments' => $ticket->comments->map(fn($c) => [
                'id' => $c->id,
                'author' => $c->author,
                'message' => $c->message,
                'created_at' => $c->created_at->format('Y-m-d H:i:s'),
            ]),
        ]);
    }

    #[OA\Post(
        path: '/api/v1/tickets/{id}/events',
        summary: 'Добавить комментарий и (опционально) сменить статус — в одной транзакции',
        tags: ['Tickets'],
        security: [['apiKey' => []]],

        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],

        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'version', type: 'integer', example: '3'),
                    new OA\Property(property: 'status', type: 'string', enum: ['new', 'in_progress', 'done', 'closed'], example: 'done'),
                    new OA\Property(
                        property: 'comment',
                        type: 'object',
                        properties: [
                            new OA\Property(
                                property: 'author',
                                type: 'string',
                                example: 'Support'
                            ),
                            new OA\Property(
                                property: 'message',
                                type: 'string',
                                example: 'Починили, проверьте'
                            ),
                        ]
                    ),
                ],
            ),
        ),

        responses: [
            new OA\Response(response: 200, description: 'Комментарий добавлен'),
            new OA\Response(response: 409, description: 'Конфликт версий')
        ],
    )]
    public function events(int $id, StoreEventRequest $request): JsonResponse
    {
        $now = now()->format('Y-m-d H:i:s');

        return DB::transaction(function () use ($id, $request, $now) {
            $ticket = Ticket::where('id', $id)->lockForUpdate()->first();

            if (!$ticket) {
                return response()->json([
                    'error' => ['code' => 'not_found', 'message' => 'Ticket not found'],
                ], 404);
            }

            if ($ticket->version !== (int) $request->version) {
                return response()->json([
                    'error' => [
                        'code' => 'conflict',
                        'message' => 'Version mismatch',
                        'details' => [
                            'current_version' => $ticket->version,
                            'provided_version' => (int) $request->version,
                        ],
                    ],
                ], 409);
            }

            if ($request->has('comment') && is_array($request->comment)) {
                TicketComment::create([
                    'ticket_id' => $ticket->id,
                    'author' => $request->comment['author'],
                    'message' => $request->comment['message'],
                    'created_at' => $now,
                ]);
            }

            if ($request->filled('status')) {
                $ticket->status = $request->status;
                $ticket->version = $ticket->version + 1;
            }

            $ticket->updated_at = $now;
            $ticket->save();

            return response()->json([
                'id' => $ticket->id,
                'version' => $ticket->version,
                'status' => $ticket->status,
            ]);
        });
    }
}
