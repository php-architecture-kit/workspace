import { useState, type FormEvent } from 'react';
import UuidResult from './UuidResult';
import { postJson, type UuidResponse } from './types';

type UuidPageProps = {
    methods: string[];
    namespaces: Record<string, string>;
    generateUrl: string;
    inspectUrl: string;
    homeUrl: string;
};

const DESCRIPTIONS: Record<string, string> = {
    new: 'Default factory for a new identifier (currently v4).',
    v1: 'Time-based, with clock sequence and node identifier.',
    v3: 'Deterministic, from a namespace and a name (MD5).',
    v4: 'Random.',
    v5: 'Deterministic, from a namespace and a name (SHA-1).',
    v6: 'Time-based like v1, reordered for sorting.',
    v7: 'Unix-time-ordered with random bits.',
    v8: 'Custom data (16 bytes).',
    nil: 'All zeros.',
    max: 'All ones.',
};

const FIELDS: Record<string, string[]> = {
    v1: ['time', 'clockSequence', 'nodeIdentifier'],
    v3: ['namespace', 'name'],
    v5: ['namespace', 'name'],
    v6: ['time', 'clockSequence', 'nodeIdentifier'],
    v7: ['time'],
    v8: ['customData'],
};

const CUSTOM_NAMESPACE = '';

function errorField(result: UuidResponse | null): string | null {
    return result !== null && 'error' in result ? result.error.field : null;
}

export default function UuidPage({ methods, namespaces, generateUrl, inspectUrl, homeUrl }: UuidPageProps) {
    const [method, setMethod] = useState('v7');
    const [values, setValues] = useState<Record<string, string>>({ namespace: Object.values(namespaces)[0] ?? '' });
    const [namespacePreset, setNamespacePreset] = useState(Object.values(namespaces)[0] ?? CUSTOM_NAMESPACE);
    const [generateResult, setGenerateResult] = useState<UuidResponse | null>(null);

    const [inspectValue, setInspectValue] = useState('');
    const [inspectResult, setInspectResult] = useState<UuidResponse | null>(null);

    const fields = FIELDS[method] ?? [];
    const invalidField = errorField(generateResult);

    const setValue = (field: string, value: string) => setValues((current) => ({ ...current, [field]: value }));

    const generate = async (event: FormEvent) => {
        event.preventDefault();
        const body: Record<string, string> = { method };
        fields.forEach((field) => {
            body[field] = values[field] ?? '';
        });
        setGenerateResult(await postJson(generateUrl, body));
    };

    const inspect = async (event: FormEvent) => {
        event.preventDefault();
        setInspectResult(await postJson(inspectUrl, { value: inspectValue }));
    };

    const input = (field: string, label: string, props: { placeholder?: string; type?: string; hint?: string } = {}) => (
        <label className="field">
            <span className="field-label">{label}</span>
            <input
                type={props.type ?? 'text'}
                value={values[field] ?? ''}
                placeholder={props.placeholder}
                aria-invalid={invalidField === field}
                onChange={(event) => setValue(field, event.target.value)}
            />
            {props.hint && <span className="field-hint">{props.hint}</span>}
        </label>
    );

    return (
        <main className="page">
            <a className="back" href={homeUrl}>
                ← All packages
            </a>
            <header className="page-header">
                <h1>uuid</h1>
                <p className="page-lead">
                    Calls <code>PhpArchitecture\Uuid\Foundation\Uuid</code> with the values you enter. Validation is done
                    by the package; errors show the exception it threw.
                </p>
            </header>

            <div className="panels">
                <section className="panel">
                    <h2>Generate</h2>
                    <form onSubmit={generate} className="form">
                        <label className="field">
                            <span className="field-label">Method</span>
                            <select value={method} onChange={(event) => setMethod(event.target.value)}>
                                {methods.map((name) => (
                                    <option key={name} value={name}>
                                        Uuid::{name}()
                                    </option>
                                ))}
                            </select>
                            <span className="field-hint">{DESCRIPTIONS[method]}</span>
                        </label>

                        {fields.includes('time') &&
                            input('time', 'Time (UTC, optional)', {
                                type: 'datetime-local',
                                hint: 'Passed as a FrozenClock from php-architecture-kit/clock. Empty = system clock.',
                            })}
                        {fields.includes('clockSequence') &&
                            input('clockSequence', 'Clock sequence (optional)', { placeholder: '0–16383' })}
                        {fields.includes('nodeIdentifier') &&
                            input('nodeIdentifier', 'Node identifier (optional)', { placeholder: '12 hex characters' })}

                        {fields.includes('namespace') && (
                            <label className="field">
                                <span className="field-label">Namespace</span>
                                <select
                                    value={namespacePreset}
                                    onChange={(event) => {
                                        setNamespacePreset(event.target.value);
                                        setValue('namespace', event.target.value);
                                    }}
                                >
                                    {Object.entries(namespaces).map(([name, value]) => (
                                        <option key={name} value={value}>
                                            {name} ({value})
                                        </option>
                                    ))}
                                    <option value={CUSTOM_NAMESPACE}>Custom…</option>
                                </select>
                            </label>
                        )}
                        {fields.includes('namespace') &&
                            namespacePreset === CUSTOM_NAMESPACE &&
                            input('namespace', 'Custom namespace UUID')}
                        {fields.includes('name') && input('name', 'Name', { placeholder: 'example.com' })}
                        {fields.includes('customData') &&
                            input('customData', 'Custom data (hex)', {
                                placeholder: '32 hex characters = 16 bytes',
                                hint: 'Converted with hex2bin() before calling Uuid::v8().',
                            })}

                        <button type="submit">Generate</button>
                    </form>
                    <UuidResult result={generateResult} />
                </section>

                <section className="panel">
                    <h2>Inspect</h2>
                    <form onSubmit={inspect} className="form">
                        <label className="field">
                            <span className="field-label">UUID string</span>
                            <input
                                type="text"
                                value={inspectValue}
                                placeholder="6ba7b810-9dad-11d1-80b4-00c04fd430c8"
                                aria-invalid={inspectResult !== null && 'error' in inspectResult}
                                onChange={(event) => setInspectValue(event.target.value)}
                            />
                            <span className="field-hint">Calls Uuid::fromString() and reads the result.</span>
                        </label>
                        <button type="submit">Inspect</button>
                    </form>
                    <UuidResult result={inspectResult} />
                </section>
            </div>
        </main>
    );
}
