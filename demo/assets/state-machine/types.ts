export type GraphNode = { id: string; label: string; kind: string; x: number; y: number };
export type GraphEdge = { from: string; to: string; label: string };
export type Graph = { nodes: GraphNode[]; edges: GraphEdge[] };

export type Pointer = { name: string; box: string; node: string; waiting: boolean };
export type StateView = { name: string; technical: boolean; details: Record<string, string> };
export type LogEntry = { id: number; time: string; kind: string; text: string };

export type Snapshot<Panel> = {
    time: string;
    status: 'waiting' | 'completed';
    pointers: Pointer[];
    trail: string[];
    states: StateView[];
    log: LogEntry[];
    panel: Panel;
};

export type ScenarioProps<Panel = unknown> = {
    id: 'atm' | 'elevator';
    title: string;
    graph: Graph;
    snapshot: Snapshot<Panel>;
    actionUrl: string;
};

export type Act = (action: string, payload?: Record<string, unknown>) => Promise<void>;

export type AtmPanelData = {
    atm: {
        mode: 'insert-card' | 'pin' | 'amount' | 'take-card' | 'take-cash' | 'busy' | 'finished';
        screen: string;
        notice: string | null;
        cardSlot: 'empty' | 'inside' | 'ejected' | 'retained';
        cashSlot: 'empty' | 'presented' | 'retracted';
        receipt: 'none' | 'printed' | 'skipped';
        pinAttempts: number;
        amount: number | null;
        outcome: string | null;
    };
    card: { number?: string; holder?: string };
    cards: { number: string; label: string }[];
    quickAmounts: number[];
    bank: {
        auto: boolean;
        accounts: { number: string; holder: string; pin: string; balance: number; dailyLimit: number; withdrawnToday: number }[];
        inbox: { id: string; task: string; summary: string; assessment: string }[];
    };
    customer: number;
    timeouts: { input: number; bank: number };
};

export type ElevatorPanelData = {
    car: {
        floor: number;
        direction: 'up' | 'down' | 'idle';
        doors: 'open' | 'closed' | 'closing';
        moving: boolean;
        halted: boolean;
        load: number;
        alarm: boolean;
        progress: number;
    };
    calls: { hall: string[]; car: number[] };
    stop: boolean;
    obstruction: boolean;
    floors: number;
    capacity: number;
    timing: { ride: number; doors: number };
};
