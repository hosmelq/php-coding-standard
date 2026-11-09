<?php

if ($enabled) {
    Passport::refreshToken()->whereIn('access_token_id', $tokens->select('access_token_id'))
        ->update(['revoked' => true]);
    Passport::authCode()->where('client_id', $client)
        ->where('user_id', $user->userId)->update(['revoked' => true, 'revoked_at' => now()]);
}

-----
<?php

if ($enabled) {
    Passport::refreshToken()
        ->whereIn('access_token_id', $tokens->select('access_token_id'))
        ->update(['revoked' => true]);
    Passport::authCode()
        ->where('client_id', $client)
        ->where('user_id', $user->userId)
        ->update(['revoked' => true, 'revoked_at' => now()]);
}
