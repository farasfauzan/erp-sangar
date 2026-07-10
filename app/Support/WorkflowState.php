<?php

namespace App\Support;

use Illuminate\Http\Exceptions\HttpResponseException;

final class WorkflowState
{
    /**
     * Stop a workflow action when its source document is not at an allowed stage.
     *
     * @param  array<int, string>  $allowedStates
     */
    public static function require(string $actualState, array $allowedStates, string $message): void
    {
        if (! in_array($actualState, $allowedStates, true)) {
            self::fail($message);
        }
    }

    public static function requireAmount(float|int|string $actualAmount, float|int|string $expectedAmount, string $message): void
    {
        if (round((float) $actualAmount, 2) !== round((float) $expectedAmount, 2)) {
            self::fail($message);
        }
    }

    public static function fail(string $message): never
    {
        throw new HttpResponseException(response()->json([
            'message' => $message,
        ], 422));
    }
}
