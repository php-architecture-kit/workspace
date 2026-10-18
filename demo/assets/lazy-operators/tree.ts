import type { CatalogArgument, CatalogEntry, NodeDefinition } from './types';

export type InputType = 'int' | 'float' | 'string' | 'bool' | 'array';

export type InputValue = { type: InputType; text: string };

const LITERAL_FOR_TYPE: Record<InputType, string> = {
    int: 'int_literal',
    float: 'float_literal',
    string: 'string_literal',
    bool: 'bool_literal',
    array: 'array_literal',
};

const DEFAULT_NATIVE: Record<string, unknown> = { int: 0, float: 0, string: '', bool: false, array: [] };

/** Starting values for native arguments whose format the type alone does not tell. */
const NATIVE_EXAMPLES: Record<string, unknown> = {
    'datetime_literal.value': '2026-10-17T10:00:00+02:00',
    'datetime_literal.timezone': 'Europe/Warsaw',
    'interval_literal.value': 'P7D',
    'time_literal.value': '09:00',
};

export function isNode(value: unknown): value is NodeDefinition {
    return typeof value === 'object' && value !== null && typeof (value as NodeDefinition).expression === 'string';
}

/** A node fits an argument when it implements any of the value types the argument accepts. */
export function fits(entry: CatalogEntry, accepts: string[]): boolean {
    return accepts.some((type) => entry.types.includes(type));
}

export function createNode(entry: CatalogEntry): NodeDefinition {
    const args: Record<string, unknown> = {};

    entry.arguments.forEach((argument) => {
        if (argument.kind === 'list' || argument.kind === 'cases') {
            args[argument.name] = [];
        } else if (argument.kind === 'native' && !argument.optional && argument.nativeType !== null) {
            args[argument.name] = NATIVE_EXAMPLES[`${entry.key}.${argument.name}`] ?? DEFAULT_NATIVE[argument.nativeType] ?? '';
        }
    });

    return { expression: entry.key, version: entry.version, arguments: args };
}

/** Names of all runtime_port nodes in the tree, in order of first appearance. */
export function collectPorts(value: unknown, names: string[] = []): string[] {
    if (Array.isArray(value)) {
        value.forEach((item) => collectPorts(item, names));
    } else if (isNode(value)) {
        const name = value.arguments.name;

        if (value.expression === 'runtime_port' && typeof name === 'string' && name !== '' && !names.includes(name)) {
            names.push(name);
        }

        Object.values(value.arguments).forEach((argument) => collectPorts(argument, names));
    } else if (typeof value === 'object' && value !== null) {
        Object.values(value).forEach((item) => collectPorts(item, names));
    }

    return names;
}

export function inputFromLiteral(node: NodeDefinition): InputValue {
    const type = (Object.keys(LITERAL_FOR_TYPE) as InputType[]).find((key) => LITERAL_FOR_TYPE[key] === node.expression) ?? 'string';

    return { type, text: nativeText(node.arguments.value) };
}

/**
 * Turns an input field into a literal node. Text that does not parse as the chosen type is sent
 * as-is, so the builder's own type error shows up instead of the UI hiding it.
 */
export function literalFromInput(input: InputValue): NodeDefinition {
    return { expression: LITERAL_FOR_TYPE[input.type], arguments: { value: parseNative(input.type, input.text) } };
}

export function parseNative(type: string, text: string): unknown {
    switch (type) {
        case 'int':
            return /^-?\d+$/.test(text.trim()) ? Number.parseInt(text, 10) : text;
        case 'float':
            return text.trim() !== '' && Number.isFinite(Number(text)) ? Number(text) : text;
        case 'bool':
            return text === 'true';
        case 'array':
            try {
                return JSON.parse(text);
            } catch {
                return text;
            }
        default:
            return text;
    }
}

export function nativeText(value: unknown): string {
    if (value === undefined || value === null) {
        return '';
    }

    return typeof value === 'object' ? JSON.stringify(value) : String(value);
}

const TEMPLATE_MAX_LENGTH = 32;

/**
 * One-line, human-readable summary of a node, e.g. "⟨price⟩ x (int) ⟨quantity⟩ > 100".
 * Short formulas from the registry (#[Formula], right-hand side) serve as templates with the
 * argument names replaced by their own summaries; longer, descriptive ones fall back to Name(args).
 */
