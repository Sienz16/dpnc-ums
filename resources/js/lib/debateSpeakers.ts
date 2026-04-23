import type { SpeakerPosition, TeamMember, TeamSide } from '@/types/debate';

export const orderedSpeakerPositions: SpeakerPosition[] = ['speaker_1', 'speaker_2', 'speaker_3', 'speaker_4'];

export const speakerPositionLabel = (position: SpeakerPosition): string => {
    switch (position) {
        case 'speaker_1':
            return 'Pendebat 1';
        case 'speaker_2':
            return 'Pendebat 2';
        case 'speaker_3':
            return 'Pendebat 3';
        case 'speaker_4':
            return 'Pendebat 4 (Simpanan)';
    }
};

export const speakerRoleLabel = (position: SpeakerPosition, side: TeamSide): string => {
    if (side === 'government') {
        switch (position) {
            case 'speaker_1':
                return 'Perdana Menteri';
            case 'speaker_2':
                return 'Timbalan Perdana Menteri';
            case 'speaker_3':
                return 'Menteri Pertahanan';
            case 'speaker_4':
                return 'Simpanan Kerajaan';
        }
    }

    switch (position) {
        case 'speaker_1':
            return 'Ketua Pembangkang';
        case 'speaker_2':
            return 'Timbalan Ketua Pembangkang';
        case 'speaker_3':
            return 'Pembangkang Pertama';
        case 'speaker_4':
            return 'Simpanan Pembangkang';
    }
};

export const scoredSpeakerPositions: SpeakerPosition[] = ['speaker_1', 'speaker_2', 'speaker_3'];

export const sortMembersBySpeakerPosition = (members: TeamMember[] = []): TeamMember[] => {
    return [...members].sort(
        (left, right) => orderedSpeakerPositions.indexOf(left.speaker_position) - orderedSpeakerPositions.indexOf(right.speaker_position),
    );
};

export const scoredMembers = (members: TeamMember[] = []): TeamMember[] => {
    return sortMembersBySpeakerPosition(members).filter((member) => member.speaker_position !== 'speaker_4');
};
