import { useCallback, useEffect, useRef, useState, type ReactNode } from 'react';
import AtmPanel, { BankPanel } from './AtmPanel';
import ElevatorPanel from './ElevatorPanel';
import GraphView from './GraphView';
import type { AtmPanelData, ElevatorPanelData, LogEntry, ScenarioProps, Snapshot, StateView } from './types';

type ScenarioViewProps = {
    scenario: ScenarioProps;
    active: boolean;
};

const INTRO: Record<ScenarioProps['id'], ReactNode> = {
    atm: (
        <>
            One <code>Execution</code> per customer. The customer and the bank write states; every click is a separate request that resumes the saved
            Execution. Try a wrong PIN (it loops back), walking away from the cash (it is retracted and the withdrawal reversed), or answering the
            bank yourself.
        </>
    ),
    elevator: (
        <>
            One <code>Execution</code> that never ends: idle, ride, doors - again and again, every lap re-entering the same components. The doors
            close on whichever comes first, the "close" button or the 5-second timer: a <code>RaceComponent</code> that absorbs the late loser of
            every lap. Call the car, block the doors, overload it or press STOP. Time only moves when you let it.
        </>
    ),
};

export default function ScenarioView({ scenario, active }: ScenarioViewProps) {
    const [snapshot, setSnapshot] = useState<Snapshot<unknown>>(scenario.snapshot);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [auto, setAuto] = useState(false);
    const busyRef = useRef(false);

    const act = useCallback(
        async (action: string, payload: Record<string, unknown> = {}) => {
            if (busyRef.current) {
                return;
            }
            busyRef.current = true;
            setBusy(true);
            try {
                const response = await fetch(scenario.actionUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                    body: JSON.stringify({ action, payload }),
                });
                const body = await response.json();
                if ('error' in body) {
                    setError(body.error);
                } else {
                    setError(null);
                    setSnapshot(body.snapshot);
                }
            } finally {
                busyRef.current = false;
                setBusy(false);
            }
        },
        [scenario.actionUrl],
    );

    const completed = snapshot.status === 'completed';

    useEffect(() => {
        if (!auto || !active || completed) {
            return undefined;
        }
        const timer = window.setInterval(() => void act('tick', { seconds: 1 }), 1000);

        return () => window.clearInterval(timer);
    }, [auto, active, completed, act]);

    return (
        <div className="sm-scenario">
            <p className="page-lead">{INTRO[scenario.id]}</p>

            <div className="sm-toolbar">
                <span className="sm-clock" title="Simulated time">
                    🕒 {snapshot.time}
                </span>
                <button type="button" className="secondary" disabled={busy || completed} onClick={() => act('tick', { seconds: 1 })}>
                    +1 s
                </button>
                <button type="button" className="secondary" disabled={busy || completed} onClick={() => act('tick', { seconds: 5 })}>
                    +5 s
                </button>
                <button type="button" className="secondary" disabled={busy || completed} onClick={() => act('tick', { seconds: 30 })}>
                    +30 s
                </button>
                <label className="atm-check">
                    <input type="checkbox" checked={auto} disabled={completed} onChange={(event) => setAuto(event.target.checked)} /> Auto play (1 s per second)
                </label>
                <span className={`sm-status sm-status-${snapshot.status}`}>{completed ? 'Execution completed' : `${snapshot.pointers.length} pointer(s) waiting`}</span>
                <button type="button" className="link" disabled={busy} onClick={() => act('reset')}>
                    {scenario.id === 'atm' ? 'New customer' : 'Restart'}
                </button>
            </div>

            {error !== null && (
                <div className="result result-error sm-error" role="alert">
                    <p>{error}</p>
                </div>
            )}

            <div className="sm-main">
                <section className="panel sm-simulation">
                    <h2>{scenario.id === 'atm' ? 'ATM' : 'Elevator'}</h2>
                    {scenario.id === 'atm' ? (
                        <AtmPanel panel={snapshot.panel as AtmPanelData} completed={completed} busy={busy} act={act} />
                    ) : (
                        <ElevatorPanel panel={snapshot.panel as ElevatorPanelData} busy={busy} act={act} />
                    )}
                </section>

                <section className="panel sm-graph-panel">
                    <h2>Graph</h2>
                    <p className="field-hint">
                        Boxes are nodes; dashed boxes are the package's components. <span className="sm-legend-active">Filled</span>: a pointer waits there;{' '}
                        <span className="sm-legend-visited">outlined</span>: passed in the last request.
                    </p>
                    <GraphView graph={scenario.graph} pointers={snapshot.pointers} trail={snapshot.trail} />
                </section>
            </div>

            <div className="sm-bottom">
                {scenario.id === 'atm' && <BankPanel panel={snapshot.panel as AtmPanelData} busy={busy} act={act} />}
                <StatesPanel states={snapshot.states} />
                <LogPanel log={snapshot.log} />
            </div>
        </div>
    );
}

function StatesPanel({ states }: { states: StateView[] }) {
    const [technical, setTechnical] = useState(true);

    return (
        <section className="panel">
            <h2>States</h2>
            <label className="atm-check">
                <input type="checkbox" checked={technical} onChange={(event) => setTechnical(event.target.checked)} /> Show the technical state (components'
                per-visit data)
            </label>
            {states
                .filter((state) => technical || !state.technical)
                .map((state) => (
                    <div key={state.name} className={state.technical ? 'sm-state sm-state-technical' : 'sm-state'}>
                        <h3>
                            <code>{state.name}</code>
                        </h3>
                        <dl className="details">
                            {Object.entries(state.details).map(([name, value]) => (
                                <div key={name} className="details-row">
                                    <dt title={name}>{name}</dt>
                                    <dd>{value}</dd>
                                </div>
                            ))}
                        </dl>
                    </div>
                ))}
        </section>
    );
}

function LogPanel({ log }: { log: LogEntry[] }) {
    const [technical, setTechnical] = useState(false);
    const entries = log.filter((entry) => technical || entry.kind !== 'technical').slice().reverse();

    return (
        <section className="panel">
            <h2>Events</h2>
            <label className="atm-check">
                <input type="checkbox" checked={technical} onChange={(event) => setTechnical(event.target.checked)} /> Show technical state changes
            </label>
            <ol className="sm-log">
                {entries.map((entry) => (
                    <li key={entry.id} className={`sm-log-${entry.kind}`}>
                        <span className="sm-log-time">{entry.time}</span> <span className="sm-log-kind">{entry.kind}</span> {entry.text}
                    </li>
                ))}
            </ol>
        </section>
    );
}
