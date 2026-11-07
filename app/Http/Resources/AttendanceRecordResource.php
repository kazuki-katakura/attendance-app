<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class AttendanceRecordResource extends JsonResource
{
    /**
     * 一覧・詳細で共通の形。関連データは読み込まれているときだけ含める。
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user_name' => $this->whenLoaded('user', fn (): string => $this->user->name),
            'user' => new UserResource($this->whenLoaded('user')),
            'date' => $this->date?->toDateString(),
            'clock_in' => $this->clock_in,
            'clock_out' => $this->clock_out,
            'total_time' => $this->formatDuration($this->total_time),
            'total_break_time' => $this->formatDuration($this->total_break_time),
            'comment' => $this->comment,
            'breaks' => AttendanceBreakResource::collection($this->whenLoaded('breaks')),
            'applications' => ApplicationResource::collection($this->whenLoaded('applications')),
        ];
    }

    /**
     * 「08:00:00」を「08:00」にする。
     */
    private function formatDuration(?string $time): ?string
    {
        return $time ? Carbon::parse($time)->format('H:i') : null;
    }
}
