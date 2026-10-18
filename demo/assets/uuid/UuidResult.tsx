import type { UuidResponse } from './types';

export default function UuidResult({ result }: { result: UuidResponse | null }) {
    if (result === null) {
        return null;
    }

    if ('error' in result) {
        const { error } = result;

        return (
            <div className="result result-error" role="alert">
                <p className="result-source">
                    {error.source === 'package' ? 'Rejected by the uuid package' : 'Rejected by the form'}
                </p>
                <p className="result-exception" title={error.exceptionClass}>
                    {error.exception}
                </p>
                <p className="result-message">{error.message}</p>
            </div>
        );
    }

    const { uuid } = result;
    const rows: [string, string | number | null][] = [
        ['Version', uuid.version],
        ['Time', uuid.time],
        ['Clock sequence', uuid.clockSequence],
        ['Node', uuid.node],
        ['Binary (hex)', uuid.binaryHex],
        ['Valid', uuid.valid ? 'yes' : 'no'],
        ['Provider', uuid.provider],
    ];

    return (
        <div className="result result-success">
            <p className="result-source">{uuid.call}</p>
            <p className="result-value">{uuid.value}</p>
            <dl className="details">
                {rows.map(([label, value]) => (
                    <div key={label} className="details-row">
                        <dt>{label}</dt>
                        <dd>{value ?? '—'}</dd>
                    </div>
                ))}
            </dl>
        </div>
    );
}