export function summarize(value: unknown, byKey: Record<string, CatalogEntry>): string {
    if (!isNode(value)) {
        return '…';
    }

    const entry = byKey[value.expression];

    if (entry === undefined) {
        return value.expression;
    }

    const args = value.arguments;

    if (value.expression === 'runtime_port') {
        return `⟨${typeof args.name === 'string' && args.name !== '' ? args.name : '?'}⟩`;
    }

    const template = entry.formula?.split(' = ').slice(1).join(' = ') ?? '';

    if (template !== '' && template.length <= TEMPLATE_MAX_LENGTH && entry.arguments.length > 0) {
        const parts: Record<string, string> = {};
        entry.arguments.forEach((argument) => {
            parts[argument.name] = summarizeArgument(argument, args[argument.name], byKey, true);
        });
        const names = entry.arguments.map((argument) => argument.name).sort((a, b) => b.length - a.length);

        return template.replace(new RegExp(`\\b(${names.join('|')})\\b`, 'g'), (name) => parts[name] ?? name);
    }

    // Commas already separate the arguments here, so children need no parentheses.
    const given = entry.arguments
        .filter((argument) => argument.name in args)
        .map((argument) => summarizeArgument(argument, args[argument.name], byKey, false));

    return `${entry.name}(${given.join(', ')})`;
}

function summarizeArgument(argument: CatalogArgument, value: unknown, byKey: Record<string, CatalogEntry>, wrap: boolean): string {
    if (value === undefined) {
        return argument.optional ? (argument.defaultValue ?? 'default') : '…';
    }

    switch (argument.kind) {
        case 'expression':
            return summarizeChild(value, byKey, wrap);
        case 'list':
            return Array.isArray(value) ? value.map((item) => summarizeChild(item, byKey, false)).join(', ') : '…';
        case 'cases':
            return Array.isArray(value) ? `${value.length} case${value.length === 1 ? '' : 's'}` : '…';
        case 'enum':
            return String(value);
        case 'callback':
            return 'ƒ';
        default:
            return JSON.stringify(value);
    }
}

/**
 * Wraps a child with two or more arguments in parentheses, so operator nesting stays readable.
 * Casts only adapt a value's type, so inside a parent's summary they show their operand.
 */
function summarizeChild(value: unknown, byKey: Record<string, CatalogEntry>, wrap: boolean): string {
    const entry = isNode(value) ? byKey[value.expression] : undefined;

    if (isNode(value) && entry?.group === 'Cast' && entry.arguments.length === 1) {
        return summarizeChild(value.arguments[entry.arguments[0].name], byKey, wrap);
    }

    const summary = summarize(value, byKey);

    return wrap && entry !== undefined && entry.arguments.length >= 2 && summary.includes(' ') ? `(${summary})` : summary;
}

const GROUP_ICONS: Record<string, string> = {
    Arithmetic: '±',
    Comparison: '≷',
    Comparator: '⇔',
    Logical: '∧',
    Conditional: '?',
    Cast: '⇢',
    Static: '#',
    Runtime: '⟨⟩',
    Custom: 'ƒ',
    Math: 'π',
    List: '∑',
    Array: '[ ]',
    Allocation: '%',
    BcMath: 'bc',
    Temporal: '◷',
};

export function groupIcon(group: string): string {
    const family = group.split(' | ')[0] ?? group;

    return GROUP_ICONS[family] ?? family.charAt(0);
}

/** A stable hue per group family, so every family gets its own icon colour. */
export function groupHue(group: string): number {
    const family = group.split(' | ')[0] ?? group;
    let hash = 0;

    for (const char of family) {
        hash = (hash * 31 + char.charCodeAt(0)) % 360;
    }

    return hash;
}

export type MissingArgument = { path: string; node: string; argument: string };

/**
 * Required arguments that are still empty anywhere in the tree, in the builder's path format,
 * so the page can point at them before sending an incomplete definition.
 */
export function findMissing(value: unknown, byKey: Record<string, CatalogEntry>, path = '$'): MissingArgument[] {
    if (!isNode(value)) {
        return [];
    }

    const entry = byKey[value.expression];

    if (entry === undefined) {
        return [];
    }

    return entry.arguments.flatMap((argument) => {
        const argumentPath = `${path}.arguments.${argument.name}`;
        const current = value.arguments[argument.name];

        switch (argument.kind) {
            case 'expression':
                if (isNode(current)) {
                    return findMissing(current, byKey, argumentPath);
                }

                return argument.optional ? [] : [{ path: argumentPath, node: entry.name, argument: argument.name }];

            case 'list':
                return Array.isArray(current) ? current.flatMap((item, index) => findMissing(item, byKey, `${argumentPath}[${index}]`)) : [];

            case 'cases':
                return Array.isArray(current)
                    ? (current as Record<string, unknown>[]).flatMap((item, index) =>
                          (argument.fields ?? []).flatMap((field) => {
                              const fieldPath = `${argumentPath}[${index}].${field.name}`;

                              return isNode(item[field.name])
                                  ? findMissing(item[field.name], byKey, fieldPath)
                                  : [{ path: fieldPath, node: `${entry.name} case ${index + 1}`, argument: field.name }];
                          }),
                      )
                    : [];

            default:
                return current === undefined && !argument.optional ? [{ path: argumentPath, node: entry.name, argument: argument.name }] : [];
        }
    });
}
