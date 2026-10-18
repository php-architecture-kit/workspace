import { useState } from 'react';
import ScenarioView from './ScenarioView';
import type { ScenarioProps } from './types';

type StateMachinePageProps = {
    scenarios: ScenarioProps[];
    homeUrl: string;
};

export default function StateMachinePage({ scenarios, homeUrl }: StateMachinePageProps) {
    const [current, setCurrent] = useState(scenarios[0]?.id);

    return (
        <main className="page page-wide">
            <a className="back" href={homeUrl}>
                ← All packages
            </a>
            <header className="page-header">
                <h1>state-machine</h1>
            </header>

            <div className="sm-tabs" role="tablist">
                {scenarios.map((scenario) => (
                    <button
                        key={scenario.id}
                        type="button"
                        role="tab"
                        aria-selected={scenario.id === current}
                        className={scenario.id === current ? 'sm-tab sm-tab-active' : 'sm-tab'}
                        onClick={() => setCurrent(scenario.id)}
                    >
                        {scenario.title}
                    </button>
                ))}
            </div>

            {scenarios.map((scenario) => (
                <div key={scenario.id} hidden={scenario.id !== current}>
                    <ScenarioView scenario={scenario} active={scenario.id === current} />
                </div>
            ))}
        </main>
    );
}
