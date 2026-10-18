import { useState, type ReactNode } from 'react';
import type { Act, AtmPanelData } from './types';

type AtmPanelProps = {
    panel: AtmPanelData;
    completed: boolean;
    busy: boolean;
    act: Act;
};

export default function AtmPanel({ panel, completed, busy, act }: AtmPanelProps) {
    const { atm } = panel;
    const [pin, setPin] = useState('');
    const [amount, setAmount] = useState('');
    const [receipt, setReceipt] = useState(true);

    const enterPin = async () => {
        await act('key', { type: 'pin', value: pin });
        setPin('');
    };

    const withdraw = (value: number) => act('key', { type: 'amount', value, receipt });

    return (
        <div className="atm">
            <div className="atm-screen" aria-live="polite">
                {atm.notice !== null && <p className="atm-notice">{atm.notice}</p>}
                <p className="atm-text">{atm.screen}</p>
                {atm.mode === 'finished' && atm.outcome !== null && <p className="atm-outcome">{atm.outcome}</p>}
            </div>

            <div className="atm-controls">
                {atm.mode === 'insert-card' && (
                    <div className="atm-row">
                        {panel.cards.map((card) => (
                            <button key={card.number} type="button" className="secondary" disabled={busy} onClick={() => act('insertCard', { card: card.number })}>
                                Insert: {card.label}
                            </button>
                        ))}
                    </div>
                )}

                {atm.mode === 'pin' && (
                    <form
                        className="atm-row"
                        onSubmit={(event) => {
                            event.preventDefault();
                            void enterPin();
                        }}
                    >
                        <input
                            className="atm-pin"
                            type="password"
                            inputMode="numeric"
                            autoComplete="off"
                            maxLength={4}
                            placeholder="••••"
                            value={pin}
                            aria-label="PIN"
                            onChange={(event) => setPin(event.target.value.replace(/\D/g, ''))}
                        />
                        <button type="submit" disabled={busy || pin.length !== 4}>
                            Enter
                        </button>
                        <button type="button" className="secondary" disabled={busy} onClick={() => act('key', { type: 'cancel' })}>
                            Cancel
                        </button>
                    </form>
                )}

                {atm.mode === 'amount' && (
                    <div className="atm-amount">
                        <div className="atm-row">
                            {panel.quickAmounts.map((value) => (
                                <button key={value} type="button" className="secondary" disabled={busy} onClick={() => withdraw(value)}>
                                    {value}
                                </button>
                            ))}
                        </div>
                        <form
                            className="atm-row"
                            onSubmit={(event) => {
                                event.preventDefault();
                                void withdraw(Number(amount));
                            }}
                        >
                            <input
                                className="atm-other"
                                inputMode="numeric"
                                placeholder="Other amount"
                                value={amount}
                                aria-label="Other amount"
                                onChange={(event) => setAmount(event.target.value.replace(/\D/g, ''))}
                            />
                            <button type="submit" disabled={busy || amount === ''}>
                                Withdraw
                            </button>
                            <button type="button" className="secondary" disabled={busy} onClick={() => act('key', { type: 'cancel' })}>
                                Cancel
                            </button>
                        </form>
                        <label className="atm-check">
                            <input type="checkbox" checked={receipt} onChange={(event) => setReceipt(event.target.checked)} /> Print a receipt
                        </label>
                    </div>
                )}

                {completed && (
                    <button type="button" disabled={busy} onClick={() => act('reset')}>
                        Next customer
                    </button>
                )}
            </div>

            <div className="atm-slots">
                <Slot
                    name="Card slot"
                    state={atm.cardSlot}
                    action={atm.cardSlot === 'ejected' ? <button type="button" disabled={busy} onClick={() => act('takeCard')}>Take card</button> : null}
                />
                <Slot
                    name="Cash slot"
                    state={atm.cashSlot === 'presented' ? `${atm.amount} presented` : atm.cashSlot}
                    action={atm.cashSlot === 'presented' ? <button type="button" disabled={busy} onClick={() => act('takeCash')}>Take cash</button> : null}
                />
                <Slot name="Receipt" state={atm.receipt} action={null} />
            </div>
        </div>
    );
}

function Slot({ name, state, action }: { name: string; state: string; action: ReactNode }) {
    return (
        <div className="atm-slot">
            <span className="atm-slot-name">{name}</span>
            <span className={`atm-slot-state atm-slot-${state.split(' ').pop()}`}>{state}</span>
            {action}
        </div>
    );
}

export function BankPanel({ panel, busy, act }: { panel: AtmPanelData; busy: boolean; act: Act }) {
    const { bank } = panel;

    return (
        <section className="panel">
            <h2>Bank</h2>
            <label className="atm-check">
                <input type="checkbox" checked={bank.auto} disabled={busy} onChange={(event) => act('bankAuto', { on: event.target.checked })} /> Answer
                automatically
            </label>
            <p className="field-hint">
                Turn it off to answer each task yourself - in a later request, after the Execution was saved. Without an answer the ATM gives up after{' '}
                {panel.timeouts.bank} s.
            </p>

            <h3 className="sm-subtitle">Tasks waiting for an answer</h3>
            {bank.inbox.length === 0 ? (
                <p className="empty">None.</p>
            ) : (
                <ul className="bank-inbox">
                    {bank.inbox.map((task) => (
                        <li key={task.id}>
                            <div>
                                <code>{task.task}</code> <span className="field-hint">{task.summary}</span>
                            </div>
                            <div className="atm-row">
                                <button type="button" disabled={busy} onClick={() => act('bankAnswer', { task: task.id })}>
                                    Answer: {task.assessment}
                                </button>
                                <button type="button" className="secondary" disabled={busy} onClick={() => act('bankAnswer', { task: task.id, decline: true })}>
                                    Decline
                                </button>
                                <button type="button" className="link" disabled={busy} onClick={() => act('bankLose', { task: task.id })}>
                                    Lose the answer
                                </button>
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            <h3 className="sm-subtitle">Accounts</h3>
            <table className="sm-table">
                <thead>
                    <tr>
                        <th>Holder</th>
                        <th>PIN</th>
                        <th>Balance</th>
                        <th>Today</th>
                    </tr>
                </thead>
                <tbody>
                    {bank.accounts.map((account) => (
                        <tr key={account.number}>
                            <td>{account.holder}</td>
                            <td>
                                <code>{account.pin}</code>
                            </td>
                            <td>{account.balance}</td>
                            <td>
                                {account.withdrawnToday} / {account.dailyLimit}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </section>
    );
}
