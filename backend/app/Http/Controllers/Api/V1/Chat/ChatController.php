<?php

namespace App\Http\Controllers\Api\V1\Chat;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChatConversationResource;
use App\Http\Resources\ChatMessageResource;
use App\Models\ChatConversation;
use App\Services\Ai\AiChatService;
use App\Support\Api\ApiQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    public function __construct(private readonly AiChatService $ai) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = ChatConversation::query();

        if (! $user->hasAnyPermission(['chat.view', 'chat.respond'])) {
            $customer = $user->customer()->firstOrFail();
            $query->where('customer_id', $customer->id);
        }

        $conversations = ApiQuery::for($request, $query)
            ->filters(['status'])
            ->sorts(['started_at'])
            ->paginate();

        return ChatConversationResource::collection($conversations)->response();
    }

    public function store(Request $request): JsonResponse
    {
        $customer = $request->user()->customer()->firstOrFail();

        $conversation = ChatConversation::create([
            'customer_id' => $customer->id,
            'session_id' => (string) Str::uuid(),
            'channel' => $request->input('channel', 'web_widget'),
            'status' => 'open',
            'started_at' => now(),
        ]);

        return ChatConversationResource::make($conversation)->response()->setStatusCode(201);
    }

    public function show(Request $request, ChatConversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        return ChatConversationResource::make($conversation->load('messages'))->response();
    }

    public function sendMessage(Request $request, ChatConversation $conversation): JsonResponse
    {
        $this->authorize('respond', $conversation);

        $data = $request->validate(['content' => ['required', 'string', 'max:4000']]);

        $isStaff = $request->user()->hasAnyPermission(['chat.respond']);

        $message = $conversation->messages()->create([
            'sender_type' => $isStaff ? 'staff' : 'customer',
            'sender_id' => $request->user()->id,
            // CLAUDE.md §10: stored as plain text data - never concatenated
            // into a system prompt by anything downstream.
            'content' => $data['content'],
        ]);

        // The bot only replies to the customer's own messages, and only
        // while staff haven't already taken the conversation over - once
        // a human is handling it (status='escalated', or staff sent the
        // last message), the bot stays quiet rather than talking over them.
        $reply = null;
        if (! $isStaff && $conversation->status === 'open') {
            $reply = $this->ai->respond($conversation, $message);
        }

        return response()->json([
            'data' => [
                'message' => ChatMessageResource::make($message),
                'reply' => $reply ? ChatMessageResource::make($reply) : null,
            ],
        ], 201);
    }

    public function close(Request $request, ChatConversation $conversation): JsonResponse
    {
        $this->authorize('respond', $conversation);

        $conversation->update(['status' => 'closed', 'ended_at' => now()]);

        return ChatConversationResource::make($conversation)->response();
    }
}
