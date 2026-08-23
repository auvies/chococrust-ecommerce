<?php

namespace App\Policies;

use App\Models\ChatConversation;
use App\Models\User;

class ChatConversationPolicy
{
    public function view(User $user, ChatConversation $conversation): bool
    {
        return $conversation->customer?->user_id === $user->id
            || $user->hasAnyPermission(['chat.view', 'chat.respond']);
    }

    public function respond(User $user, ChatConversation $conversation): bool
    {
        return $conversation->customer?->user_id === $user->id
            || $user->hasAnyPermission(['chat.respond']);
    }
}
