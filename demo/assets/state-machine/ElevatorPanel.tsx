import { useEffect, useRef } from 'react';
import type { Act, ElevatorPanelData } from './types';

const ROW_H = 56;

type ElevatorPanelProps = {
    panel: ElevatorPanelData;
    busy: boolean;
    act: Act;
};

export default function ElevatorPanel({ panel, busy, act }: ElevatorPanelProps) {
    const { car, calls } = panel;
    const floors = Array.from({ length: panel.floors }, (_, index) => panel.floors - 1 - index);
    const top = panel.floors - 1;

    // while riding, the car is part of the way to the next floor (simulated time, not wall clock)
    const position = car.floor + (car.moving ? (car.direction === 'up' ? 1 : -1) * car.progress : 0);

    // reaching the floor and opening the doors arrive in one response: open the doors only once the car stopped sliding
    const previousPosition = useRef(position);
    const arriving = previousPosition.current !== position && car.doors === 'open';
    useEffect(() => {
        previousPosition.current = position;
    }, [position]);

    return (
        <div className="lift">
            <div className="lift-building" style={{ height: panel.floors * ROW_H }}>
                {floors.map((floor) => (
                    <div key={floor} className="lift-floor" style={{ top: (top - floor) * ROW_H, height: ROW_H }}>
                        <span className="lift-floor-number">{floor}</span>
                        <span className="lift-hall">
                            {floor < top && (
                                <button
                                    type="button"
                                    className={calls.hall.includes(`${floor}:up`) ? 'lift-call lift-call-on' : 'lift-call'}
                                    disabled={busy}
                                    aria-label={`Call up from floor ${floor}`}
                                    onClick={() => act('hallCall', { floor, direction: 'up' })}
                                >
                                    ▲
                                </button>
                            )}
                            {floor > 0 && (
                                <button
                                    type="button"
                                    className={calls.hall.includes(`${floor}:down`) ? 'lift-call lift-call-on' : 'lift-call'}
                                    disabled={busy}
                                    aria-label={`Call down from floor ${floor}`}
                                    onClick={() => act('hallCall', { floor, direction: 'down' })}
                                >
                                    ▼
                                </button>
                            )}
                        </span>
                    </div>
                ))}

                <div className="lift-shaft">
                    <div
                        className={['lift-car', `lift-car-${car.doors}`, arriving ? 'lift-car-arriving' : '', car.halted ? 'lift-car-halted' : '', car.alarm ? 'lift-car-alarm' : ''].join(' ')}
                        style={{
                            top: (top - position) * ROW_H + 4,
                            height: ROW_H - 8,
                        }}
                    >
                        <span className="lift-door lift-door-left" />
                        <span className="lift-door lift-door-right" />
                        <span className="lift-load">{car.load > 0 ? `${car.load}👤` : ''}</span>
                    </div>
                </div>
            </div>

            <div className="lift-panel">
                <div className="lift-display">
                    <span className="lift-display-floor">{car.floor}</span>
                    <span>{car.direction === 'up' ? '▲' : car.direction === 'down' ? '▼' : '•'}</span>
                    <span className="field-hint">
                        doors {car.doors}
                        {car.moving ? ', moving' : ''}
                    </span>
                </div>
                {car.alarm && <p className="lift-warning">Overload! {car.load} / {panel.capacity}</p>}
                {car.halted && <p className="lift-warning">Emergency stop</p>}

                <div className="lift-buttons">
                    {floors.map((floor) => (
                        <button
                            key={floor}
                            type="button"
                            className={calls.car.includes(floor) ? 'lift-button lift-button-on' : 'lift-button'}
                            disabled={busy}
                            onClick={() => act('carCall', { floor })}
                        >
                            {floor}
                        </button>
                    ))}
                </div>

                <div className="atm-row">
                    <button type="button" className="secondary" disabled={busy || car.doors !== 'open'} onClick={() => act('closeButton')}>
                        ▶◀ Close doors
                    </button>
                    <button type="button" className={panel.stop ? 'lift-stop lift-stop-on' : 'lift-stop'} disabled={busy} onClick={() => act('stop', { on: !panel.stop })}>
                        {panel.stop ? 'Release STOP' : 'STOP'}
                    </button>
                </div>

                <div className="atm-row">
                    <span className="field-hint">
                        Passengers {car.load} / {panel.capacity}
                    </span>
                    <button type="button" className="secondary" disabled={busy || car.doors !== 'open'} onClick={() => act('passengers', { delta: 1 })}>
                        +1 in
                    </button>
                    <button type="button" className="secondary" disabled={busy || car.doors !== 'open'} onClick={() => act('passengers', { delta: 3 })}>
                        +3 in
                    </button>
                    <button type="button" className="secondary" disabled={busy || car.doors !== 'open' || car.load === 0} onClick={() => act('passengers', { delta: -1 })}>
                        −1 out
                    </button>
                </div>

                <label className="atm-check">
                    <input type="checkbox" checked={panel.obstruction} disabled={busy} onChange={(event) => act('obstruction', { on: event.target.checked })} /> Something
                    blocks the doors (photocell)
                </label>
            </div>
        </div>
    );
}
