export type UuidDetails = {
    call: string;
    value: string;
    version: number;
    time: string | null;
    clockSequence: number | null;
    node: string | null;
    binaryHex: string;
    valid: boolean;
    provider: string | null;
};

export type UuidError = {
    source: 'form' | 'package';
    exception: string;
    exceptionClass: string;
    message: string;
    field: string | null;
};

export type UuidResponse = { uuid: UuidDetails } | { error: UuidError };

export async function postJson(url: string, body: Record<string, string>): Promise<UuidResponse> {
    const response = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(body),
    });

    return response.json();
}
