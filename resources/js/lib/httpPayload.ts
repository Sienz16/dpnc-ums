type UnknownRecord = Record<string, unknown>;

const isRecord = (value: unknown): value is UnknownRecord => {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
};

/**
 * Supports both payload shapes:
 * - { data: [...] }
 * - { data: { data: [...] } } (axios-style response wrapper)
 */
export const unwrapData = <T>(response: unknown): T | null => {
    if (Array.isArray(response)) {
        return response as T;
    }

    if (!isRecord(response)) {
        return null;
    }

    const root = 'data' in response ? response.data : response;

    if (Array.isArray(root)) {
        return root as T;
    }

    if (!isRecord(root)) {
        return root as T;
    }

    return ('data' in root ? root.data : root) as T;
};

export const unwrapCollection = <T>(response: unknown): T[] => {
    const data = unwrapData<unknown>(response);

    if (Array.isArray(data)) {
        return data as T[];
    }

    return [];
};
