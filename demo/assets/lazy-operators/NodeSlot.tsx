import { createContext, useContext, useEffect, useState, type CSSProperties } from 'react';
import { createNode, findMissing, fits, groupHue, groupIcon, isNode, nativeText, parseNative, summarize } from './tree';
import type { CatalogArgument, CatalogEntry, NodeDefinition } from './types';

type BuilderContext = {
    catalog: CatalogEntry[];
    byKey: Record<string, CatalogEntry>;
    errorPath: string | null;
};

export const Builder = createContext<BuilderContext>({ catalog: [], byKey: {}, errorPath: null });

function contains(errorPath: string | null, path: string): boolean {
    return errorPath !== null && (errorPath === path || errorPath.startsWith(`${path}.`) || errorPath.startsWith(`${path}[`));
}

function GroupIcon({ group }: { group: string }) {
    return (
        <span className="group-icon" style={{ '--hue': groupHue(group) } as CSSProperties} aria-hidden="true">
            {groupIcon(group)}
        </span>
    );
}

/** Home Assistant-style "add" dialog: search, then pick from a grouped list of fitting nodes. */
function Picker({ accepts, onPick, onClose }: { accepts: string[]; onPick: (entry: CatalogEntry) => void; onClose: () => void }) {
    const { catalog } = useContext(Builder);
    const [query, setQuery] = useState('');
    const needle = query.trim().toLowerCase();

    const groups = new Map<string, CatalogEntry[]>();
    catalog
        .filter((entry) => fits(entry, accepts))
        .filter((entry) =>
            needle === '' ||
            [entry.name, entry.key, entry.group, entry.description ?? ''].some((text) => text.toLowerCase().includes(needle)),
        )
        .forEach((entry) => groups.set(entry.group, [...(groups.get(entry.group) ?? []), entry]));

    return (
        <div className="picker-backdrop" onClick={onClose}>
            <div
                className="picker"
                role="dialog"
                aria-label="Choose an expression"
                onClick={(event) => event.stopPropagation()}
                onKeyDown={(event) => event.key === 'Escape' && onClose()}
            >
                <div className="picker-header">
                    <input
                        autoFocus
                        type="search"
                        value={query}
                        placeholder={`Search ${accepts.join(' | ')}…`}
                        onChange={(event) => setQuery(event.target.value)}
                    />
                    <button type="button" className="icon-button" onClick={onClose} aria-label="Close">
                        ✕
                    </button>
                </div>
                <div className="picker-list">
                    {groups.size === 0 && <p className="empty">No matching expressions.</p>}
                    {[...groups.entries()]
                        .sort(([a], [b]) => a.localeCompare(b))
                        .map(([group, entries]) => (
                            <div key={group}>
                                <div className="picker-group">{group}</div>
                                {entries.map((entry) => (
                                    <button
                                        key={entry.key}
                                        type="button"
                                        className="picker-item"
                                        disabled={!entry.available}
                                        title={entry.unavailableReason ?? entry.description ?? undefined}
                                        onClick={() => onPick(entry)}
                                    >
                                        <GroupIcon group={entry.group} />
                                        <span className="picker-text">
                                            <span className="picker-name">{entry.name}</span>
                                            <span className="picker-meta">{entry.available ? entry.formula : entry.unavailableReason}</span>
                                        </span>
                                        <span className="type-chip">{entry.type}</span>
                                    </button>
                                ))}
                            </div>
                        ))}
                </div>
            </div>
        </div>
    );
}

function AddButton({
    accepts,
    label,
    invalid,
    required,
    onPick,
}: {
    accepts: string[];
    label: string;
    invalid?: boolean;
    required?: boolean;
    onPick: (entry: CatalogEntry) => void;
}) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <button
                type="button"
                className={`add-button${invalid ? ' add-button-error' : required ? ' add-button-required' : ''}`}
                onClick={() => setOpen(true)}
            >
                <span className="add-plus">+</span> {label}
                {required && <span className="required-chip">required</span>}
                <span className="add-accepts">{accepts.join(' | ')}</span>
            </button>
            {open && (
                <Picker
                    accepts={accepts}
                    onClose={() => setOpen(false)}
                    onPick={(entry) => {
                        setOpen(false);
                        onPick(entry);
                    }}
                />
            )}
        </>
    );
}

