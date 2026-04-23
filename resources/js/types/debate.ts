export type UserRole = 'superadmin' | 'judge';

export interface User {
    id: number;
    name: string;
    email: string;
    role: UserRole;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

export interface Round {
    id: number;
    name: string;
    sequence: number | null;
    created_at: string;
    updated_at: string;
}

export interface Room {
    id: number;
    name: string;
    location: string | null;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

export interface Team {
    id: number;
    name: string;
    institution: string | null;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    members?: TeamMember[];
}

export type SpeakerPosition = 'speaker_1' | 'speaker_2' | 'speaker_3' | 'speaker_4';
export type TeamSide = 'government' | 'opposition';

export interface TeamMember {
    id: number;
    team_id: number;
    full_name: string;
    speaker_position: SpeakerPosition;
    speaker_position_label?: string;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

export type MatchStatus = 'pending' | 'in_progress' | 'completed';
export type MatchCompletionType = 'normal' | 'force_completed';
export type MatchResultState = 'provisional' | 'final';
export type JudgePanelSize = 1 | 3 | 5 | 7;

export interface Match {
    id: number;
    round_id: number;
    room_id: number;
    government_team_id: number;
    opposition_team_id: number;
    judge_panel_size: JudgePanelSize;
    status: MatchStatus;
    completion_type: MatchCompletionType | null;
    result_state: MatchResultState | null;
    scheduled_at: string | null;
    created_at: string;
    updated_at: string;
    round?: Round;
    room?: Room;
    government_team?: Team;
    opposition_team?: Team;
    judge_assignments?: JudgeAssignment[];
    unavailable_judge_ids?: number[];
    result?: MatchResult;
    score_sheets?: ScoreSheet[];
}

export type AssignedMode = 'manual' | 'random';

export interface JudgeAssignment {
    id: number;
    match_id: number;
    judge_id: number;
    assigned_mode: AssignedMode;
    checked_in_at: string | null;
    submitted_at: string | null;
    created_at: string;
    updated_at: string;
    judge?: User;
}

export type WinnerSide = 'government' | 'opposition';
export type ScoreSheetState = 'draft' | 'submitted';

export interface ScoreSheet {
    id: number;
    match_id: number;
    judge_id: number;
    mark_pm: number;
    mark_tpm: number;
    mark_m1: number;
    mark_kp: number;
    mark_tkp: number;
    mark_p1: number;
    mark_penggulungan_gov: number;
    mark_penggulungan_opp: number;
    gov_total: number;
    opp_total: number;
    margin: number;
    winner_side: WinnerSide;
    best_debater_member_id: number;
    state: ScoreSheetState;
    submitted_at: string | null;
    created_at: string;
    updated_at: string;
    judge?: User;
}

export interface MatchResult {
    id: number;
    match_id: number;
    winner_side: WinnerSide;
    winner_vote_count: number;
    loser_vote_count: number;
    official_margin: number;
    official_team_score_government: number;
    official_team_score_opposition: number;
    best_speaker_member_id: number;
    is_force_completed: boolean;
    is_provisional: boolean;
    calculated_at: string;
    created_at: string;
    updated_at: string;
    best_speaker?: TeamMember;
}

export interface AuditLog {
    id: number;
    actor_user_id: number;
    entity_type: string;
    entity_id: number;
    action: string;
    reason: string | null;
    metadata_json: any;
    created_at: string;
    updated_at: string;
    actor?: User;
}

export interface TeamRanking {
    team_id: number;
    team_name: string;
    win_count: number;
    judge_count: number;
    average_margin: number;
    average_team_score: number;
}

export interface SpeakerRanking {
    speaker_id: number;
    speaker_name: string;
    team_name: string;
    average_official_points_per_appearance: number;
    best_speaker_wins_count: number;
    average_score_per_appearance: number;
}
