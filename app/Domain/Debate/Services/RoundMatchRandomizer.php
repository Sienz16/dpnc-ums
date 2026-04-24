<?php

namespace App\Domain\Debate\Services;

use App\Domain\Debate\Enums\JudgePanelSize;
use App\Domain\Debate\Enums\MatchStatus;
use App\Models\DebateMatch;
use App\Models\Room;
use App\Models\Round;
use App\Models\Team;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RoundMatchRandomizer
{
    /**
     * @return array{matches: EloquentCollection<int, DebateMatch>, unpaired_team: Team|null}
     */
    public function randomize(Round $round): array
    {
        return DB::transaction(function () use ($round): array {
            $this->ensureRoundCanBeReshuffled($round);

            /** @var Collection<int, Team> $teams */
            $teams = Team::query()
                ->where('is_active', true)
                ->orderBy('id')
                ->get()
                ->shuffle()
                ->values();

            if ($teams->count() < 2) {
                throw new InvalidArgumentException('Sekurang-kurangnya dua pasukan aktif diperlukan untuk jana matchup.');
            }

            /** @var Collection<int, Room> $rooms */
            $rooms = Room::query()
                ->where('is_active', true)
                ->orderBy('id')
                ->get()
                ->shuffle()
                ->values();

            $requiredRoomCount = intdiv($teams->count(), 2);

            if ($rooms->count() < $requiredRoomCount) {
                throw new InvalidArgumentException('Bilik aktif tidak mencukupi untuk semua matchup pusingan ini.');
            }

            $round->matches()
                ->where('status', MatchStatus::Pending)
                ->delete();

            $unpairedTeam = $teams->count() % 2 === 1 ? $teams->pop() : null;

            /** @var EloquentCollection<int, DebateMatch> $matches */
            $matches = new EloquentCollection;

            foreach ($teams->chunk(2)->values() as $index => $pair) {
                $pair = $pair->values();

                $matches->push(DebateMatch::query()->create([
                    'round_id' => $round->id,
                    'room_id' => $rooms[$index]->id,
                    'government_team_id' => $pair[0]->id,
                    'opposition_team_id' => $pair[1]->id,
                    'judge_panel_size' => JudgePanelSize::Three,
                    'status' => MatchStatus::Pending,
                    'scheduled_at' => now(),
                ]));
            }

            return [
                'matches' => $matches->load(['round', 'room', 'governmentTeam', 'oppositionTeam']),
                'unpaired_team' => $unpairedTeam,
            ];
        });
    }

    protected function ensureRoundCanBeReshuffled(Round $round): void
    {
        $hasStartedMatch = $round->matches()
            ->where('status', '!=', MatchStatus::Pending)
            ->exists();

        if ($hasStartedMatch) {
            throw new InvalidArgumentException('Matchup tidak boleh di-shuffle selepas ada perlawanan yang sudah bermula atau selesai.');
        }
    }
}
