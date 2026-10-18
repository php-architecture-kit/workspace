import { useMemo, useState } from 'react';
import { Builder, NodeSlot } from './NodeSlot';
import { collectPorts, findMissing, inputFromLiteral, literalFromInput, type InputType, type InputValue, type MissingArgument } from './tree';
import type { CatalogEntry, EvaluationResponse, NodeDefinition, Preset } from './types';

type LazyOperatorsPageProps = {
    catalog: CatalogEntry[];
    presets: Preset[];
    evaluateUrl: string;
    homeUrl: string;
};

const STAGE_TITLES = {
    definition: 'Definition rejected by ExpressionBuilder',
    input: 'Input rejected',
    evaluation: 'Evaluation failed',
};

function inputsOf(preset: Preset | undefined): Record<string, InputValue> {
    const inputs: Record<string, InputValue> = {};

    Object.entries(preset?.inputs ?? {}).forEach(([name, node]) => {
        inputs[name] = inputFromLiteral(node);
    });

    return inputs;
}

export default function LazyOperatorsPage({ catalog, presets, evaluateUrl, homeUrl }: LazyOperatorsPageProps) {
    const byKey = useMemo(() => Object.fromEntries(catalog.map((entry) => [entry.key, entry])), [catalog]);

    const [revision, setRevision] = useState(0);
    const [tree, setTree] = useState<NodeDefinition | null>(presets[0]?.definition ?? null);
    const [inputs, setInputs] = useState<Record<string, InputValue>>(inputsOf(presets[0]));
    const [response, setResponse] = useState<EvaluationResponse | null>(null);
    const [incomplete, setIncomplete] = useState<MissingArgument[]>([]);

    const ports = collectPorts(tree);
    const error = response !== null && 'error' in response ? response.error : null;
    const definitionErrorPath = error?.stage === 'definition' ? error.path : null;

    const load = (preset: Preset | null) => {
        setTree(preset?.definition ?? null);
        setInputs(inputsOf(preset ?? undefined));
        setResponse(null);
        setIncomplete([]);
        setRevision((current) => current + 1);
    };

    const setInput = (name: string, input: InputValue) => setInputs((current) => ({ ...current, [name]: input }));

    const evaluate = async () => {
        const missing = findMissing(tree, byKey);
        setIncomplete(missing);

        if (missing.length > 0) {
            setResponse(null);

            return;
        }

        const boundInputs: Record<string, NodeDefinition> = {};

        ports.forEach((name) => {
            const input = inputs[name];

            if (input !== undefined && input.text !== '') {
                boundInputs[name] = literalFromInput(input);
            }
        });

        const result = await fetch(evaluateUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ definition: tree, inputs: boundInputs }),
        });

        setResponse(await result.json());
    };

    return (
        <main className="page page-wide">
            <a className="back" href={homeUrl}>
                ← All packages
            </a>
            <header className="page-header">
                <h1>lazy-operators</h1>
                <p className="page-lead">
                    Build an expression tree from the registry's {catalog.length} nodes. The tree is sent as data to{' '}
                    <code>ExpressionBuilder</code>, the package's builder contract, which builds and runs it.
                </p>
            </header>

            <div className="presets">
                {presets.map((preset) => (
                    <button key={preset.name} type="button" className="secondary" title={preset.description} onClick={() => load(preset)}>
                        {preset.name}
                    </button>
                ))}
                <button type="button" className="link" onClick={() => load(null)}>
                    Start empty
                </button>
            </div>

            <Builder.Provider value={{ catalog, byKey, errorPath: definitionErrorPath }}>
                <div className="builder">
                    <section className="panel builder-tree">
                        <h2>Expression tree</h2>
                        <NodeSlot key={revision} value={tree} accepts={['Expression']} path="$" label="Add expression" onChange={setTree} />
                    </section>

                    <aside className="builder-side">
                        <section className="panel">
                            <h2>Inputs</h2>
                            {ports.length === 0 ? (
                                <p className="empty">
                                    No ports in the tree. Add a <em>Runtime → Port</em> node to pass values at evaluation time.
                                </p>
                            ) : (
                                <div className="form">
                                    {ports.map((name) => {
                                        const input = inputs[name] ?? { type: 'float', text: '' };
                                        const invalid = error?.stage === 'input' && error.input === name;

                                        return (
                                            <div key={name} className="field">
                                                <span className="field-label">{name}</span>
                                                <div className="input-row">
                                                    <select
                                                        value={input.type}
                                                        onChange={(event) => setInput(name, { ...input, type: event.target.value as InputType })}
                                                    >
                                                        <option value="int">int</option>
                                                        <option value="float">float</option>
                                                        <option value="string">string</option>
                                                        <option value="bool">bool</option>
                                                        <option value="array">array (JSON)</option>
                                                    </select>
                                                    <input
                                                        type="text"
                                                        value={input.text}
                                                        aria-invalid={invalid}
                                                        onChange={(event) => setInput(name, { ...input, text: event.target.value })}
                                                    />
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}

                            <button type="button" className="evaluate" onClick={evaluate} disabled={tree === null}>
                                Evaluate
                            </button>

                            {incomplete.length > 0 && (
                                <div className="result result-error" role="alert">
                                    <p className="result-source">Not sent: the definition is incomplete</p>
                                    <ul className="missing-list">
                                        {incomplete.map((item) => (
                                            <li key={item.path}>
                                                <strong>{item.node}</strong> needs <code>{item.argument}</code>
                                                <code className="result-path">{item.path}</code>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                            {response !== null && 'result' in response && (
                                <div className="result result-success">
                                    <p className="result-source">result · {response.result.type}</p>
                                    <pre className="result-value">{response.result.display}</pre>
                                </div>
                            )}
                            {error !== null && (
                                <div className="result result-error" role="alert">
                                    <p className="result-source">
                                        {STAGE_TITLES[error.stage]}
                                        {error.input !== null ? ` · input "${error.input}"` : ''}
                                    </p>
                                    <p className="result-exception" title={error.exceptionClass}>
                                        {error.exception}
                                    </p>
                                    <p className="result-message">{error.message}</p>
                                    {error.path !== null && <code className="result-path">{error.path}</code>}
                                </div>
                            )}
                        </section>

                        <details className="panel definition-panel">
                            <summary>
                                Definition <span className="field-row-hint">JSON sent to ExpressionBuilder</span>
                            </summary>
                            <pre className="definition">{JSON.stringify(tree, null, 2)}</pre>
                        </details>
                    </aside>
                </div>
            </Builder.Provider>
        </main>
    );
}
