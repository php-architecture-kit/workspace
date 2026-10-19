import { useMemo } from 'react';
import type { Graph, GraphEdge, GraphNode, Pointer } from './types';

const CELL_W = 184;
const CELL_H = 60;
const BOX_W = 134;
const BOX_H = 38;
const PAD = 24;
/** Room on the left for the loops of the first column. */
const LEFT = 64;

type Point = { x: number; y: number };

const COMPONENT_NAMES: Record<string, string> = {
    SingleNodeDefinition: 'AwaitState',
    AwaitStateComponent: 'AwaitState',
    AsyncComponent: 'Async',
    ForkComponent: 'Fork',
    JoinComponent: 'Join',
    RaceComponent: 'Race',
    ChoiceComponent: 'Choice',
};

function center(node: GraphNode): Point {
    return { x: LEFT + node.x * CELL_W + BOX_W / 2, y: PAD + node.y * CELL_H + BOX_H / 2 };
}

/** Point where the ray from the box centre towards `toward` leaves the box. */
function border(node: GraphNode, toward: Point): Point {
    const c = center(node);
    const dx = toward.x - c.x;
    const dy = toward.y - c.y;
    if (dx === 0 && dy === 0) {
        return c;
    }
    const scale = Math.min(
        dx === 0 ? Infinity : BOX_W / 2 / Math.abs(dx),
        dy === 0 ? Infinity : BOX_H / 2 / Math.abs(dy),
    );

    return { x: c.x + dx * scale, y: c.y + dy * scale };
}

type Path = { d: string; label: Point };

function edgePath(from: GraphNode, to: GraphNode, bend: number): Path {
    const a = center(from);
    const b = center(to);
    const sameColumn = Math.abs(from.x - to.x) < 0.01 && Math.abs(from.y - to.y) > 1.01;
    const sameRow = Math.abs(from.y - to.y) < 0.01 && Math.abs(from.x - to.x) > 1.01;

    let control: Point | null = null;
    if (sameColumn) {
        // loops of the first column go around its free left side
        const side = from.x === 0 ? -1 : 1;
        control = { x: a.x + side * (BOX_W / 2 + 34 + 8 * Math.abs(from.y - to.y)), y: (a.y + b.y) / 2 };
    } else if (sameRow) {
        control = { x: (a.x + b.x) / 2, y: a.y - BOX_H / 2 - 30 - 6 * Math.abs(from.x - to.x) };
    } else if (bend !== 0) {
        const mx = (a.x + b.x) / 2;
        const my = (a.y + b.y) / 2;
        const len = Math.hypot(b.x - a.x, b.y - a.y) || 1;
        control = { x: mx + (-(b.y - a.y) / len) * bend, y: my + ((b.x - a.x) / len) * bend };
    }

    if (control === null) {
        const start = border(from, b);
        const end = border(to, a);

        return { d: `M${start.x},${start.y} L${end.x},${end.y}`, label: { x: (start.x + end.x) / 2, y: (start.y + end.y) / 2 } };
    }

    const start = border(from, control);
    const end = border(to, control);

    return {
        d: `M${start.x},${start.y} Q${control.x},${control.y} ${end.x},${end.y}`,
        label: { x: 0.25 * start.x + 0.5 * control.x + 0.25 * end.x, y: 0.25 * start.y + 0.5 * control.y + 0.25 * end.y },
    };
}

type GraphViewProps = {
    graph: Graph;
    pointers: Pointer[];
    trail: string[];
};

export default function GraphView({ graph, pointers, trail }: GraphViewProps) {
    const byId = useMemo(() => Object.fromEntries(graph.nodes.map((node) => [node.id, node])), [graph]);
    const width = LEFT + PAD + (Math.max(...graph.nodes.map((node) => node.x)) + 1) * CELL_W;
    const height = PAD * 2 + (Math.max(...graph.nodes.map((node) => node.y)) + 1) * CELL_H;

    const active = new Map<string, Pointer[]>();
    pointers.forEach((pointer) => active.set(pointer.box, [...(active.get(pointer.box) ?? []), pointer]));
    const visited = new Set(trail);
    const walked = new Set(trail.slice(1).map((box, index) => `${trail[index]}>${box}`));

    const paths = useMemo(() => {
        const keys = new Set(graph.edges.map((edge) => `${edge.from}>${edge.to}`));

        return graph.edges.map((edge: GraphEdge) => {
            const reverse = keys.has(`${edge.to}>${edge.from}`);

            return { edge, path: edgePath(byId[edge.from], byId[edge.to], reverse ? 18 : 0) };
        });
    }, [graph, byId]);

    return (
        <div className="sm-graph">
            <svg width={width} height={height} viewBox={`0 0 ${width} ${height}`} role="img" aria-label="State machine graph">
                <defs>
                    <marker id="sm-arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse">
                        <path d="M0,0 L10,5 L0,10 z" className="sm-arrow" />
                    </marker>
                    <marker id="sm-arrow-hot" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse">
                        <path d="M0,0 L10,5 L0,10 z" className="sm-arrow-hot" />
                    </marker>
                </defs>

                {paths.map(({ edge, path }) => {
                    const hot = walked.has(`${edge.from}>${edge.to}`);

                    return (
                        <g key={`${edge.from}>${edge.to}`} className={hot ? 'sm-edge sm-edge-hot' : 'sm-edge'}>
                            <path d={path.d} markerEnd={hot ? 'url(#sm-arrow-hot)' : 'url(#sm-arrow)'} />
                        </g>
                    );
                })}

                {graph.nodes.map((node) => {
                    const here = active.get(node.id) ?? [];
                    const component = node.kind !== 'step';
                    const classes = ['sm-box', component ? 'sm-box-component' : '', here.length > 0 ? 'sm-box-active' : '', visited.has(node.id) ? 'sm-box-visited' : ''];
                    const x = LEFT + node.x * CELL_W;
                    const y = PAD + node.y * CELL_H;

                    return (
                        <g key={node.id} className={classes.filter(Boolean).join(' ')}>
                            <title>{node.id}</title>
                            <rect x={x} y={y} width={BOX_W} height={BOX_H} rx={component ? 4 : 10} />
                            {component && (
                                <text className="sm-box-kind" x={x + 6} y={y - 3}>
                                    {COMPONENT_NAMES[node.kind] ?? node.kind}
                                </text>
                            )}
                            <text x={x + BOX_W / 2} y={y + BOX_H / 2} textAnchor="middle" dominantBaseline="central">
                                {node.label}
                            </text>
                            {here.map((pointer, index) => (
                                <g key={pointer.name} className="sm-pointer" transform={`translate(${x + BOX_W - 12 - index * 26}, ${y - 7})`}>
                                    <title>{`${pointer.name} at ${pointer.node}${pointer.waiting ? ' (waiting)' : ''}`}</title>
                                    <rect x={-12} y={-8} width={24} height={16} rx={8} />
                                    <text textAnchor="middle" dominantBaseline="central">
                                        {pointer.name}
                                    </text>
                                </g>
                            ))}
                        </g>
                    );
                })}
                {paths
                    .filter(({ edge }) => edge.label !== '')
                    .map(({ edge, path }) => (
                        <text
                            key={`label:${edge.from}>${edge.to}`}
                            className={walked.has(`${edge.from}>${edge.to}`) ? 'sm-edge-label sm-edge-label-hot' : 'sm-edge-label'}
                            x={path.label.x}
                            y={path.label.y}
                            textAnchor="middle"
                            dominantBaseline="middle"
                        >
                            {edge.label}
                        </text>
                    ))}
            </svg>
        </div>
    );
}