type SlotProps = {
    value: unknown;
    accepts: string[];
    path: string;
    label: string;
    required?: boolean;
    onChange: (value: NodeDefinition | null) => void;
};

export function NodeSlot({ value, accepts, path, label, required, onChange }: SlotProps) {
    const { errorPath } = useContext(Builder);
    const [created, setCreated] = useState(false);

    if (!isNode(value)) {
        return (
            <AddButton
                accepts={accepts}
                label={label}
                invalid={errorPath === path}
                required={required}
                onPick={(entry) => {
                    setCreated(true);
                    onChange(createNode(entry));
                }}
            />
        );
    }

    return <NodeCard value={value} accepts={accepts} path={path} onChange={onChange} defaultExpanded={created || path === '$'} />;
}

type CardProps = {
    value: NodeDefinition;
    accepts: string[];
    path: string;
    onChange: (value: NodeDefinition | null) => void;
    defaultExpanded: boolean;
};

function NodeCard({ value, accepts, path, onChange, defaultExpanded }: CardProps) {
    const { byKey, errorPath } = useContext(Builder);
    const [expanded, setExpanded] = useState(defaultExpanded);
    const [replacing, setReplacing] = useState(false);
    const errorInside = contains(errorPath, path);

    useEffect(() => {
        if (errorInside) {
            setExpanded(true);
        }
    }, [errorInside, errorPath]);

    const entry = byKey[value.expression];

    if (entry === undefined) {
        return (
            <div className="card card-error">
                <div className="card-row">
                    Unknown expression <code>{value.expression}</code>
                    <button type="button" className="icon-button" onClick={() => onChange(null)}>
                        ✕
                    </button>
                </div>
            </div>
        );
    }

    const setArgument = (name: string, argument: unknown) => {
        const args = { ...value.arguments };

        if (argument === undefined) {
            delete args[name];
        } else {
            args[name] = argument;
        }

        onChange({ ...value, arguments: args });
    };

    const summary = summarize(value, byKey);
    const missing = findMissing(value, byKey, path).length;

    return (
        <div className={`card${expanded ? ' card-open' : ''}${errorPath === path ? ' card-error' : errorInside ? ' card-error-inside' : ''}`}>
            <div
                className="card-row"
                role="button"
                tabIndex={0}
                aria-expanded={expanded}
                onClick={() => setExpanded(!expanded)}
                onKeyDown={(event) => (event.key === 'Enter' || event.key === ' ') && setExpanded(!expanded)}
            >
                <GroupIcon group={entry.group} />
                <span className="card-text">
                    <span className="card-title" title={summary}>
                        {summary}
                    </span>
                    <span className="card-subtitle">
                        {entry.name} · {entry.type}
                        {missing > 0 && <span className="missing-count"> · {missing} missing</span>}
                    </span>
                </span>
                <span className="card-actions" onClick={(event) => event.stopPropagation()}>
                    <button type="button" className="icon-button" title="Replace" onClick={() => setReplacing(true)}>
                        ⇄
                    </button>
                    <button type="button" className="icon-button" title="Remove" onClick={() => onChange(null)}>
                        ✕
                    </button>
                </span>
                <span className="chevron" aria-hidden="true">
                    ▾
                </span>
            </div>

            {replacing && (
                <Picker
                    accepts={accepts}
                    onClose={() => setReplacing(false)}
                    onPick={(next) => {
                        setReplacing(false);
                        onChange(createNode(next));
                    }}
                />
            )}

            {expanded && (
                <div className="card-body" key={value.expression}>
                    {entry.description && <p className="card-description">{entry.description}</p>}
                    {entry.arguments.length === 0 && <p className="card-description">No arguments.</p>}
                    {entry.arguments.map((argument) => (
                        <Field
                            key={argument.name}
                            argument={argument}
                            value={value.arguments[argument.name]}
                            path={`${path}.arguments.${argument.name}`}
                            onChange={(next) => setArgument(argument.name, next)}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}

type FieldProps = {
    argument: CatalogArgument;
    value: unknown;
    path: string;
    onChange: (value: unknown) => void;
};

function Field({ argument, value, path, onChange }: FieldProps) {
    const { errorPath } = useContext(Builder);
    const inline = argument.kind === 'native' || argument.kind === 'enum';

    return (
        <div className={`field-row${inline ? ' field-row-inline' : ''}${errorPath === path ? ' field-row-error' : ''}`}>
            <div className="field-row-label" title={argument.description ?? undefined}>
                <span className="field-row-name">{argument.name}</span>
                <span className="field-row-hint">
                    {argument.type}
                    {argument.kind === 'list' ? ' · any number' : ''}
                    {argument.optional && argument.kind !== 'list' ? ' · optional' : ''}
                </span>
            </div>
            <div className="field-row-editor">
                <ArgumentEditor argument={argument} value={value} path={path} onChange={onChange} />
            </div>
        </div>
    );
}

function ArgumentEditor({ argument, value, path, onChange }: FieldProps) {
    switch (argument.kind) {
        case 'expression':
            return (
                <NodeSlot
                    value={value}
                    accepts={argument.accepts}
                    path={path}
                    label={`Add ${argument.name}`}
                    required={!argument.optional}
                    onChange={(next) => onChange(next ?? undefined)}
                />
            );

        case 'list': {
            const items = Array.isArray(value) ? value : [];

            return (
                <div className="stack">
                    {items.map((item, index) => (
                        <NodeSlot
                            key={index}
                            value={item}
                            accepts={argument.accepts}
                            path={`${path}[${index}]`}
                            label={`Add ${argument.name}`}
                            onChange={(next) =>
                                onChange(next === null ? items.filter((_, i) => i !== index) : items.map((current, i) => (i === index ? next : current)))
                            }
                        />
                    ))}
                    <AddButton accepts={argument.accepts} label={`Add ${argument.name}`} onPick={(entry) => onChange([...items, createNode(entry)])} />
                </div>
            );
        }

        case 'cases': {
            const cases = Array.isArray(value) ? (value as Record<string, unknown>[]) : [];

            return (
                <div className="stack">
                    {cases.map((current, index) => (
                        <div key={index} className="case">
                            <div className="case-header">
                                <span>Case {index + 1}</span>
                                <button type="button" className="icon-button" title="Remove case" onClick={() => onChange(cases.filter((_, i) => i !== index))}>
                                    ✕
                                </button>
                            </div>
                            {(argument.fields ?? []).map((field) => (
                                <Field
                                    key={field.name}
                                    argument={field}
                                    value={current[field.name]}
                                    path={`${path}[${index}].${field.name}`}
                                    onChange={(next) => onChange(cases.map((item, i) => (i === index ? { ...item, [field.name]: next } : item)))}
                                />
                            ))}
                        </div>
                    ))}
                    <button type="button" className="add-button" onClick={() => onChange([...cases, {}])}>
                        <span className="add-plus">+</span> Add case
                    </button>
                </div>
            );
        }

        case 'enum':
            return (
                <select value={typeof value === 'string' ? value : ''} onChange={(event) => onChange(event.target.value || undefined)}>
                    <option value="" disabled={!argument.optional}>
                        {argument.optional ? `Default${argument.defaultValue ? ` (${argument.defaultValue})` : ''}` : 'Choose…'}
                    </option>
                    {(argument.options ?? []).map((option) => (
                        <option key={option} value={option}>
                            {option}
                        </option>
                    ))}
                </select>
            );

        case 'native':
            return <NativeInput type={argument.nativeType ?? 'string'} value={value} onChange={onChange} />;

        case 'callback':
            return <p className="unsupported">A Closure cannot be described as data, so the builder does not support this argument.</p>;
    }
}

function NativeInput({ type, value, onChange }: { type: string; value: unknown; onChange: (value: unknown) => void }) {
    const [text, setText] = useState(nativeText(value));

    if (type === 'bool') {
        return (
            <select value={value === true ? 'true' : 'false'} onChange={(event) => onChange(event.target.value === 'true')}>
                <option value="false">false</option>
                <option value="true">true</option>
            </select>
        );
    }

    const update = (next: string) => {
        setText(next);
        onChange(parseNative(type, next));
    };

    if (type === 'array') {
        return <textarea rows={3} value={text} onChange={(event) => update(event.target.value)} placeholder="JSON, e.g. [1, 2, 3]" />;
    }

    return (
        <input
            type="text"
            inputMode={type === 'int' || type === 'float' ? 'decimal' : undefined}
            value={text}
            placeholder={type}
            onChange={(event) => update(event.target.value)}
        />
    );
}
