<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceToken extends Model
{
    protected $fillable = ['user_id', 'token', 'platform'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function getAllTokens(): array
    {
        return self::pluck('token')->toArray();
    }

    public static function saveToken(int $userId, string $token, string $platform = 'android'): void
    {
        self::updateOrCreate(
            ['token' => $token],
            ['user_id' => $userId, 'platform' => $platform]
        );
    }

    public static function removeToken(string $token): void
    {
        self::where('token', $token)->delete();
    }
}
