<?php

use App\Models\BattleParticipant;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('battle.{battleId}', function (User $user, int $battleId) {
    if ($user->is_admin) {
        return true;
    }

    return BattleParticipant::query()
        ->where('battle_id', $battleId)
        ->where('user_id', $user->id)
        ->exists();
});
